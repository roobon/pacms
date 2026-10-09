import { createContext } from 'react';

/** Whether the visitor is signed in (menus hide guest-only or member-only items). */
export const VisitorContext = createContext({ signedIn: false });

/** Items the visitor may see: "guests" items for visitors, "members" items once signed in. */
export function visibleItems(items, signedIn) {
    return (items ?? [])
        .filter((item) => item.visibility !== (signedIn ? 'guests' : 'members'))
        .map((item) => ({ ...item, children: visibleItems(item.children, signedIn) }))
        .filter((item) => item.url || item.children.length > 0);
}
