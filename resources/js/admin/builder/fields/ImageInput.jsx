import { useState } from 'react';
import { MediaPickerDialog } from '../../islands/media-picker/MediaPickerDialog.jsx';
import { useBuilder } from '../store.js';

/**
 * Image or document field: stores {"$media": id}; keeps a small preview cache for the
 * inspector. The resolved file (srcset, alt, URL…) comes from the server for the live preview.
 *
 * @param {{value: Object|null, onChange: (value: Object|null) => void, label: string, disabled?: boolean, kind?: 'image'|'document'}} props
 */
export default function ImageInput({ value, onChange, label, disabled = false, kind = 'image' }) {
    const noun = kind === 'image' ? 'image' : 'document';
    const [open, setOpen] = useState(false);
    const [preview, setPreview] = useState(null);
    const endpoints = useBuilder((state) => state.definitions?.endpoints);
    const canUpload = useBuilder((state) => state.definitions?.permissions?.media_upload);
    const mediaId = value?.$media ?? null;

    return (
        <div className="pa-media-field">
            {mediaId ? (
                preview?.id === mediaId && preview.thumbnail ? (
                    <img src={preview.thumbnail} alt={preview.alt ?? ''} className="pa-media-field__preview" />
                ) : preview?.id === mediaId && preview.name ? (
                    <span className="small">
                        <i className="bi bi-file-earmark-text me-1" aria-hidden="true" />
                        {preview.name}
                    </span>
                ) : (
                    <span className="small">{kind === 'image' ? 'Image' : 'Document'} #{mediaId}</span>
                )
            ) : (
                <span className="small text-body-secondary">No {noun} selected.</span>
            )}
            <div className="d-flex gap-2">
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setOpen(true)} disabled={disabled}>
                    {mediaId ? 'Change' : 'Choose'}
                    <span className="visually-hidden"> {label}</span>
                </button>
                {mediaId && (
                    <button type="button" className="btn btn-sm btn-link text-danger" onClick={() => onChange(null)} disabled={disabled}>
                        Remove<span className="visually-hidden"> {label}</span>
                    </button>
                )}
            </div>
            {open && (
                <MediaPickerDialog
                    title={`Choose ${label.toLowerCase()}`}
                    kind={kind}
                    endpoint={endpoints.media}
                    uploadEndpoint={endpoints.mediaUpload}
                    canUpload={Boolean(canUpload)}
                    selectedId={mediaId}
                    onClose={() => setOpen(false)}
                    onSelect={(media) => {
                        setPreview(media);
                        onChange({ $media: media.id });
                        setOpen(false);
                    }}
                />
            )}
        </div>
    );
}
