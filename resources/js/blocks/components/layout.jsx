import { containerClass, frameProps } from '../common/frame.js';

export function SectionBlock({ node, preview, children }) {
    const inner = containerClass(node);

    return (
        <section {...frameProps(node, preview, 'pa-section')} aria-label={node.content.aria_label || undefined}>
            {inner ? <div className={inner}>{children}</div> : children}
        </section>
    );
}

export function ContainerBlock({ node, preview, children }) {
    return <div {...frameProps(node, preview, 'pa-container-block')}>{children}</div>;
}

/** 12-column CSS grid; spans per device come from layout.columns (compiled CSS). */
export function ColumnsBlock({ node, preview, children }) {
    return <div {...frameProps(node, preview, 'pa-columns')}>{children}</div>;
}

export function ColumnBlock({ node, preview, children }) {
    return <div {...frameProps(node, preview, 'pa-column')}>{children}</div>;
}
