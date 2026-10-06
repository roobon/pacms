import { Component } from 'react';
import { components } from './registry.js';
import { useIsPreview } from './common/frame.js';

/**
 * Recursive block renderer (CMS-ARCHITECTURE.md §5.4):
 * BlockRenderer → block type → component → children → BlockRenderer.
 *
 * @param {{nodes: Array<Object>}} props
 */
export default function BlockRenderer({ nodes }) {
    return (nodes ?? []).map((node) => <BlockNode key={node.uuid} node={node} />);
}

function BlockNode({ node }) {
    const preview = useIsPreview();
    const Block = components[node.type];

    if (!Block) {
        return preview ? (
            <div className="alert alert-warning small" role="note">
                Unknown block type “{node.type}”.
            </div>
        ) : null;
    }

    return (
        <BlockBoundary type={node.type} preview={preview}>
            <Block node={node} preview={preview}>
                {node.children?.length ? <BlockRenderer nodes={node.children} /> : null}
            </Block>
        </BlockBoundary>
    );
}

/** One broken block never blanks the page. */
class BlockBoundary extends Component {
    constructor(props) {
        super(props);
        this.state = { failed: false };
    }

    static getDerivedStateFromError() {
        return { failed: true };
    }

    componentDidCatch(error) {
        if (import.meta.env.DEV) console.error(error);
    }

    render() {
        if (!this.state.failed) return this.props.children;
        return this.props.preview ? (
            <div className="alert alert-danger small" role="note">
                The “{this.props.type}” block could not be displayed.
            </div>
        ) : null;
    }
}
