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
 *   canUpload: boolean, disabled: boolean, initial: PickedMedia|null
 * }} props
 */
export function MediaPickerField({ name, label, endpoint, uploadEndpoint, canUpload, disabled, initial }) {
    const [value, setValue] = useState(/** @type {PickedMedia|null} */ (initial));
    const [open, setOpen] = useState(false);

    return (
        <>
            <input type="hidden" name={name} value={value?.id ?? ''} />
            {value ? (
                <img src={value.thumbnail ?? ''} alt={value.alt} className="pa-media-field__preview" />
            ) : (
                <p className="text-body-secondary small mb-0">No image selected.</p>
            )}
            <div className="d-flex flex-wrap gap-2">
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setOpen(true)} disabled={disabled}>
                    <i className="bi bi-images me-1" aria-hidden="true" />
                    {value ? 'Change image' : 'Choose image'}
                    <span className="visually-hidden"> for {label}</span>
                </button>
                {value && (
                    <button type="button" className="btn btn-sm btn-link text-danger" onClick={() => setValue(null)} disabled={disabled}>
                        Remove<span className="visually-hidden"> {label}</span>
                    </button>
                )}
            </div>
            {value?.name && <span className="small text-body-secondary w-100">{value.name}</span>}
            {open && (
                <MediaPickerDialog
                    title={`Choose ${label.toLowerCase()}`}
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
