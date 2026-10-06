import { create } from 'zustand';
import { cloneNode, createNode, findNode, insertNode, moveNode, removeNode, updateNode } from './tree.js';

/**
 * Builder state (Zustand: components subscribe to the slices they use, so large trees
 * stay responsive). Undo/redo and drag & drop arrive in Phase 5.
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

    init({ definitions, nodes, errors, readonly }) {
        const types = Object.fromEntries(definitions.types.map((type) => [type.slug, type]));
        set({ definitions, types, nodes, errors: errors ?? {}, readonly: Boolean(readonly), selected: null, dirty: false });
    },

    select(uuid) {
        set({ selected: uuid });
    },

    setDevice(device) {
        set({ device });
    },

    setErrors(errors) {
        set({ errors: errors ?? {} });
    },

    /** Insert a new block of `slug` inside `parentUuid` (null = page) at `index`. */
    add(slug, parentUuid = null, index = undefined) {
        const node = createNode(slug, get().types);
        set({ nodes: insertNode(get().nodes, parentUuid, index, node), selected: node.uuid, dirty: true });
        return node.uuid;
    },

    update(uuid, updater) {
        set({ nodes: updateNode(get().nodes, uuid, updater), dirty: true });
    },

    remove(uuid) {
        const found = findNode(get().nodes, uuid);
        const nextSelection = found?.siblings[found.index + 1]?.uuid ?? found?.siblings[found.index - 1]?.uuid ?? found?.parent?.uuid ?? null;
        set({ nodes: removeNode(get().nodes, uuid), selected: nextSelection, dirty: true });
    },

    move(uuid, delta) {
        set({ nodes: moveNode(get().nodes, uuid, delta), dirty: true });
    },

    duplicate(uuid) {
        const found = findNode(get().nodes, uuid);
        if (!found) return;
        const copy = cloneNode(found.node);
        set({ nodes: insertNode(get().nodes, found.parent?.uuid ?? null, found.index + 1, copy), selected: copy.uuid, dirty: true });
    },
}));

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
