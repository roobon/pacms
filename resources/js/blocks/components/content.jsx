import BlockRenderer from '../BlockRenderer.jsx';
import Image from '../common/Image.jsx';
import { containerClass, frameProps } from '../common/frame.js';
import ItemsDisplay from '../display/ItemsDisplay.jsx';
import SmartLink from '../common/SmartLink.jsx';
import Accordion from './Accordion.jsx';

export function HeroBlock({ node, preview, children }) {
    const inner = containerClass(node);

    return (
        <section {...frameProps(node, preview, 'pa-hero')}>
            {inner ? <div className={`${inner} pa-hero__inner`}>{children}</div> : <div className="pa-hero__inner">{children}</div>}
        </section>
    );
}

/**
 * Split (Phase 11W): a photo filling its half of the screen to the edge, and the child blocks
 * on the other half, lined up with the site width. Phones show the photo first. Without a
 * photo yet, a quiet panel holds its place.
 */
export function SplitBlock({ node, preview, children }) {
    const { image, alt, image_side: side = 'left', image_width: width = 'half', height = 'md' } = node.content;
    const classes = `pa-split pa-split--photo-${side === 'right' ? 'right' : 'left'} pa-split--w-${width} pa-split--h-${height}`;

    return (
        <section {...frameProps(node, preview, classes)}>
            <div className="pa-split__media">
                {image ? (
                    <Image image={{ ...image, alt: alt || image.alt }} className="pa-split__image" sizes="(min-width: 992px) 60vw, 100vw" />
                ) : (
                    <div className="pa-split__placeholder">
                        <i className="bi bi-image" aria-hidden="true" />
                        {preview && <span>Choose a photo (Content → Photo)</span>}
                    </div>
                )}
            </div>
            <div className="pa-split__body">
                <div className="pa-split__content">{children}</div>
            </div>
        </section>
    );
}

export function CtaBlock({ node, preview, children }) {
    return <div {...frameProps(node, preview, 'pa-cta')}>{children}</div>;
}

export function CardsBlock({ node, preview }) {
    const items = (node.content.items ?? []).map((card, index) => ({
        key: `${node.uuid}-${index}`,
        kind: 'card',
        title: card.title,
        url: card.link?.href ?? null,
        link: card.link ?? null,
        excerpt: card.text ?? null,
        image: card.image ?? null,
        icon: card.icon ?? null,
    }));

    return (
        <div {...frameProps(node, preview, 'pa-cards')}>
            {node.content.heading && <h2 className="pa-block-heading">{node.content.heading}</h2>}
            <ItemsDisplay items={items} display={node.display} headingLevel={node.content.heading ? 3 : 2} />
        </div>
    );
}

export function StatisticsBlock({ node, preview }) {
    const columns = node.display?.columns ?? {};
    // Without a setting, one column per number (up to four), so 4 statistics share one row.
    const fallback = Math.min(Math.max((node.content.items ?? []).length, 1), 4);
    const style = { '--pa-cols-d': columns.desktop ?? fallback, '--pa-cols-t': columns.tablet ?? Math.min(columns.desktop ?? fallback, 2), '--pa-cols-m': columns.mobile ?? 1, '--pa-item-min': '9rem' };
    const format = new Intl.NumberFormat(document.documentElement.lang || 'en');

    return (
        <div {...frameProps(node, preview, 'pa-statistics')}>
            {node.content.heading && <h2 className="pa-block-heading">{node.content.heading}</h2>}
            <dl className="pa-items-grid" style={style}>
                {(node.content.items ?? []).map((stat, index) => (
                    <div className="pa-stat-public" key={index}>
                        {stat.icon && <i className={`bi ${stat.icon} pa-stat-public__icon`} aria-hidden="true" />}
                        {/* dt before dd (valid HTML); CSS shows the number first. */}
                        <dt className="pa-stat-public__label">{stat.label}</dt>
                        <dd className="pa-stat-public__value">
                            {stat.prefix}
                            {format.format(Number(stat.value) || 0)}
                            {stat.suffix}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}

export function AccordionBlock({ node, preview }) {
    const items = (node.children ?? []).map((child) => ({
        key: child.uuid,
        title: child.content?.title ?? '',
        body: <BlockRenderer nodes={child.children ?? []} />,
    }));

    return (
        <div {...frameProps(node, preview, 'pa-accordion-block')}>
            {node.content.heading && <h2 className="pa-block-heading">{node.content.heading}</h2>}
            <Accordion items={items} firstOpen={Boolean(node.display?.first_open)} multiple={Boolean(node.display?.allow_multiple_open)} headingLevel={node.content.heading ? 3 : 2} />
        </div>
    );
}

export function FaqBlock({ node, preview }) {
    const items = (node.content.items ?? []).map((item, index) => ({
        key: `${node.uuid}-${index}`,
        title: item.question,
        // Answers are rich text sanitised on the server.
        body: <div className="pa-rich-text" dangerouslySetInnerHTML={{ __html: item.answer ?? '' }} />,
    }));

    return (
        <div {...frameProps(node, preview, 'pa-faq')}>
            {node.content.heading && <h2 className="pa-block-heading">{node.content.heading}</h2>}
            <Accordion items={items} headingLevel={node.content.heading ? 3 : 2} />
        </div>
    );
}

export function QuoteBlock({ node, preview }) {
    const { text, author, role, image } = node.content;

    return (
        <figure {...frameProps(node, preview, 'pa-quote')}>
            <blockquote className="pa-quote__text">
                <p>{text}</p>
            </blockquote>
            {(author || role || image) && (
                <figcaption className="pa-quote__cite">
                    {image && <Image image={{ ...image, alt: '' }} className="pa-quote__photo" />}
                    <span>
                        {author && <strong className="d-block">{author}</strong>}
                        {role && <span className="text-body-secondary">{role}</span>}
                    </span>
                </figcaption>
            )}
        </figure>
    );
}

/** Logo row (Phase 11I): chosen logos with a name and an optional link (to a page here or elsewhere). */
export function LogoRowBlock({ node, preview }) {
    const { heading, items = [], size = 'lg', show_labels: showLabels = false, grayscale = false } = node.content;
    if (!items.length) return preview ? <div {...frameProps(node, preview, 'pa-placeholder')}>Add logos</div> : null;

    return (
        <div {...frameProps(node, preview, 'pa-logo-row')}>
            {heading && <h2 className="pa-block-heading">{heading}</h2>}
            <ul className={`pa-logos pa-logos--${size}${grayscale ? ' pa-logos--grayscale' : ''}${showLabels ? ' pa-logos--labelled' : ''} list-unstyled`} aria-label={heading || 'Logos'}>
                {items.map((item, index) => {
                    // The name is the logo's text alternative; shown under it only if chosen.
                    const logo = item.image ? <Image image={{ ...item.image, alt: showLabels ? '' : item.label }} className="pa-logos__image" sizes="12rem" /> : null;
                    const content = (
                        <>
                            {logo}
                            {/* Without a logo yet, the name stands in, in a tile the size of a logo. */}
                            {!logo && <span className="pa-logos__tile">{item.label}</span>}
                            {showLabels && logo && <span className="pa-logos__name">{item.label}</span>}
                        </>
                    );
                    return (
                        <li key={index} className="pa-logos__item">
                            {item.link?.href ? (
                                <SmartLink link={item.link} className="pa-logos__link">
                                    {content}
                                </SmartLink>
                            ) : (
                                content
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
