import { useId, useRef, useState } from 'react';
import { MediaPickerDialog } from '../media-picker/MediaPickerDialog.jsx';

const VIDEO_PATTERN = /^https:\/\/(www\.)?(youtube\.com|youtu\.be|m\.youtube\.com|vimeo\.com|player\.vimeo\.com)\//i;

/**
 * A gallery's photos (several at once from the Media Library) and YouTube/Vimeo videos,
 * each with a caption, alternative text and credit, in a chosen order. Inputs are named
 * gallery_items[i][…], so one "Save" stores them with the rest of the form.
 *
 * @param {{
 *   endpoint: string, uploadEndpoint: string, canUpload: boolean, disabled?: boolean,
 *   rows?: Array<{media_id: number|null, video_url: string|null, caption: string|null, alt_override: string|null, credit: string|null, thumbnail?: string|null, name?: string|null}>,
 *   errors?: Record<string, string>
 * }} props
 */
export function GalleryField({ endpoint, uploadEndpoint, canUpload, disabled = false, rows = [], errors = {} }) {
    const baseId = useId();
    const nextKey = useRef(rows.length);
    const [items, setItems] = useState(() => rows.map((row, index) => ({ key: index, ...row })));
    const [open, setOpen] = useState(false);
    const [video, setVideo] = useState('');
    const [videoError, setVideoError] = useState('');
    const [announcement, setAnnouncement] = useState('');

    const describe = (item, index) => (item.media_id ? item.name || `Photo ${index + 1}` : `Video ${index + 1}`);

    function update(index, key, value) {
        setItems((list) => list.map((item, i) => (i === index ? { ...item, [key]: value } : item)));
    }

    function move(index, direction) {
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        setItems((list) => {
            const next = [...list];
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
        setAnnouncement(`${describe(items[index], index)} moved to position ${target + 1}.`);
    }

    function addVideo() {
        const url = video.trim();
        if (!VIDEO_PATTERN.test(url)) {
            setVideoError('Use a YouTube or Vimeo link (https://…).');
            return;
        }
        setItems((list) => [...list, { key: nextKey.current++, media_id: null, video_url: url, caption: '', alt_override: '', credit: '' }]);
        setVideo('');
        setVideoError('');
        setAnnouncement('Video added.');
    }

    return (
        <div className="mb-3">
            <p className="form-label fs-6 mb-1">Photos and videos</p>
            <p className="small text-body-secondary">
                {items.length} {items.length === 1 ? 'item' : 'items'}. Photos come from the Media Library (public files only).
            </p>

            {items.length > 0 && (
                <ol className="pa-gallery-editor list-unstyled">
                    {items.map((item, index) => {
                        const label = describe(item, index);
                        const fieldId = (key) => `${baseId}-${item.key}-${key}`;
                        return (
                            <li key={item.key} className="pa-gallery-editor__item">
                                <div className="pa-gallery-editor__thumb">
                                    {item.media_id && item.thumbnail ? <img src={item.thumbnail} alt="" /> : <i className={`bi ${item.media_id ? 'bi-image' : 'bi-play-btn'}`} aria-hidden="true" />}
                                </div>
                                <span className="small text-truncate" title={item.video_url || item.name || ''}>
                                    {index + 1}. {item.media_id ? item.name || `Photo #${item.media_id}` : item.video_url}
                                </span>
                                <input type="hidden" name={`gallery_items[${index}][media_id]`} value={item.media_id ?? ''} />
                                <input type="hidden" name={`gallery_items[${index}][video_url]`} value={item.video_url ?? ''} />
                                <label htmlFor={fieldId('caption')} className="visually-hidden">
                                    Caption for {label}
                                </label>
                                <input id={fieldId('caption')} name={`gallery_items[${index}][caption]`} className="form-control form-control-sm" placeholder="Caption" value={item.caption ?? ''} maxLength={1000} disabled={disabled} onChange={(e) => update(index, 'caption', e.target.value)} />
                                {item.media_id && (
                                    <>
                                        <label htmlFor={fieldId('alt')} className="visually-hidden">
                                            Alternative text for {label}
                                        </label>
                                        <input id={fieldId('alt')} name={`gallery_items[${index}][alt_override]`} className="form-control form-control-sm" placeholder="Alt text (optional)" value={item.alt_override ?? ''} maxLength={255} disabled={disabled} onChange={(e) => update(index, 'alt_override', e.target.value)} />
                                    </>
                                )}
                                <label htmlFor={fieldId('credit')} className="visually-hidden">
                                    Credit for {label}
                                </label>
                                <input id={fieldId('credit')} name={`gallery_items[${index}][credit]`} className="form-control form-control-sm" placeholder="Credit (optional)" value={item.credit ?? ''} maxLength={191} disabled={disabled} onChange={(e) => update(index, 'credit', e.target.value)} />
                                {errors[`gallery_items.${index}.video_url`] && <div className="invalid-feedback d-block">{errors[`gallery_items.${index}.video_url`]}</div>}
                                {errors[`gallery_items.${index}.media_id`] && <div className="invalid-feedback d-block">{errors[`gallery_items.${index}.media_id`]}</div>}
                                <div className="pa-gallery-editor__actions">
                                    <span>
                                        <button type="button" className="btn btn-sm btn-link" disabled={disabled || index === 0} onClick={() => move(index, -1)}>
                                            <i className="bi bi-arrow-left" aria-hidden="true" />
                                            <span className="visually-hidden">Move {label} earlier</span>
                                        </button>
                                        <button type="button" className="btn btn-sm btn-link" disabled={disabled || index === items.length - 1} onClick={() => move(index, 1)}>
                                            <i className="bi bi-arrow-right" aria-hidden="true" />
                                            <span className="visually-hidden">Move {label} later</span>
                                        </button>
                                    </span>
                                    <button
                                        type="button"
                                        className="btn btn-sm btn-link text-danger"
                                        disabled={disabled}
                                        onClick={() => {
                                            setItems((list) => list.filter((_, i) => i !== index));
                                            setAnnouncement(`${label} removed.`);
                                        }}
                                    >
                                        <i className="bi bi-x-lg" aria-hidden="true" />
                                        <span className="visually-hidden">Remove {label}</span>
                                    </button>
                                </div>
                            </li>
                        );
                    })}
                </ol>
            )}

            {!disabled && (
                <div className="d-flex flex-wrap gap-2 align-items-start mt-2">
                    <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setOpen(true)}>
                        <i className="bi bi-images me-1" aria-hidden="true" />
                        Add photos
                    </button>
                    <div className="input-group input-group-sm" style={{ maxWidth: '26rem' }}>
                        <label htmlFor={`${baseId}-video`} className="visually-hidden">
                            YouTube or Vimeo link
                        </label>
                        <input
                            id={`${baseId}-video`}
                            type="url"
                            className={`form-control${videoError ? ' is-invalid' : ''}`}
                            placeholder="YouTube or Vimeo link"
                            value={video}
                            onChange={(e) => setVideo(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    addVideo();
                                }
                            }}
                        />
                        <button type="button" className="btn btn-outline-primary" onClick={addVideo}>
                            Add video
                        </button>
                    </div>
                    {videoError && <div className="invalid-feedback d-block w-100">{videoError}</div>}
                </div>
            )}

            <span className="visually-hidden" role="status" aria-live="polite">
                {announcement}
            </span>
            {open && (
                <MediaPickerDialog
                    title="Add photos"
                    multiple
                    endpoint={endpoint}
                    uploadEndpoint={uploadEndpoint}
                    canUpload={canUpload}
                    selectedId={null}
                    onClose={() => setOpen(false)}
                    onSelect={() => {}}
                    onSelectMany={(list) => {
                        setItems((current) => [
                            ...current,
                            ...list.map((media) => ({ key: nextKey.current++, media_id: media.id, video_url: null, caption: '', alt_override: '', credit: '', thumbnail: media.thumbnail, name: media.name })),
                        ]);
                        setAnnouncement(`${list.length} ${list.length === 1 ? 'photo' : 'photos'} added.`);
                        setOpen(false);
                    }}
                />
            )}
        </div>
    );
}
