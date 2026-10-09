import { describe, expect, it } from 'vitest';
import { build, flatten, indent, move, moveDown, moveUp, outdent, remove, toPayload } from './tree.js';

const tree = [
    { id: 1, label: 'Home', children: [] },
    { id: 2, label: 'About', children: [{ id: 3, label: 'Team', children: [] }, { id: 4, label: 'History', children: [] }] },
    { id: 5, label: 'Contact', children: [] },
];
const labels = (rows) => rows.map((r) => `${'-'.repeat(r.depth)}${r.item.label}`);

describe('menu tree', () => {
    it('flattens and rebuilds without loss', () => {
        const rows = flatten(tree);
        expect(labels(rows)).toEqual(['Home', 'About', '-Team', '-History', 'Contact']);
        expect(build(rows)).toEqual(tree);
    });

    it('moves an item with its sub-items', () => {
        expect(labels(moveUp(flatten(tree), 1))).toEqual(['About', '-Team', '-History', 'Home', 'Contact']);
        expect(labels(moveDown(flatten(tree), 1))).toEqual(['Home', 'Contact', 'About', '-Team', '-History']);
        expect(labels(moveDown(flatten(tree), 2))).toEqual(['Home', 'About', '-History', '-Team', 'Contact']);
        // The last sub-item cannot move down out of its parent.
        expect(labels(moveDown(flatten(tree), 3))).toEqual(labels(flatten(tree)));
    });

    it('indents under the sibling above and outdents after the old siblings', () => {
        expect(labels(indent(flatten(tree), 4))).toEqual(['Home', 'About', '-Team', '-History', '-Contact']);
        expect(labels(indent(flatten(tree), 0))).toEqual(labels(flatten(tree)));
        expect(labels(outdent(flatten(tree), 2))).toEqual(['Home', 'About', '-History', 'Team', 'Contact']);
    });

    it('never goes deeper than allowed', () => {
        const deep = flatten([{ id: 1, label: 'A', children: [{ id: 2, label: 'B', children: [] }] }, { id: 3, label: 'C', children: [] }]);
        expect(labels(indent(deep, 2, 2))).toEqual(['A', '-B', '-C']);
        expect(labels(indent(indent(deep, 2, 2), 2, 2))).toEqual(['A', '-B', '-C']);
    });

    it('drags to a new position, taking a valid depth', () => {
        const rows = flatten(tree);
        // Dropped between Team and History, Contact joins About's sub-items (History stays there).
        expect(labels(move(rows, 4, 3))).toEqual(['Home', 'About', '-Team', '-Contact', '-History']);
        // Dropped at the end, Home stays at the top level.
        expect(labels(move(rows, 0, 4))).toEqual(['About', '-Team', '-History', 'Contact', 'Home']);
    });

    it('saves only the label the editor typed, never the target title shown', () => {
        const payload = toPayload({ id: 9, type: 'page', label: 'About us', own_label: null, target: { entity: 'pages', id: 3, title: 'About us', path: '/about' }, public: true, children: [{ id: 'new-1', type: 'custom_url', own_label: 'Team', url: '/team', children: [] }] });
        expect(payload).toMatchObject({ id: 9, label: '', target: { entity: 'pages', id: 3 } });
        expect(payload.target).not.toHaveProperty('title');
        expect(payload.children[0]).toMatchObject({ id: null, label: 'Team', url: '/team' });
    });

    it('removes an item with its sub-items', () => {
        expect(labels(remove(flatten(tree), 1))).toEqual(['Home', 'Contact']);
    });
});
