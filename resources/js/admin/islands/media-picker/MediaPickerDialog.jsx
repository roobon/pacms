import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { adminHttp, errorMessage } from '../../http.js';

/**
 * Modal media browser (images, or documents such as PDFs). Uses the native <dialog> element: focus is trapped, Esc closes
 * and focus returns to the trigger automatically.
 *
 * @param {{
 *   title: string, endpoint: string, uploadEndpoint: string, canUpload: boolean,
 *   selectedId: number|null, onClose: () => void, onSelect: (media: any) => void, kind?: 'image'|'document',
 *   multiple?: boolean, onSelectMany?: (media: any[]) => void
 * }} props
 */
export function MediaPickerDialog({ title, endpoint, uploadEndpoint, canUpload, selectedId, onClose, onSelect, kind = 'image', multiple = false, onSelectMany }) {
    // Multiple mode (gallery editor): tiles toggle; uploads are added straight away.
    const [many, setMany] = useState(/** @type {any[]} */ ([]));
    const isImage = kind === 'image';
    const noun = isImage ? 'image' : 'document';
    const dialogRef = useRef(/** @type {HTMLDialogElement|null} */ (null));
    const headingId = useId();
    const [query, setQuery] = useState('');
    const [items, setItems] = useState(/** @type {any[]} */ ([]));
    const [page, setPage] = useState(1);
    const [hasMore, setHasMore] = useState(false);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState('');
    const [chosen, setChosen] = useState(/** @type {any|null} */ (null));
    const [upload, setUpload] = useState({ file: /** @type {File|null} */ (null), alt: '', busy: false });

    useEffect(() => {
        const dialog = dialogRef.current;
        dialog?.showModal();
        return () => dialog?.close();
    }, []);

    const load = useCallback(
        async (pageNumber, search) => {
            setLoading(true);
            setError('');
            try {
                const { data } = await adminHttp.get(endpoint, { params: { kind, q: search || undefined, page: pageNumber, visibility: isImage && !multiple ? undefined : 'public' } });
                setItems((previous) => (pageNumber === 1 ? data.data : [...previous, ...data.data]));
                setHasMore(data.meta.current_page < data.meta.last_page);
                setPage(pageNumber);
            } catch (e) {
                setError(errorMessage(e));
            } finally {
                setLoading(false);
            }
        },
        [endpoint, kind, isImage, multiple],
    );

    // Debounced search (and initial load).
    useEffect(() => {
        const timer = setTimeout(() => load(1, query), query ? 300 : 0);
        return () => clearTimeout(timer);
    }, [query, load]);

    async function submitUpload(event) {
        event.preventDefault();
        if (!upload.file) return;
        setUpload((u) => ({ ...u, busy: true }));
        setError('');
        try {
            const form = new FormData();
            form.append('file', upload.file);
            if (isImage) form.append('alt', upload.alt);
            const { data } = await adminHttp.post(uploadEndpoint, form);
            if (multiple) onSelectMany?.([data.data]);
            else onSelect(data.data);
        } catch (e) {
            setError(errorMessage(e));
            setUpload((u) => ({ ...u, busy: false }));
        }
    }

    // Rendered in <body> through a portal: the picker is often placed inside the page form,
    // and its upload <form> must never be nested in another form.
    return createPortal(
        <dialog ref={dialogRef} className="pa-dialog" aria-labelledby={headingId} onClose={onClose} onCancel={onClose}>
            <div className="pa-dialog__header">
                <h2 id={headingId} className="h5 mb-0">
                    {title}
                </h2>
                <button type="button" className="btn-close" aria-label="Close" onClick={onClose} />
            </div>

            <div className="pa-dialog__body">
                {error && (
                    <div className="alert alert-danger pa-alert" role="alert">
                        {error}
                    </div>
                )}

                {canUpload && (
                    <form className="border rounded p-3 mb-3 row g-2 align-items-end" onSubmit={submitUpload}>
                        <div className={isImage ? 'col-md-5' : 'col-md-10'}>
                            <label htmlFor={`${headingId}-file`} className="form-label small">
                                Upload a new {noun}
                            </label>
                            <input
                                id={`${headingId}-file`}
                                type="file"
                                accept={isImage ? '.jpg,.jpeg,.png,.webp,.gif,.avif' : '.pdf,.docx,.xlsx,.pptx,.odt,.ods,.odp,.txt,.csv'}
                                className="form-control form-control-sm"
                                onChange={(e) => setUpload((u) => ({ ...u, file: e.target.files?.[0] ?? null }))}
                            />
                        </div>
                        {isImage && (
                        <div className="col-md-5">
                            <label htmlFor={`${headingId}-alt`} className="form-label small">
                                Alternative text
                            </label>
                            <input
                                id={`${headingId}-alt`}
                                className="form-control form-control-sm"
                                value={upload.alt}
                                maxLength={255}
                                onChange={(e) => setUpload((u) => ({ ...u, alt: e.target.value }))}
                                placeholder="What does the image show?"
                            />
                        </div>
                        )}
                        <div className="col-md-2">
                            <button type="submit" className="btn btn-sm btn-primary w-100" disabled={!upload.file || upload.busy}>
                                {upload.busy ? 'Uploading…' : 'Upload'}
                            </button>
                        </div>
                    </form>
                )}

                <label htmlFor={`${headingId}-search`} className="form-label small">
                    Search the library
                </label>
                <input
                    id={`${headingId}-search`}
                    type="search"
                    className="form-control mb-3"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    placeholder="File name, alt text, caption"
                />

                <div aria-live="polite" aria-busy={loading}>
                    {!loading && items.length === 0 && <p className="text-body-secondary">No {noun}s found.</p>}
                    <ul className="pa-media-grid list-unstyled mb-0">
                        {items.map((item) => {
                            const isChosen = multiple ? many.some((m) => m.id === item.id) : (chosen?.id ?? selectedId) === item.id;
                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        className="pa-media-tile w-100"
                                        aria-pressed={isChosen}
                                        onClick={() => (multiple ? setMany((list) => (list.some((m) => m.id === item.id) ? list.filter((m) => m.id !== item.id) : [...list, item])) : setChosen(item))}
                                        onDoubleClick={() => !multiple && onSelect(item)}
                                    >
                                        <span className="pa-media-tile__thumb">
                                            {item.thumbnail ? <img src={item.thumbnail} alt="" loading="lazy" /> : <i className={`bi ${isImage ? 'bi-image' : 'bi-file-earmark-text'}`} aria-hidden="true" />}
                                        </span>
                                        <span className="pa-media-tile__name">
                                            {item.name}
                                            {isImage && !item.alt && !item.is_decorative && <span className="pa-badge pa-badge--warning d-block mt-1">Needs alt text</span>}
                                        </span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                    {loading && <p className="text-body-secondary mt-3">Loading…</p>}
                    {hasMore && !loading && (
                        <button type="button" className="btn btn-outline-secondary btn-sm mt-3" onClick={() => load(page + 1, query)}>
                            Load more
                        </button>
                    )}
                </div>
            </div>

            <div className="pa-dialog__footer">
                <span className="small text-body-secondary" aria-live="polite">
                    {multiple ? `${many.length} selected` : chosen ? `Selected: ${chosen.name}` : `Select ${isImage ? 'an image' : 'a document'}, then confirm.`}
                </span>
                <div className="d-flex gap-2">
                    <button type="button" className="btn btn-link" onClick={onClose}>
                        Cancel
                    </button>
                    {multiple ? (
                        <button type="button" className="btn btn-primary" disabled={many.length === 0} onClick={() => onSelectMany?.(many)}>
                            Add {many.length || ''} {noun}
                            {many.length === 1 ? '' : 's'}
                        </button>
                    ) : (
                        <button type="button" className="btn btn-primary" disabled={!chosen} onClick={() => chosen && onSelect(chosen)}>
                            Use this {noun}
                        </button>
                    )}
                </div>
            </div>
        </dialog>,
        document.body,
    );
}
