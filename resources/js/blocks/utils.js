/** True when the blocks (at any depth) contain an H1 heading. */
export function hasHeadingOne(nodes) {
    return (nodes ?? []).some((node) => (node.type === 'heading' && String(node.content?.level) === '1') || hasHeadingOne(node.children));
}
