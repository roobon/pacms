import { useEffect, useState } from 'react';
import BlockRenderer from '../../blocks/BlockRenderer.jsx';
import BlockStyles from '../../blocks/BlockStyles.jsx';
import { PreviewContext } from '../../blocks/common/frame.js';
import { useInitialData } from '../contexts/InitialDataContext.jsx';
import NotFoundPage from './NotFoundPage.jsx';

/**
 * Live preview inside the Block Builder's iframe (CMS-ARCHITECTURE.md §23.4). Receives
 * server-validated, resolved trees from the parent admin window (same origin only) and
 * renders them with the same components as the public site.
 */
export default function BuilderPreviewPage() {
    const { route } = useInitialData();
    const [blocks, setBlocks] = useState([]);
    const [selected, setSelected] = useState(null);

    useEffect(() => {
        if (!route?.builder) return undefined;

        function onMessage(event) {
            if (event.origin !== window.location.origin || event.source !== window.parent) return;
            const data = event.data ?? {};
            if (data.type === 'pacms:preview' && Array.isArray(data.blocks)) setBlocks(data.blocks);
            if (data.type === 'pacms:select') setSelected(typeof data.uuid === 'string' ? data.uuid : null);
        }

        window.addEventListener('message', onMessage);
        document.body.classList.add('pa-builder-preview');
        window.parent.postMessage({ type: 'pacms:preview-ready' }, window.location.origin);

        return () => window.removeEventListener('message', onMessage);
    }, [route?.builder]);

    // Highlight the selected block and scroll it into view.
    useEffect(() => {
        document.querySelectorAll('[data-block-uuid].is-selected').forEach((el) => el.classList.remove('is-selected'));
        if (!selected) return;
        const element = document.querySelector(`[data-block-uuid="${CSS.escape(selected)}"]`);
        element?.classList.add('is-selected');
        element?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }, [selected, blocks]);

    if (!route?.builder) return <NotFoundPage />;

    function onClick(event) {
        const target = event.target.closest('[data-block-uuid]');
        if (!target) return;
        event.preventDefault();
        window.parent.postMessage({ type: 'pacms:select', uuid: target.getAttribute('data-block-uuid') }, window.location.origin);
    }

    return (
        <PreviewContext value={true}>
            <div onClickCapture={onClick}>
                <BlockStyles nodes={blocks} />
                {blocks.length === 0 ? (
                    <div className="container py-5 text-center text-body-secondary">Add blocks to see the preview.</div>
                ) : (
                    <BlockRenderer nodes={blocks} />
                )}
            </div>
        </PreviewContext>
    );
}
