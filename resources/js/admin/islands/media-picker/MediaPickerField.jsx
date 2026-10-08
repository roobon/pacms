import { useState } from 'react';
import { MediaPickerDialog } from './MediaPickerDialog.jsx';

/**
 * @typedef {Object} PickedMedia
 * @property {number} id
 * @property {string|null} thumbnail
 * @property {string} alt
 * @property {string} name
 */

/**
 * @param {{
 *   name: string, label: string, endpoint: string, uploadEndpoint: string,
 *   canUpload: boolean, disabled: boolean, initial: PickedMedia|null, kind?: 'image'|'document'
 * }} props
 */
export function MediaPickerField({ name, label, endpoint, uploadEndpoint, canUpload, disabled, initial, kind = 'image' }) {
    const [value, setValue] = useState(/** @type {PickedMedia|null} */ (initial));
    const [open, setOpen] = useState(false);
    const isImage = kind === 'image';
    const noun = isImage ? 'image' : 'document';

    return (
        <>
            <input type="hidden" name={name} value={value?.id ?? ''} />
            {value && isImage && <img src={value.thumbnail ?? ''} alt={value.alt} className="pa-media-field__preview" />}
            {value && !isImage && (
                <p className="mb-0">
                    <i className="bi bi-file-earmark-text me-1" aria-hidden="true" />
                    {value.name}
                </p>
            )}
            {!value && <p className="text-body-secondary small mb-0">No {noun} selected.</p>}
            <div className="d-flex flex-wrap gap-2">
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setOpen(true)} disabled={disabled}>
                    <i className={`bi ${isImage ? 'bi-images' : 'bi-file-earmark-arrow-up'} me-1`} aria-hidden="true" />
                    {value ? `Change ${noun}` : `Choose ${noun}`}
                    <span className="visually-hidden"> for {label}</span>
                </button>
                {value && (
                    <button type="button" className="btn btn-sm btn-link text-danger" onClick={() => setValue(null)} disabled={disabled}>
                        Remove<span className="visually-hidden"> {label}</span>
                    </button>
                )}
            </div>
            {isImage && value?.name && <span className="small text-body-secondary w-100">{value.name}</span>}
            {open && (
                <MediaPickerDialog
                    title={`Choose ${label.toLowerCase()}`}
                    kind={kind}
                    endpoint={endpoint}
                    uploadEndpoint={uploadEndpoint}
                    canUpload={canUpload}
                    selectedId={value?.id ?? null}
                    onClose={() => setOpen(false)}
                    onSelect={(media) => {
                        setValue({ id: media.id, thumbnail: media.thumbnail, alt: media.alt ?? '', name: media.name });
                        setOpen(false);
                    }}
                />
            )}
        </>
    );
}
