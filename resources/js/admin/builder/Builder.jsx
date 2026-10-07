import { useEffect, useRef, useState } from 'react';
import { adminHttp } from '../http.js';
import FieldBuilder from './FieldBuilder.jsx';
import Inspector from './Inspector.jsx';
import PreviewFrame from './PreviewFrame.jsx';
import StructurePanel from './StructurePanel.jsx';
import { useBuilder } from './store.js';
import { countNodes } from './tree.js';

const AUTOSAVE_MS = 60_000;

/**
 * Block Builder (CMS-ARCHITECTURE.md §23.4): structure · live preview · inspector (plus
 * the field builder for custom block types). The tree (and fields) are written into the
 * owner form's hidden inputs, so one "Save" stores everything together.
 *
 * @param {{input: HTMLInputElement, fieldsInput?: HTMLInputElement|null, owner?: {type: string, id: number}|null, autosave?: {saved_at: string, blocks: Array<Object>}|null}} props
 */
export default function Builder({ input, fieldsInput = null, owner = null, autosave = null }) {
    const nodes = useBuilder((state) => state.nodes);
    const fields = useBuilder((state) => state.fields);
    const dirty = useBuilder((state) => state.dirty);
    const context = useBuilder((state) => state.context);
    const notice = useBuilder((state) => state.notice);
    const field = useRef(input);
    const fieldsField = useRef(fieldsInput);
    const [pending, setPending] = useState(autosave);
    const savedAt = useAutosave(owner, dirty);

    // Keep the form fields in sync with the tree.
    useEffect(() => {
        field.current.value = JSON.stringify(nodes);
    }, [nodes]);
    useEffect(() => {
        if (fieldsField.current && fields) fieldsField.current.value = JSON.stringify(fields);
    }, [fields]);

    useShortcuts();

    // Warn before leaving with unsaved changes.
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
            {pending && (
                <div className="alert alert-info pa-alert d-flex flex-wrap align-items-center gap-2 m-2 mb-0" role="status">
                    <i className="bi bi-clock-history" aria-hidden="true" />
                    <span>You have unsaved block changes from {new Date(pending.saved_at).toLocaleString()}.</span>
                    <button
                        type="button"
                        className="btn btn-sm btn-primary ms-auto"
                        onClick={() => {
                            useBuilder.getState().commit(pending.blocks, { notice: 'Unsaved changes restored. Save to keep them.' });
                            setPending(null);
                        }}
                    >
                        Restore them
                    </button>
                    <button type="button" className="btn btn-sm btn-link" onClick={() => setPending(null)}>
                        Discard
                    </button>
                </div>
            )}
            <div className="pa-builder__status small text-body-secondary">
                <span>
                    {countNodes(nodes)} blocks{dirty ? ' · unsaved changes — click “Save”' : ''}
                    {savedAt && dirty ? ` · autosaved ${savedAt.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}` : ''}
                </span>
                <span aria-live="polite" className="pa-builder__notice">
                    {notice}
                </span>
            </div>
            {context === 'structure' && <FieldBuilder />}
            <div className="pa-builder__grid">
                <StructurePanel />
                <PreviewFrame />
                <Inspector />
            </div>
        </div>
    );
}

/**
 * Every minute, store the unsaved tree as the user's autosave revision (only valid trees
 * are accepted; the next real save supersedes it).
 */
function useAutosave(owner, dirty) {
    const [savedAt, setSavedAt] = useState(null);
    const lastSent = useRef(null);

    useEffect(() => {
        if (!owner || !dirty) return undefined;
        const timer = setInterval(async () => {
            const { nodes, fields, definitions, readonly } = useBuilder.getState();
            if (readonly || nodes === lastSent.current) return;
            try {
                await adminHttp.post(definitions.endpoints.autosave, { owner: owner.type, id: owner.id, blocks: nodes, fields: fields ?? undefined });
                lastSent.current = nodes;
                setSavedAt(new Date());
            } catch {
                // Invalid tree or network error: the preview already shows what to fix.
            }
        }, AUTOSAVE_MS);
        return () => clearInterval(timer);
    }, [owner, dirty]);

    return savedAt;
}

/**
 * Ctrl/⌘+Z undo, Ctrl/⌘+Shift+Z or Ctrl+Y redo, Ctrl/⌘+C copy, Ctrl/⌘+V paste,
 * Ctrl/⌘+D duplicate, Delete removes. Ignored while typing in a form field.
 */
function useShortcuts() {
    useEffect(() => {
        function onKeyDown(event) {
            const target = event.target;
            if (!target.closest?.('.pa-builder') || target.closest('input, textarea, select, [contenteditable="true"], dialog')) return;
            const { readonly, selected, undo, redo, copy, paste, duplicate, remove } = useBuilder.getState();
            if (readonly) return;
            const mod = event.ctrlKey || event.metaKey;
            const key = event.key.toLowerCase();

            const actions = {
                z: mod && !event.shiftKey ? undo : null,
                y: mod ? redo : null,
                c: mod && selected ? () => copy(selected) : null,
                v: mod ? paste : null,
                d: mod && selected ? () => duplicate(selected) : null,
                delete: selected && !mod ? () => window.confirm('Delete the selected block and everything inside it?') && remove(selected) : null,
            };
            if (mod && event.shiftKey && key === 'z') actions.z = redo;

            const action = actions[key];
            if (action) {
                event.preventDefault();
                action();
            }
        }
        document.addEventListener('keydown', onKeyDown);
        return () => document.removeEventListener('keydown', onKeyDown);
    }, []);
}
