import { useCallback, useEffect, useId, useRef, useState } from 'react';
import { adminHttp, errorMessage } from '../../http.js';

/**
 * Modal image browser. Uses the native <dialog> element: focus is trapped, Esc closes
 * and focus returns to the trigger automatically.
 *
 * @param {{
 *   title: string, endpoint: string, uploadEndpoint: string, canUpload: boolean,
 *   selectedId: number|null, onClose: () => void, onSelect: (media: any) => void
 * }} props
 */
export function MediaPickerDialog({ title, endpoint, uploadEndpoint, canUpload, selectedId, onClose, onSelect }) {
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
                const { data } = await adminHttp.get(endpoint, { params: { kind: 'image', q: search || undefined, page: pageNumber } });
                setItems((previous) => (pageNumber === 1 ? data.data : [...previous, ...data.data]));
                setHasMore(data.meta.current_page < data.meta.last_page);
                setPage(pageNumber);
            } catch (e) {
                setError(errorMessage(e));
            } finally {
                setLoading(false);
            }
        },
        [endpoint],
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
            form.append('alt', upload.alt);
            const { data } = await adminHttp.post(uploadEndpoint, form);
            onSelect(data.data);
        } catch (e) {
            setError(errorMessage(e));
            setUpload((u) => ({ ...u, busy: false }));
        }
    }

    return (
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
                        <div className="col-md-5">
                            <label htmlFor={`${headingId}-file`} className="form-label small">
                                Upload a new image
                            </label>
                            <input
                                id={`${headingId}-file`}
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,.gif,.avif"
                                className="form-control form-control-sm"
                                onChange={(e) => setUpload((u) => ({ ...u, file: e.target.files?.[0] ?? null }))}
                            />
                        </div>
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
                    {!loading && items.length === 0 && <p className="text-body-secondary">No images found.</p>}
                    <ul className="pa-media-grid list-unstyled mb-0">
                        {items.map((item) => {
                            const isChosen = (chosen?.id ?? selectedId) === item.id;
                            return (
                                <li key={item.id}>
                                    <button
                                        type="button"
                                        className="pa-media-tile w-100"
                                        aria-pressed={isChosen}
                                        onClick={() => setChosen(item)}
                                        onDoubleClick={() => onSelect(item)}
                                    >
                                        <span className="pa-media-tile__thumb">
                                            {item.thumbnail ? <img src={item.thumbnail} alt="" loading="lazy" /> : <i className="bi bi-image" aria-hidden="true" />}
                                        </span>
                                        <span className="pa-media-tile__name">
                                            {item.name}
                                            {!item.alt && !item.is_decorative && <span className="pa-badge pa-badge--warning d-block mt-1">Needs alt text</span>}
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
                <span className="small text-body-secondary">{chosen ? `Selected: ${chosen.name}` : 'Select an image, then confirm.'}</span>
                <div className="d-flex gap-2">
                    <button type="button" className="btn btn-link" onClick={onClose}>
                        Cancel
                    </button>
                    <button type="button" className="btn btn-primary" disabled={!chosen} onClick={() => chosen && onSelect(chosen)}>
                        Use this image
                    </button>
                </div>
            </div>
        </dialog>
    );
}
