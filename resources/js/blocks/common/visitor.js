import { createContext } from 'react';

/** Whether the visitor is signed in (menus hide guest-only or member-only items). */
export const VisitorContext = createContext({ signedIn: false });

/** Items the visitor may see: "guests" items for visitors, "members" items once signed in. */
export function visibleItems(items, signedIn) {
    return (items ?? [])
        .filter((item) => item.visibility !== (signedIn ? 'guests' : 'members'))
        .map((item) => ({ ...item, children: visibleItems(item.children, signedIn) }))
        .filter((item) => item.url || item.children.length > 0 || hasPanel(item));
}

/** A top-level item that opens a mega panel (blocks) instead of a list of sub-items. */
export function hasPanel(item) {
    return Array.isArray(item.panel) && item.panel.length > 0;
}
