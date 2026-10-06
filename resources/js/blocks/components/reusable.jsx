import { frameProps } from '../common/frame.js';

/**
 * A placed Global Block (CMS-ARCHITECTURE.md §12). The server inlines the global block's
 * published tree as children; they are locked in the builder preview (edit the global
 * block itself to change them).
 */
export function GlobalRefBlock({ node, preview, children }) {
    if (!children) {
        return preview ? (
            <div {...frameProps(node, preview, 'pa-placeholder')} role="note">
                <i className="bi bi-globe2" aria-hidden="true" /> Choose a published global block.
            </div>
        ) : null;
    }

    return <div {...frameProps(node, preview, 'pa-global-block')}>{children}</div>;
}

/**
 * An instance of a custom block type (CMS-ARCHITECTURE.md §11.3). The server expands the
 * type's structure with this block's values into ordinary blocks, rendered as children.
 */
export function CustomBlock({ node, preview, children }) {
    return <div {...frameProps(node, preview, 'pa-custom-block')}>{children}</div>;
}
