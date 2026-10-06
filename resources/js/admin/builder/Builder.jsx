import { useEffect, useRef } from 'react';
import Inspector from './Inspector.jsx';
import PreviewFrame from './PreviewFrame.jsx';
import StructurePanel from './StructurePanel.jsx';
import { useBuilder } from './store.js';
import { countNodes } from './tree.js';

/**
 * Block Builder (CMS-ARCHITECTURE.md §23.4): structure · live preview · inspector.
 * The tree is written into the page form's hidden "blocks" input, so one "Save draft"
 * stores fields and blocks together (one revision, one lock version).
 *
 * @param {{input: HTMLInputElement}} props
 */
export default function Builder({ input }) {
    const nodes = useBuilder((state) => state.nodes);
    const dirty = useBuilder((state) => state.dirty);
    const field = useRef(input);

    // Keep the form field in sync with the tree.
    useEffect(() => {
        field.current.value = JSON.stringify(nodes);
    }, [nodes]);

    // Warn before leaving with unsaved block changes.
    useEffect(() => {
        if (!dirty) return undefined;
        const form = field.current.form;
        let submitting = false;
        const onSubmit = () => {
            submitting = true;
        };
        const onBeforeUnload = (event) => {
            if (!submitting) event.preventDefault();
        };
        form?.addEventListener('submit', onSubmit);
        window.addEventListener('beforeunload', onBeforeUnload);
        return () => {
            form?.removeEventListener('submit', onSubmit);
            window.removeEventListener('beforeunload', onBeforeUnload);
        };
    }, [dirty]);

    return (
        <div className="pa-builder">
            <div className="pa-builder__status small text-body-secondary" aria-live="polite">
                {countNodes(nodes)} blocks{dirty ? ' · unsaved changes — click “Save draft”' : ''}
            </div>
            <div className="pa-builder__grid">
                <StructurePanel />
                <PreviewFrame />
                <Inspector />
            </div>
        </div>
    );
}
