import { useState } from 'react';
import { MediaPickerDialog } from '../media-picker/MediaPickerDialog.jsx';

/**
 * Documents listed on a content item (reports, briefs…): chosen from the Media Library
 * (or uploaded there), each with an optional label, in a chosen order.
 *
 * @param {{
 *   name?: string, endpoint: string, uploadEndpoint: string, canUpload: boolean, disabled?: boolean, max?: number,
 *   rows?: Array<{media_id: number, label: string|null, name: string, size?: string}>,
 *   errors?: Record<string, string>
 * }} props
 */
export function DocumentsField({ name = 'documents', endpoint, uploadEndpoint, canUpload, disabled = false, max = 30, rows = [], errors = {} }) {
    const [items, setItems] = useState(() => rows.map((row) => ({ ...row, label: row.label ?? '' })));
    const [open, setOpen] = useState(false);
    const [announcement, setAnnouncement] = useState('');

    function move(index, direction) {
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        setItems((previous) => {
            const next = [...previous];
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
        setAnnouncement(`${items[index].name} moved to position ${target + 1}.`);
    }

    return (
        <div className="mb-3">
            {items.length === 0 && <p className="small text-body-secondary">No documents yet.</p>}
            <ol className="pa-list-editor list-unstyled mb-2">
                {items.map((item, index) => (
                    <li key={`${item.media_id}-${index}`} className="pa-list-editor__row">
                        <span className="pa-list-editor__number" aria-hidden="true">
                            {index + 1}
                        </span>
                        <div className="pa-list-editor__fields">
                            <p className="mb-1">
                                <i className="bi bi-file-earmark-text me-1" aria-hidden="true" />
                                {item.name}
                                {item.size && <span className="small text-body-secondary"> · {item.size}</span>}
                            </p>
                            <input type="hidden" name={`${name}[${index}][media_id]`} value={item.media_id} />
                            <label htmlFor={`document-label-${index}`} className="form-label small mb-1">
                                Label shown to visitors (optional)
                            </label>
                            <input
                                id={`document-label-${index}`}
                                name={`${name}[${index}][label]`}
                                className={`form-control form-control-sm${errors[`${name}.${index}.label`] ? ' is-invalid' : ''}`}
                                value={item.label}
                                maxLength={255}
                                disabled={disabled}
                                placeholder={item.name}
                                onChange={(event) => setItems((previous) => previous.map((row, i) => (i === index ? { ...row, label: event.target.value } : row)))}
                            />
                            {errors[`${name}.${index}.media_id`] && <div className="invalid-feedback d-block">{errors[`${name}.${index}.media_id`]}</div>}
                        </div>
                        <div className="pa-list-editor__actions">
                            <button type="button" className="btn btn-sm btn-link" onClick={() => move(index, -1)} disabled={disabled || index === 0}>
                                <i className="bi bi-arrow-up" aria-hidden="true" />
                                <span className="visually-hidden">Move {item.name} up</span>
                            </button>
                            <button type="button" className="btn btn-sm btn-link" onClick={() => move(index, 1)} disabled={disabled || index === items.length - 1}>
                                <i className="bi bi-arrow-down" aria-hidden="true" />
                                <span className="visually-hidden">Move {item.name} down</span>
                            </button>
                            <button
                                type="button"
                                className="btn btn-sm btn-link text-danger"
                                disabled={disabled}
                                onClick={() => {
                                    setItems((previous) => previous.filter((_, i) => i !== index));
                                    setAnnouncement(`${item.name} removed.`);
                                }}
                            >
                                <i className="bi bi-x-lg" aria-hidden="true" />
                                <span className="visually-hidden">Remove {item.name}</span>
                            </button>
                        </div>
                    </li>
                ))}
            </ol>
            {items.length < max && (
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setOpen(true)} disabled={disabled}>
                    <i className="bi bi-file-earmark-plus me-1" aria-hidden="true" />
                    Add document
                </button>
            )}
            {errors[name] && <div className="invalid-feedback d-block">{errors[name]}</div>}
            <span className="visually-hidden" role="status" aria-live="polite">
                {announcement}
            </span>
            {open && (
                <MediaPickerDialog
                    title="Choose a document"
                    kind="document"
                    endpoint={endpoint}
                    uploadEndpoint={uploadEndpoint}
                    canUpload={canUpload}
                    selectedId={null}
                    onClose={() => setOpen(false)}
                    onSelect={(media) => {
                        setItems((previous) => [...previous, { media_id: media.id, label: '', name: media.name, size: media.size }]);
                        setAnnouncement(`${media.name} added.`);
                        setOpen(false);
                    }}
                />
            )}
        </div>
    );
}
