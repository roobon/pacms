import { create } from 'zustand';
import { allowedInContext, canInsert, cloneNode, cloneTree, createNode, findNode, insertNode, isWithin, moveNode, moveTo, removeNode, updateNode } from './tree.js';

const HISTORY_LIMIT = 100;
/** Edits to the same block within this window form one undo step (typing a heading). */
const MERGE_WINDOW_MS = 800;
const CLIPBOARD_KEY = 'pacms:block-clipboard';

/**
 * Builder state (Zustand: components subscribe to the slices they use, so large trees
 * stay responsive). Trees are immutable, so undo/redo keeps previous trees (which share
 * unchanged branches) rather than patches.
 */
export const useBuilder = create((set, get) => ({
    definitions: null,
    types: {},
    nodes: [],
    selected: null,
    errors: {},
    device: 'desktop',
    readonly: false,
    dirty: false,
    /** page | global | template | structure (custom block type layout) */
    context: 'page',
    /** Custom block type field definitions (structure context only), else null. */
    fields: null,
    past: [],
    future: [],
    lastEdit: null,
    /** Short message for the screen-reader live region and the status bar. */
    notice: '',

    init({ definitions, nodes, errors, readonly, context = 'page', fields = null }) {
        const types = Object.fromEntries(definitions.types.map((type) => [type.slug, type]));
        set({ definitions, types, nodes, errors: errors ?? {}, readonly: Boolean(readonly), context, fields, selected: null, dirty: false, past: [], future: [], lastEdit: null });
    },

    select(uuid) {
        set({ selected: uuid, lastEdit: null });
    },

    setDevice(device) {
        set({ device });
    },

    setErrors(errors) {
        set({ errors: errors ?? {} });
    },

    announce(notice) {
        set({ notice });
    },

    /**
     * Replace the tree as one undoable step. `mergeKey` groups rapid edits of the same
     * setting into one step.
     */
    commit(nodes, extra = {}, mergeKey = null) {
        const { nodes: current, past, lastEdit } = get();
        if (nodes === current) return;
        const now = Date.now();
        const merge = mergeKey && lastEdit && lastEdit.key === mergeKey && now - lastEdit.at < MERGE_WINDOW_MS;
        set({
            nodes,
            past: merge ? past : [...past, current].slice(-HISTORY_LIMIT),
            future: [],
            dirty: true,
            lastEdit: mergeKey ? { key: mergeKey, at: now } : null,
            ...extra,
        });
    },

    undo() {
        const { past, nodes, future } = get();
        if (!past.length) return;
        set({ nodes: past[past.length - 1], past: past.slice(0, -1), future: [nodes, ...future], dirty: true, lastEdit: null, notice: 'Undone' });
    },

    redo() {
        const { past, nodes, future } = get();
        if (!future.length) return;
        set({ nodes: future[0], past: [...past, nodes], future: future.slice(1), dirty: true, lastEdit: null, notice: 'Redone' });
    },

    /** Insert a new block of `slug` inside `parentUuid` (null = top level) at `index`. */
    add(slug, parentUuid = null, index = undefined) {
        const node = createNode(slug, get().types);
        get().commit(insertNode(get().nodes, parentUuid, index, node), { selected: node.uuid, notice: `${get().types[slug]?.label ?? slug} added` });
        return node.uuid;
    },

    /** Insert ready-made nodes (template, paste, detach) with fresh uuids. Returns false when not allowed there. */
    insertNodes(nodes, parentUuid = null, index = undefined) {
        const { types, context } = get();
        const usable = (list) => list.every((node) => types[node.type] && allowedInContext(types[node.type], context) && usable(node.children ?? []));
        if (!nodes.length || !usable(nodes) || !nodes.every((node) => canInsert(get().nodes, node.type, parentUuid, types))) return false;
        const copies = cloneTree(nodes);
        let tree = get().nodes;
        copies.forEach((node, i) => {
            tree = insertNode(tree, parentUuid, index === undefined ? undefined : index + i, node);
        });
        get().commit(tree, { selected: copies[0].uuid });
        return true;
    },

    update(uuid, updater, mergeKey = null) {
        get().commit(updateNode(get().nodes, uuid, updater), {}, mergeKey ? `${uuid}:${mergeKey}` : null);
    },

    remove(uuid) {
        const found = findNode(get().nodes, uuid);
        const nextSelection = found?.siblings[found.index + 1]?.uuid ?? found?.siblings[found.index - 1]?.uuid ?? found?.parent?.uuid ?? null;
        get().commit(removeNode(get().nodes, uuid), { selected: nextSelection, notice: 'Block deleted' });
    },

    move(uuid, delta) {
        get().commit(moveNode(get().nodes, uuid, delta), { notice: delta < 0 ? 'Moved up' : 'Moved down' });
    },

    /** Move to another parent/position (drag & drop, indent/outdent). Checks placement rules. */
    moveTo(uuid, parentUuid, index) {
        const { nodes, types } = get();
        const found = findNode(nodes, uuid);
        const intoItself = parentUuid !== null && isWithin(nodes, parentUuid, uuid);
        if (!found || intoItself || !canInsert(removeNode(nodes, uuid), found.node.type, parentUuid, types)) {
            set({ notice: 'That block cannot be placed there.' });
            return false;
        }
        get().commit(moveTo(nodes, uuid, parentUuid, index), { notice: 'Block moved' });
        return true;
    },

    /** Indent: make the block the last child of its previous sibling. */
    indent(uuid) {
        const found = findNode(get().nodes, uuid);
        const previous = found?.siblings[found.index - 1];
        if (!previous) return false;
        return get().moveTo(uuid, previous.uuid, previous.children?.length ?? 0);
    },

    /** Outdent: move the block out of its parent, right after it. */
    outdent(uuid) {
        const found = findNode(get().nodes, uuid);
        if (!found?.parent) return false;
        const parent = findNode(get().nodes, found.parent.uuid);
        return get().moveTo(uuid, parent.parent?.uuid ?? null, parent.index + 1);
    },

    duplicate(uuid) {
        const found = findNode(get().nodes, uuid);
        if (!found) return;
        const copy = cloneNode(found.node);
        get().commit(insertNode(get().nodes, found.parent?.uuid ?? null, found.index + 1, copy), { selected: copy.uuid, notice: 'Block duplicated' });
    },

    toggleHidden(uuid) {
        get().update(uuid, (node) => {
            const copy = { ...node };
            if (copy.hidden) delete copy.hidden;
            else copy.hidden = true;
            return copy;
        });
    },

    rename(uuid, name) {
        get().update(
            uuid,
            (node) => {
                const copy = { ...node };
                if (name.trim()) copy.name = name.slice(0, 120);
                else delete copy.name;
                return copy;
            },
            'name',
        );
    },

    /** Copy a block (with its children) to the clipboard, usable across pages and tabs. */
    copy(uuid) {
        const found = findNode(get().nodes, uuid);
        if (!found) return;
        const payload = JSON.stringify({ pacms: 'blocks', version: '1.0', nodes: [found.node] });
        try {
            localStorage.setItem(CLIPBOARD_KEY, payload);
        } catch {
            // Storage unavailable (private mode): the system clipboard below still works.
        }
        navigator.clipboard?.writeText(payload).catch(() => {});
        set({ notice: 'Block copied' });
    },

    /** Paste after the selected block (or inside it, when it accepts children and is empty). */
    paste() {
        const payload = readClipboard();
        const nodes = payload?.pacms === 'blocks' && Array.isArray(payload.nodes) ? payload.nodes : null;
        if (!nodes) {
            set({ notice: 'Nothing to paste. Copy a block first.' });
            return false;
        }

        const { selected, types } = get();
        const found = selected ? findNode(get().nodes, selected) : null;
        const intoSelected = found && Array.isArray(types[found.node.type]?.capabilities.allowed_children) && !(found.node.children?.length);
        const ok = intoSelected
            ? get().insertNodes(nodes, found.node.uuid, 0)
            : get().insertNodes(nodes, found?.parent?.uuid ?? null, found ? found.index + 1 : undefined);
        set({ notice: ok ? 'Block pasted' : 'The copied block cannot be placed here.' });
        return ok;
    },

    /** Replace one block with other blocks (detach a global block). */
    replaceWith(uuid, nodes) {
        const found = findNode(get().nodes, uuid);
        if (!found) return;
        const copies = cloneTree(nodes);
        let tree = removeNode(get().nodes, uuid);
        copies.forEach((node, i) => {
            tree = insertNode(tree, found.parent?.uuid ?? null, found.index + i, node);
        });
        get().commit(tree, { selected: copies[0]?.uuid ?? null, notice: 'Detached: the blocks are now a local copy' });
    },

    /** Custom block type field definitions (structure context). */
    setFields(fields) {
        set({ fields, dirty: true });
    },
}));

function readClipboard() {
    try {
        return JSON.parse(localStorage.getItem(CLIPBOARD_KEY) ?? 'null');
    } catch {
        return null;
    }
}

/** Errors for one block: { 'content.title': ['…'], … } */
export function errorsFor(errors, uuid) {
    const prefix = `blocks.${uuid}`;
    const out = {};
    for (const [key, messages] of Object.entries(errors ?? {})) {
        if (key === prefix) out[''] = messages;
        else if (key.startsWith(`${prefix}.`)) out[key.slice(prefix.length + 1)] = messages;
    }
    return out;
}
