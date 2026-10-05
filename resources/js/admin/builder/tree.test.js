import { describe, expect, it } from 'vitest';
import { canPlace, cloneNode, createNode, findNode, insertNode, moveNode, removeNode, setIn, ulid } from './tree.js';

const type = (slug, capabilities = {}, extra = {}) => ({
    slug,
    label: slug,
    fields: [],
    defaults: {},
    capabilities: { allowed_children: null, allowed_parents: null, excluded_children: [], ...capabilities },
    ...extra,
});

const types = {
    section: type('section', { allowed_children: ['*'], allowed_parents: [], excluded_children: ['section'] }, { defaults: { layout: { container: 'boxed' } } }),
    columns: type('columns', { allowed_children: ['column'] }, { defaults: { children: [{ type: 'column' }, { type: 'column' }] } }),
    column: type('column', { allowed_children: ['*'], allowed_parents: ['columns'] }),
    heading: type('heading', {}, { fields: [{ key: 'level', type: 'select', default: '2' }], defaults: { content: { text: 'New heading' } } }),
};

describe('tree helpers', () => {
    it('generates 26-character lowercase ULIDs', () => {
        const id = ulid();
        expect(id).toMatch(/^[0-9a-z]{26}$/);
        expect(ulid()).not.toBe(id);
    });

    it('creates nodes with field defaults, type defaults and default children', () => {
        const heading = createNode('heading', types);
        expect(heading.content).toEqual({ level: '2', text: 'New heading' });

        const columns = createNode('columns', types);
        expect(columns.children).toHaveLength(2);
        expect(columns.children[0].type).toBe('column');
        expect(columns.children[0].uuid).not.toBe(columns.children[1].uuid);
    });

    it('mirrors the server placement rules', () => {
        expect(canPlace(types.section, null)).toBe(true);
        expect(canPlace(types.section, types.section)).toBe(false);
        expect(canPlace(types.column, null)).toBe(false);
        expect(canPlace(types.column, types.columns)).toBe(true);
        expect(canPlace(types.heading, types.column)).toBe(true);
        expect(canPlace(types.heading, types.columns)).toBe(false);
    });

    it('inserts, moves, finds and removes without mutating', () => {
        const a = createNode('section', types);
        const b = createNode('section', types);
        let nodes = insertNode([], null, undefined, a);
        nodes = insertNode(nodes, null, undefined, b);
        const before = nodes;

        nodes = moveNode(nodes, b.uuid, -1);
        expect(nodes.map((n) => n.uuid)).toEqual([b.uuid, a.uuid]);
        expect(before.map((n) => n.uuid)).toEqual([a.uuid, b.uuid]);

        const heading = createNode('heading', types);
        nodes = insertNode(nodes, a.uuid, 0, heading);
        expect(findNode(nodes, heading.uuid).parent.uuid).toBe(a.uuid);

        nodes = removeNode(nodes, heading.uuid);
        expect(findNode(nodes, heading.uuid)).toBeNull();
    });

    it('clones deeply with fresh uuids', () => {
        const original = createNode('columns', types);
        const copy = cloneNode(original);
        expect(copy.uuid).not.toBe(original.uuid);
        expect(copy.children[0].uuid).not.toBe(original.children[0].uuid);
        expect(copy.children).toHaveLength(2);
    });

    it('sets and removes nested values immutably', () => {
        const layout = { padding: { top: 1 } };
        const next = setIn(layout, 'padding.bottom', 2);
        expect(next).toEqual({ padding: { top: 1, bottom: 2 } });
        expect(layout).toEqual({ padding: { top: 1 } });
        expect(setIn(next, 'padding.top', undefined)).toEqual({ padding: { bottom: 2 } });
    });
});
