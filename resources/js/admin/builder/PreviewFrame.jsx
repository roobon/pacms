import { useEffect, useRef, useState } from 'react';
import { adminHttp } from '../http.js';
import { useBuilder } from './store.js';

const WIDTHS = { desktop: '100%', tablet: '820px', mobile: '390px' };

/**
 * Live preview: the public SPA in an iframe renders the tree with the same components
 * visitors see. The unsaved tree is validated and resolved by the server first
 * (POST /admin/api/blocks/resolve), then sent to the iframe with postMessage.
 */
export default function PreviewFrame() {
    const iframe = useRef(null);
    const nodes = useBuilder((state) => state.nodes);
    const selected = useBuilder((state) => state.selected);
    const device = useBuilder((state) => state.device);
    const endpoints = useBuilder((state) => state.definitions?.endpoints);
    const { select, setDevice, setErrors } = useBuilder.getState();
    const [ready, setReady] = useState(false);
    const [status, setStatus] = useState('idle');
    const resolved = useRef([]);

    const post = (message) => iframe.current?.contentWindow?.postMessage(message, window.location.origin);

    // Messages from the preview: ready, click-to-select.
    useEffect(() => {
        function onMessage(event) {
            if (event.origin !== window.location.origin || event.source !== iframe.current?.contentWindow) return;
            if (event.data?.type === 'pacms:preview-ready') setReady(true);
            if (event.data?.type === 'pacms:select' && typeof event.data.uuid === 'string') select(event.data.uuid);
        }
        window.addEventListener('message', onMessage);
        return () => window.removeEventListener('message', onMessage);
    }, [select]);

    // Resolve the tree on the server (debounced) and push it to the preview.
    useEffect(() => {
        if (!ready || !endpoints) return undefined;
        const controller = new AbortController();
        const timer = setTimeout(async () => {
            setStatus('updating');
            try {
                const { data } = await adminHttp.post(endpoints.resolve, { blocks: nodes }, { signal: controller.signal });
                resolved.current = data.blocks;
                post({ type: 'pacms:preview', blocks: data.blocks });
                setErrors({});
                setStatus('idle');
            } catch (error) {
                if (controller.signal.aborted) return;
                if (error?.response?.status === 422) {
                    setErrors(error.response.data.errors ?? {});
                    setStatus('invalid');
                } else {
                    setStatus('error');
                }
            }
        }, 400);
        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [nodes, ready, endpoints, setErrors]);

    useEffect(() => {
        if (ready) post({ type: 'pacms:select', uuid: selected });
    }, [selected, ready]);

    return (
        <section className="pa-preview" aria-label="Live preview">
            <div className="pa-preview__toolbar">
                <div className="btn-group btn-group-sm" role="radiogroup" aria-label="Preview device">
                    {[
                        ['desktop', 'bi-display', 'Desktop'],
                        ['tablet', 'bi-tablet', 'Tablet'],
                        ['mobile', 'bi-phone', 'Phone'],
                    ].map(([key, icon, label]) => (
                        <button key={key} type="button" role="radio" aria-checked={device === key} className={`btn ${device === key ? 'btn-secondary' : 'btn-outline-secondary'}`} onClick={() => setDevice(key)}>
                            <i className={`bi ${icon}`} aria-hidden="true" />
                            <span className="visually-hidden">{label}</span>
                        </button>
                    ))}
                </div>
                <span className="small text-body-secondary" role="status">
                    {status === 'updating' && 'Updating preview…'}
                    {status === 'invalid' && <span className="text-danger">Fix the highlighted errors to update the preview.</span>}
                    {status === 'error' && <span className="text-danger">Preview unavailable — check your connection.</span>}
                </span>
            </div>
            <div className="pa-preview__stage">
                <iframe ref={iframe} src={endpoints?.previewFrame} title="Live preview of the page" style={{ width: WIDTHS[device] }} className="pa-preview__frame" />
            </div>
        </section>
    );
}
