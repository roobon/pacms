import { useCallback, useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import Image from '../common/Image.jsx';

/**
 * Photos and videos as a grid; activating one opens a full-screen viewer (native <dialog>:
 * focus is trapped, Esc closes and focus returns). Arrow keys move between items. Videos
 * load their player only when opened (no third-party requests before that).
 *
 * @param {{items: Array<{kind: 'image'|'video', image?: Object, video?: {provider: string, id: string, url: string, thumbnail: string|null}, caption?: string|null, credit?: string|null}>,
 *   columns?: number|string, showCaptions?: boolean, label?: string}} props
 */
export default function GalleryGrid({ items, columns = 4, showCaptions = false, label = 'Gallery' }) {
    const [open, setOpen] = useState(/** @type {number|null} */ (null));

    if (!items?.length) return null;

    return (
        <>
            <ul className={`pa-gallery pa-gallery--cols-${columns} list-unstyled`} aria-label={label}>
                {items.map((item, index) => (
                    <li key={index} className="pa-gallery__item">
                        <button type="button" className="pa-gallery__button" onClick={() => setOpen(index)}>
                            {item.kind === 'image' ? (
                                <Image image={item.image} className="pa-gallery__image" sizes={`(min-width: 992px) ${Math.round(100 / Number(columns))}vw, 50vw`} />
                            ) : (
                                <span className="pa-gallery__video">
                                    {item.video?.thumbnail ? <img src={item.video.thumbnail} alt="" loading="lazy" /> : null}
                                    <i className="bi bi-play-circle-fill" aria-hidden="true" />
                                </span>
                            )}
                            <span className="visually-hidden">
                                Open {item.kind === 'image' ? 'photo' : 'video'} {index + 1} of {items.length}
                                {item.caption ? `: ${item.caption}` : ''}
                            </span>
                        </button>
                        {showCaptions && item.caption && <p className="pa-gallery__caption">{item.caption}</p>}
                    </li>
                ))}
            </ul>
            {open !== null && <Lightbox items={items} index={open} onIndex={setOpen} onClose={() => setOpen(null)} />}
        </>
    );
}

function Lightbox({ items, index, onIndex, onClose }) {
    const dialog = useRef(/** @type {HTMLDialogElement|null} */ (null));
    const item = items[index];
    const go = useCallback((step) => onIndex((index + step + items.length) % items.length), [index, items.length, onIndex]);

    useEffect(() => {
        const element = dialog.current;
        element?.showModal();
        return () => element?.close();
    }, []);

    return createPortal(
        <dialog
            ref={dialog}
            className="pa-lightbox"
            aria-label={`${item.kind === 'image' ? 'Photo' : 'Video'} ${index + 1} of ${items.length}`}
            onClose={onClose}
            onCancel={onClose}
            onKeyDown={(event) => {
                if (event.key === 'ArrowRight') go(1);
                if (event.key === 'ArrowLeft') go(-1);
            }}
        >
            <div className="pa-lightbox__stage">
                {item.kind === 'image' ? (
                    <img src={item.image.src} srcSet={item.image.srcset ?? undefined} sizes="100vw" alt={item.image.alt ?? ''} className="pa-lightbox__media" />
                ) : (
                    <iframe
                        key={item.video.id}
                        className="pa-lightbox__media pa-lightbox__media--video"
                        title={item.caption || 'Video'}
                        src={item.video.provider === 'youtube' ? `https://www.youtube-nocookie.com/embed/${item.video.id}?autoplay=1` : `https://player.vimeo.com/video/${item.video.id}?autoplay=1`}
                        allow="autoplay; fullscreen; picture-in-picture"
                        allowFullScreen
                    />
                )}
            </div>
            {(item.caption || item.credit) && (
                <p className="pa-lightbox__caption">
                    {item.caption}
                    {item.credit && <span className="pa-lightbox__credit"> {item.caption ? '· ' : ''}{item.credit}</span>}
                </p>
            )}
            <p className="pa-lightbox__count" aria-live="polite">
                {index + 1} / {items.length}
            </p>
            <button type="button" className="pa-lightbox__close" onClick={onClose}>
                <i className="bi bi-x-lg" aria-hidden="true" />
                <span className="visually-hidden">Close</span>
            </button>
            {items.length > 1 && (
                <>
                    <button type="button" className="pa-lightbox__nav pa-lightbox__nav--prev" onClick={() => go(-1)}>
                        <i className="bi bi-chevron-left" aria-hidden="true" />
                        <span className="visually-hidden">Previous</span>
                    </button>
                    <button type="button" className="pa-lightbox__nav pa-lightbox__nav--next" onClick={() => go(1)}>
                        <i className="bi bi-chevron-right" aria-hidden="true" />
                        <span className="visually-hidden">Next</span>
                    </button>
                </>
            )}
        </dialog>,
        document.body,
    );
}
