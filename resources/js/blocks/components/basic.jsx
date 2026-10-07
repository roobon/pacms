import { useState } from 'react';
import Image from '../common/Image.jsx';
import SmartLink from '../common/SmartLink.jsx';
import { frameProps } from '../common/frame.js';

const BUTTON_CLASSES = {
    primary: 'btn btn-primary',
    secondary: 'btn btn-secondary',
    accent: 'btn btn-accent',
    outline: 'btn btn-outline-primary',
    link: 'btn btn-link px-0',
};

export function HeadingBlock({ node, preview }) {
    const level = Math.min(6, Math.max(1, Number(node.content.level) || 2));
    const Tag = `h${level}`;

    return (
        <Tag {...frameProps(node, preview, 'pa-heading')}>
            {node.content.eyebrow && <span className="pa-eyebrow d-block">{node.content.eyebrow}</span>}
            {node.content.text}
        </Tag>
    );
}

/** The only place server-sanitised HTML is rendered (SECURITY-ARCHITECTURE.md §6). */
export function RichTextBlock({ node, preview }) {
    return <div {...frameProps(node, preview, 'pa-rich-text')} dangerouslySetInnerHTML={{ __html: node.content.html ?? '' }} />;
}

export function ImageBlock({ node, preview }) {
    const { image, alt, caption, link, ratio } = node.content;
    if (!image) return preview ? <div {...frameProps(node, preview, 'pa-placeholder')}>Choose an image</div> : null;

    const img = (
        <Image
            image={{ ...image, alt: alt || image.alt }}
            className={`pa-block-image ${ratio && ratio !== 'auto' ? `pa-ratio pa-ratio--${ratio.replace(':', 'x')}` : ''}`}
        />
    );

    return (
        <figure {...frameProps(node, preview, 'pa-figure')}>
            {link ? <SmartLink link={link}>{img}</SmartLink> : img}
            {caption && <figcaption className="pa-figure__caption">{caption}</figcaption>}
        </figure>
    );
}

export function ButtonBlock({ node, preview }) {
    const { label, link, variant, icon } = node.content;

    return (
        <SmartLink link={link} {...frameProps(node, preview, BUTTON_CLASSES[variant] ?? BUTTON_CLASSES.primary)}>
            {icon && <i className={`bi ${icon}`} aria-hidden="true" />}
            {label}
        </SmartLink>
    );
}

export function ButtonGroupBlock({ node, preview, children }) {
    return <div {...frameProps(node, preview, 'pa-button-group')}>{children}</div>;
}

export function DividerBlock({ node, preview }) {
    return <hr {...frameProps(node, preview, `pa-divider pa-divider--${node.content.line ?? 'solid'}`)} />;
}

export function SpacerBlock({ node, preview }) {
    const size = /^space\.[0-9a-z]+$/.test(node.content.size ?? '') ? node.content.size : 'space.6';
    return <div aria-hidden="true" {...frameProps(node, preview)} style={{ height: `var(--pa-${size.replace('.', '-')})` }} />;
}

export function IconBlock({ node, preview }) {
    const { icon, label, size } = node.content;
    if (!/^bi-[a-z0-9-]+$/.test(icon ?? '')) return null;

    return (
        <span {...frameProps(node, preview, `pa-icon pa-icon--${size ?? 'lg'}`)}>
            <i className={`bi ${icon}`} aria-hidden={label ? undefined : 'true'} role={label ? 'img' : undefined} aria-label={label || undefined} />
        </span>
    );
}

/**
 * Click-to-load video facade: no third-party iframe or cookies until the visitor presses play.
 */
export function VideoBlock({ node, preview }) {
    const [playing, setPlaying] = useState(false);
    const { url, title, poster } = node.content;
    if (!url?.id) return null;

    const src = url.provider === 'youtube'
        ? `https://www.youtube-nocookie.com/embed/${encodeURIComponent(url.id)}?autoplay=1&rel=0`
        : `https://player.vimeo.com/video/${encodeURIComponent(url.id)}?autoplay=1&dnt=1`;
    const cover = poster?.src ?? url.thumbnail;

    return (
        <div {...frameProps(node, preview, 'pa-video')}>
            {playing && !preview ? (
                <iframe src={src} title={title} allow="autoplay; encrypted-media; picture-in-picture; fullscreen" allowFullScreen loading="lazy" />
            ) : (
                <button type="button" className="pa-video__facade" onClick={() => setPlaying(true)} style={cover ? { backgroundImage: `url("${cover}")` } : undefined}>
                    <span className="pa-video__play" aria-hidden="true">
                        <i className="bi bi-play-fill" />
                    </span>
                    <span className="visually-hidden">Play video: {title}</span>
                </button>
            )}
        </div>
    );
}

const LIST_ICONS = { check: 'bi-check-circle-fill', bullet: null, number: null };

/** List of short items: bullets, numbers (<ol>), check marks or icons; items may link. */
export function ListBlock({ node, preview }) {
    const { style = 'check', icon, columns = '1', items = [] } = node.content;
    const Tag = style === 'number' ? 'ol' : 'ul';
    const iconFor = (item) => (style === 'icon' ? item.icon || icon || 'bi-arrow-right-circle' : LIST_ICONS[style]);

    return (
        <Tag {...frameProps(node, preview, `pa-list pa-list--${style} pa-list--cols-${columns}`)}>
            {items.map((item, index) => {
                const glyph = iconFor(item);
                return (
                    <li key={index} className="pa-list__item">
                        {glyph && <i className={`bi ${glyph} pa-list__icon`} aria-hidden="true" />}
                        <span className="pa-list__text">{item.link ? <SmartLink link={item.link}>{item.text}</SmartLink> : item.text}</span>
                    </li>
                );
            })}
        </Tag>
    );
}
