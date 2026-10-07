/**
 * Pure helpers for block trees (schema node format). Every function returns new arrays;
 * nothing is mutated, which keeps undo/redo (Phase 5) and React updates simple.
 */

const CROCKFORD = '0123456789abcdefghjkmnpqrstvwxyz';

/** 26-character lowercase ULID (time-sortable), matching the server's block uuid format. */
export function ulid() {
    let time = Date.now();
    let out = '';
    for (let i = 0; i < 10; i++) {
        out = CROCKFORD[time % 32] + out;
        time = Math.floor(time / 32);
    }
    const random = new Uint8Array(16);
    crypto.getRandomValues(random);
    for (const byte of random) out += CROCKFORD[byte % 32];
    return out;
}

/** Find a node and its context. */
export function findNode(nodes, uuid, parent = null) {
    for (let index = 0; index < nodes.length; index++) {
        const node = nodes[index];
        if (node.uuid === uuid) return { node, parent, index, siblings: nodes };
        const found = findNode(node.children ?? [], uuid, node);
        if (found) return found;
    }
    return null;
}

/** Replace one node using updater(node) => node. */
export function updateNode(nodes, uuid, updater) {
    return nodes.map((node) => {
        if (node.uuid === uuid) return updater(node);
        if (node.children?.length) {
            const children = updateNode(node.children, uuid, updater);
            return children === node.children ? node : { ...node, children };
        }
        return node;
    });
}

export function removeNode(nodes, uuid) {
    return nodes
        .filter((node) => node.uuid !== uuid)
        .map((node) => (node.children?.length ? { ...node, children: removeNode(node.children, uuid) } : node));
}

/** Insert at parentUuid (null = root) and index (default: end). */
export function insertNode(nodes, parentUuid, index, newNode) {
    if (parentUuid === null) {
        const copy = [...nodes];
        copy.splice(index ?? copy.length, 0, newNode);
        return copy;
    }
    return updateNode(nodes, parentUuid, (parent) => {
        const children = [...(parent.children ?? [])];
        children.splice(index ?? children.length, 0, newNode);
        return { ...parent, children };
    });
}

/** Move a node up (-1) or down (+1) among its siblings. */
export function moveNode(nodes, uuid, delta) {
    const found = findNode(nodes, uuid);
    if (!found) return nodes;
    const target = found.index + delta;
    if (target < 0 || target >= found.siblings.length) return nodes;

    const reorder = (siblings) => {
        const copy = [...siblings];
        const [item] = copy.splice(found.index, 1);
        copy.splice(target, 0, item);
        return copy;
    };

    return found.parent === null ? reorder(nodes) : updateNode(nodes, found.parent.uuid, (parent) => ({ ...parent, children: reorder(parent.children) }));
}

/** Deep copy with fresh uuids (duplicate, insert template). */
export function cloneNode(node) {
    return { ...structuredClone(node), uuid: ulid(), children: (node.children ?? []).map(cloneNode) };
}

/** Number of nodes in the tree. */
export function countNodes(nodes) {
    return nodes.reduce((sum, node) => sum + 1 + countNodes(node.children ?? []), 0);
}

/**
 * A new node of `slug` with field defaults, type defaults and default children.
 *
 * @param {string} slug
 * @param {Record<string, Object>} types definitions keyed by slug
 * @param {Object} [overrides]
 */
export function createNode(slug, types, overrides = {}) {
    const type = types[slug];
    const defaults = type?.defaults ?? {};
    const content = {};
    for (const field of type?.fields ?? []) {
        if (field.default !== undefined) content[field.key] = field.default;
    }

    const node = {
        uuid: ulid(),
        type: slug,
        content: { ...content, ...structuredClone(defaults.content ?? {}), ...(overrides.content ?? {}) },
    };
    for (const section of ['source', 'display', 'layout', 'style', 'responsive', 'advanced']) {
        const value = { ...structuredClone(defaults[section] ?? {}), ...(overrides[section] ?? {}) };
        if (Object.keys(value).length) node[section] = value;
    }

    const childSpecs = overrides.children ?? defaults.children ?? [];
    if (childSpecs.length) {
        node.children = childSpecs.map((spec) => createNode(spec.type, types, spec));
    }

    return node;
}

/** May the type be used in this builder context (page, global, template, structure)? Mirrors BlockType::allowedIn. */
export function allowedInContext(type, context) {
    const contexts = type?.capabilities.contexts;
    return !Array.isArray(contexts) || contexts.includes(context);
}

/**
 * Can `child` be placed directly inside `parent` (null = page level)? Mirrors
 * BlockType::allowedUnder. Transparent wrappers (repeat, when) may go anywhere.
 */
export function canPlace(child, parent) {
    if (!child) return false;
    if (child.capabilities.transparent) return parent === null || Array.isArray(parent.capabilities.allowed_children);
    const parents = child.capabilities.allowed_parents;
    if (Array.isArray(parents) && parents.length === 0) return parent === null;
    if (Array.isArray(parents) && (parent === null || !parents.includes(parent.slug))) return false;
    if (parent === null) return true;

    const allowed = parent.capabilities.allowed_children;
    if (!Array.isArray(allowed) || (parent.capabilities.excluded_children ?? []).includes(child.slug)) return false;
    return allowed.includes('*') || allowed.includes(child.slug);
}

/**
 * The type a block placed inside `parentUuid` renders into: the parent itself, or for
 * transparent wrappers (repeat, when) their nearest real ancestor. null = top level.
 */
export function effectiveParentType(nodes, parentUuid, types) {
    let uuid = parentUuid;
    while (uuid) {
        const found = findNode(nodes, uuid);
        if (!found) return null;
        const type = types[found.node.type];
        if (!type?.capabilities.transparent) return type ?? null;
        uuid = found.parent?.uuid ?? null;
    }
    return null;
}

/** Can a node of `slug` be inserted inside `parentUuid` (null = top level)? */
export function canInsert(nodes, slug, parentUuid, types) {
    const type = types[slug];
    if (!type) return false;
    const parentNode = parentUuid ? findNode(nodes, parentUuid)?.node : null;
    const parentType = parentNode ? types[parentNode.type] : null;
    if (parentType && !Array.isArray(parentType.capabilities.allowed_children)) return false;
    if (type.capabilities.transparent) return true;
    return canPlace(type, effectiveParentType(nodes, parentUuid, types));
}

/** Is `uuid` the node `ancestorUuid` or one of its descendants? */
export function isWithin(nodes, uuid, ancestorUuid) {
    const found = findNode(nodes, ancestorUuid);
    return Boolean(found && (found.node.uuid === uuid || findNode(found.node.children ?? [], uuid)));
}

/**
 * Move a node to `parentUuid` (null = top level) at `index` (position among the new
 * siblings, counted without the moved node). Refuses to move a node into itself.
 */
export function moveTo(nodes, uuid, parentUuid, index) {
    const found = findNode(nodes, uuid);
    if (!found || (parentUuid && isWithin(nodes, parentUuid, uuid))) return nodes;
    return insertNode(removeNode(nodes, uuid), parentUuid, index, found.node);
}

/**
 * Depth-first list of visible rows for the structure tree and drag & drop:
 * [{ uuid, node, parentUuid, depth, index }]. Children of `collapsed` uuids are skipped.
 */
export function flatten(nodes, collapsed = new Set(), parentUuid = null, depth = 0) {
    return nodes.flatMap((node, index) => [
        { uuid: node.uuid, node, parentUuid, depth, index },
        ...(collapsed.has(node.uuid) ? [] : flatten(node.children ?? [], collapsed, node.uuid, depth + 1)),
    ]);
}

/**
 * Where a dragged row would land: given the flattened rows (without the dragged row's
 * descendants), the target row index and the horizontal drag offset in levels, return
 * { parentUuid, index, depth } or null.
 */
export function projectDrop(rows, activeUuid, overIndex, levelOffset) {
    const activeIndex = rows.findIndex((row) => row.uuid === activeUuid);
    if (activeIndex < 0 || overIndex < 0) return null;

    const ordered = [...rows];
    const [active] = ordered.splice(activeIndex, 1);
    ordered.splice(overIndex, 0, active);

    const previous = ordered[overIndex - 1];
    const next = ordered[overIndex + 1];
    const maxDepth = previous ? previous.depth + 1 : 0;
    const minDepth = next ? next.depth : 0;
    const depth = Math.max(minDepth, Math.min(maxDepth, active.depth + levelOffset));

    // The parent is the closest row above with depth - 1.
    let parentUuid = null;
    for (let i = overIndex - 1; i >= 0; i--) {
        if (ordered[i].depth === depth - 1) {
            parentUuid = ordered[i].uuid;
            break;
        }
        if (ordered[i].depth < depth - 1) break;
    }

    // Position among the new siblings: siblings above the drop point at the same depth.
    let index = 0;
    for (let i = overIndex - 1; i >= 0; i--) {
        if (ordered[i].depth < depth) break;
        if (ordered[i].depth === depth) index++;
    }

    return { parentUuid, index, depth };
}

/** Ancestors of a node, outermost first. */
export function ancestorsOf(nodes, uuid) {
    const chain = [];
    let found = findNode(nodes, uuid);
    while (found?.parent) {
        chain.unshift(found.parent);
        found = findNode(nodes, found.parent.uuid);
    }
    return chain;
}

/**
 * Fields a block in a custom type's layout may link to (mirrors App\Cms\Fields\Bindings):
 * the type's fields, plus "item.…" fields of the innermost enclosing `repeat` block.
 *
 * @returns {{options: Array<{path: string, label: string, type: string}>}}
 */
export function bindingScope(nodes, uuid, fields) {
    const byKey = Object.fromEntries(fields.map((field) => [field.key, field]));
    let item = null;
    for (const ancestor of ancestorsOf(nodes, uuid)) {
        if (ancestor.type !== 'repeat' || typeof ancestor.content?.field !== 'string') continue;
        const path = ancestor.content.field;
        const repeater = path.startsWith('item.') ? item?.[path.slice(5)] : byKey[path];
        item = repeater?.type === 'repeater' ? Object.fromEntries((repeater.fields ?? []).map((field) => [field.key, field])) : null;
    }

    return {
        options: [
            ...fields.map((field) => ({ path: field.key, label: field.label, type: field.type })),
            ...Object.values(item ?? {}).map((field) => ({ path: `item.${field.key}`, label: `Row → ${field.label}`, type: field.type })),
        ],
    };
}

/** Same tree with fresh uuids everywhere (paste, insert template, detach). */
export function cloneTree(nodes) {
    return nodes.map(cloneNode);
}

/** Label of a block in the structure tree: its name, its main text, or its type. */
export function labelOf(node, types) {
    const text = node.content?.text ?? node.content?.title ?? node.content?.heading ?? node.content?.label ?? node.content?.name;
    const summary = typeof text === 'string' && text ? text.slice(0, 40) : null;
    return node.name || summary || types[node.type]?.label || node.type;
}

/** Read a nested value by dotted path. */
export function getIn(object, path) {
    return path.split('.').reduce((value, key) => (value == null ? undefined : value[key]), object);
}

/** Immutable set by dotted path; undefined/'' removes the key. */
export function setIn(object, path, value) {
    const [key, ...rest] = path.split('.');
    const base = Array.isArray(object) ? [...object] : { ...(object ?? {}) };
    if (rest.length === 0) {
        if (value === undefined || value === '' || value === null) {
            if (Array.isArray(base)) base[key] = value;
            else delete base[key];
        } else {
            base[key] = value;
        }
        return base;
    }
    base[key] = setIn(base[key], rest.join('.'), value);
    return base;
}
