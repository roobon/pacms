import { beforeEach, describe, expect, it } from 'vitest';
import { useBuilder } from './store.js';
import { findNode } from './tree.js';

const cap = (overrides = {}) => ({ source_modes: ['static'], display_modes: [], allowed_children: null, excluded_children: [], allowed_parents: null, max_children: 50, contexts: null, transparent: false, ...overrides });

const definitions = {
    types: [
        { slug: 'section', label: 'Section', category: 'layout', fields: [], defaults: {}, capabilities: cap({ allowed_children: ['*'], excluded_children: ['section'], allowed_parents: [] }) },
        { slug: 'heading', label: 'Heading', category: 'basic', fields: [{ key: 'text', type: 'text' }], defaults: {}, capabilities: cap() },
        { slug: 'columns', label: 'Columns', category: 'layout', fields: [], defaults: {}, capabilities: cap({ allowed_children: ['column'] }) },
        { slug: 'column', label: 'Column', category: 'layout', fields: [], defaults: {}, capabilities: cap({ allowed_children: ['*'], allowed_parents: ['columns'] }) },
        { slug: 'repeat', label: 'Repeat', category: 'structural', fields: [], defaults: {}, capabilities: cap({ allowed_children: ['*'], contexts: ['structure'], transparent: true }) },
        { slug: 'global-ref', label: 'Global', category: 'structural', fields: [], defaults: {}, capabilities: cap({ contexts: ['page', 'template'] }) },
    ],
};

const tree = () => [
    { uuid: 'a'.repeat(26), type: 'section', content: {}, children: [{ uuid: 'b'.repeat(26), type: 'heading', content: { text: 'One' } }] },
    { uuid: 'c'.repeat(26), type: 'heading', content: { text: 'Two' } },
];

const store = () => useBuilder.getState();

beforeEach(() => {
    localStorage.clear();
    store().init({ definitions, nodes: tree(), errors: {}, readonly: false, context: 'page' });
});

describe('builder store', () => {
    it('undoes and redoes edits', () => {
        store().update('c'.repeat(26), (node) => ({ ...node, content: { text: 'Changed' } }));
        expect(findNode(store().nodes, 'c'.repeat(26)).node.content.text).toBe('Changed');

        store().undo();
        expect(findNode(store().nodes, 'c'.repeat(26)).node.content.text).toBe('Two');
        expect(store().future).toHaveLength(1);

        store().redo();
        expect(findNode(store().nodes, 'c'.repeat(26)).node.content.text).toBe('Changed');
    });

    it('merges rapid edits of the same setting into one undo step', () => {
        const uuid = 'c'.repeat(26);
        for (const text of ['T', 'Ty', 'Typ', 'Type']) {
            store().update(uuid, (node) => ({ ...node, content: { text } }), 'content.text');
        }
        expect(store().past).toHaveLength(1);

        store().undo();
        expect(findNode(store().nodes, uuid).node.content.text).toBe('Two');
    });

    it('moves blocks between parents and refuses invalid targets', () => {
        expect(store().moveTo('c'.repeat(26), 'a'.repeat(26), 0)).toBe(true);
        expect(store().nodes).toHaveLength(1);
        expect(store().nodes[0].children.map((n) => n.uuid)).toEqual(['c'.repeat(26), 'b'.repeat(26)]);

        // A section cannot go inside a section, nor inside itself.
        store().add('section');
        const second = store().selected;
        expect(store().moveTo(second, 'a'.repeat(26), 0)).toBe(false);
        expect(store().moveTo('a'.repeat(26), 'b'.repeat(26), 0)).toBe(false);
    });

    it('indents and outdents', () => {
        store().init({ definitions, nodes: [{ uuid: 'a'.repeat(26), type: 'section', content: {}, children: [{ uuid: 'b'.repeat(26), type: 'heading', content: {} }, { uuid: 'c'.repeat(26), type: 'heading', content: {} }] }], context: 'page' });

        expect(store().outdent('c'.repeat(26))).toBe(true);
        expect(store().nodes.map((n) => n.uuid)).toEqual(['a'.repeat(26), 'c'.repeat(26)]);

        // Headings cannot contain blocks, so indenting under one is refused.
        expect(store().indent('c'.repeat(26))).toBe(true);
        expect(store().nodes).toHaveLength(1);
        expect(store().indent('c'.repeat(26))).toBe(false);
    });

    it('copies and pastes with fresh uuids, respecting placement rules', () => {
        store().copy('b'.repeat(26));
        store().select('c'.repeat(26));
        expect(store().paste()).toBe(true);

        expect(store().nodes).toHaveLength(3);
        expect(store().nodes[2].type).toBe('heading');
        expect(store().nodes[2].uuid).not.toBe('b'.repeat(26));

        // A section copied into a section is refused.
        store().copy('a'.repeat(26));
        store().select('b'.repeat(26));
        expect(store().paste()).toBe(false);
    });

    it('inserts template trees only where every block is allowed in this context', () => {
        expect(store().insertNodes([{ uuid: 'x'.repeat(26), type: 'heading', content: { text: 'T' } }], null)).toBe(true);
        expect(store().insertNodes([{ type: 'repeat', content: {} }], null)).toBe(false);
        expect(store().insertNodes([{ type: 'column', content: {} }], null)).toBe(false);
    });

    it('replaces a global block with its detached copy', () => {
        store().init({ definitions, nodes: [{ uuid: 'g'.repeat(26), type: 'global-ref', global_block_id: 3 }], context: 'page' });
        store().replaceWith('g'.repeat(26), [{ uuid: 'h'.repeat(26), type: 'heading', content: { text: 'Copy' } }]);

        expect(store().nodes).toHaveLength(1);
        expect(store().nodes[0].type).toBe('heading');
        expect(store().nodes[0].uuid).not.toBe('h'.repeat(26));
    });

    it('toggles hidden and renames as undoable edits', () => {
        store().toggleHidden('c'.repeat(26));
        store().rename('c'.repeat(26), 'Intro');
        const node = findNode(store().nodes, 'c'.repeat(26)).node;
        expect(node.hidden).toBe(true);
        expect(node.name).toBe('Intro');

        store().undo();
        store().undo();
        expect(findNode(store().nodes, 'c'.repeat(26)).node.hidden).toBeUndefined();
    });
});
