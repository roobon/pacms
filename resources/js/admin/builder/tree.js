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

/** Can `child` be placed directly inside `parent` (null = page level)? Mirrors BlockType::allowedUnder. */
export function canPlace(child, parent) {
    if (!child) return false;
    const parents = child.capabilities.allowed_parents;
    if (Array.isArray(parents) && parents.length === 0) return parent === null;
    if (Array.isArray(parents) && (parent === null || !parents.includes(parent.slug))) return false;
    if (parent === null) return true;

    const allowed = parent.capabilities.allowed_children;
    if (!Array.isArray(allowed) || (parent.capabilities.excluded_children ?? []).includes(child.slug)) return false;
    return allowed.includes('*') || allowed.includes(child.slug);
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
