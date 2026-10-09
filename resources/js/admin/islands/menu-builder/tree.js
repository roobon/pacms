/**
 * Menu tree operations for the Menu Builder. The tree is edited as a flat list in display
 * order, each row with a depth (0 = top level); a row's sub-items are the rows after it
 * with a greater depth. Every operation returns a new list and keeps it valid: depths never
 * jump by more than one, and never exceed maxDepth - 1.
 */

/**
 * @typedef {{ key: string, depth: number, item: Object }} Row
 */

/** Nested items (with `children`) → flat rows. */
export function flatten(items, depth = 0, rows = []) {
    for (const item of items ?? []) {
        const { children, ...rest } = item;
        rows.push({ key: String(item.id ?? item.key), depth, item: rest });
        flatten(children, depth + 1, rows);
    }
    return rows;
}

/** Flat rows → nested items, as the server expects them. */
export function build(rows) {
    const root = [];
    const stack = [{ depth: -1, children: root }];
    for (const row of rows) {
        while (stack[stack.length - 1].depth >= row.depth) stack.pop();
        const node = { ...row.item, children: [] };
        stack[stack.length - 1].children.push(node);
        stack.push({ depth: row.depth, children: node.children });
    }
    return root;
}

/** Index after the last sub-item of the row at index. */
export function subtreeEnd(rows, index) {
    let end = index + 1;
    while (end < rows.length && rows[end].depth > rows[index].depth) end++;
    return end;
}

/** Depths may rise by one at most from one row to the next, and stay below maxDepth. */
function normalise(rows, maxDepth) {
    let previous = -1;
    return rows.map((row) => {
        const depth = Math.max(0, Math.min(row.depth, previous + 1, maxDepth - 1));
        previous = depth;
        return depth === row.depth ? row : { ...row, depth };
    });
}

/**
 * Move the row at `from` (with its sub-items) so it lands at position `to` of the list
 * without it. It keeps its depth where it can: at least the depth of the row below (so it
 * never takes that row as a sub-item), at most one deeper than the row above.
 */
export function move(rows, from, to, maxDepth = 4) {
    const end = subtreeEnd(rows, from);
    const block = rows.slice(from, end);
    const rest = [...rows.slice(0, from), ...rows.slice(end)];
    const at = Math.max(0, Math.min(to, rest.length));
    const before = rest[at - 1];
    const after = rest[at];
    const highest = before ? before.depth + 1 : 0;
    const depth = Math.min(Math.max(block[0].depth, after?.depth ?? 0), highest);
    const shift = depth - block[0].depth;
    const moved = block.map((row) => ({ ...row, depth: row.depth + shift }));
    return normalise([...rest.slice(0, at), ...moved, ...rest.slice(at)], maxDepth);
}

/** Swap with the previous sibling (same parent). */
export function moveUp(rows, index, maxDepth = 4) {
    const depth = rows[index].depth;
    for (let i = index - 1; i >= 0; i--) {
        if (rows[i].depth < depth) return rows;
        if (rows[i].depth === depth) return move(rows, index, i, maxDepth);
    }
    return rows;
}

/** Swap with the next sibling (same parent). */
export function moveDown(rows, index, maxDepth = 4) {
    const depth = rows[index].depth;
    const next = subtreeEnd(rows, index);
    if (next >= rows.length || rows[next].depth !== depth) return rows;
    const afterNext = subtreeEnd(rows, next);
    // Position in the list without the moved block.
    return move(rows, index, afterNext - (next - index), maxDepth);
}

/** Make the row a sub-item of the sibling above it. */
export function indent(rows, index, maxDepth = 4) {
    const row = rows[index];
    const above = rows[index - 1];
    if (!above || above.depth < row.depth) return rows;
    const end = subtreeEnd(rows, index);
    const deepest = Math.max(...rows.slice(index, end).map((r) => r.depth));
    if (deepest + 1 > maxDepth - 1) return rows;
    return rows.map((r, i) => (i >= index && i < end ? { ...r, depth: r.depth + 1 } : r));
}

/** Move the row one level up (after its parent's other sub-items keep their place). */
export function outdent(rows, index, maxDepth = 4) {
    if (rows[index].depth === 0) return rows;
    const end = subtreeEnd(rows, index);
    const block = rows.slice(index, end).map((r) => ({ ...r, depth: r.depth - 1 }));
    // Later siblings stay under the old parent: the block moves after them.
    let after = end;
    while (after < rows.length && rows[after].depth >= rows[index].depth) after++;
    return normalise([...rows.slice(0, index), ...rows.slice(end, after), ...block, ...rows.slice(after)], maxDepth);
}

/** Remove the row and its sub-items. */
export function remove(rows, index) {
    return [...rows.slice(0, index), ...rows.slice(subtreeEnd(rows, index))];
}

/**
 * An item as the server stores it: no display-only fields. The label is only the one the
 * editor typed; without it, the website shows the target's current title.
 */
export function toPayload(node) {
    return {
        id: typeof node.id === 'number' ? node.id : null,
        type: node.type,
        label: node.own_label ?? '',
        target: node.target ? { entity: node.target.entity, id: node.target.id } : null,
        url: node.url ?? null,
        new_tab: Boolean(node.new_tab),
        icon: node.icon ?? '',
        class: node.class ?? '',
        visibility: node.visibility ?? 'everyone',
        children: (node.children ?? []).map(toPayload),
    };
}
