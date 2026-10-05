import BlockRenderer from '../BlockRenderer.jsx';
import Image from '../common/Image.jsx';
import { containerClass, frameProps } from '../common/frame.js';
import ItemsDisplay from '../display/ItemsDisplay.jsx';
import Accordion from './Accordion.jsx';

export function HeroBlock({ node, preview, children }) {
    const inner = containerClass(node);

    return (
        <section {...frameProps(node, preview, 'pa-hero')}>
            {inner ? <div className={`${inner} pa-hero__inner`}>{children}</div> : <div className="pa-hero__inner">{children}</div>}
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
    const style = { '--pa-cols-d': columns.desktop ?? 3, '--pa-cols-t': columns.tablet ?? columns.desktop ?? 3, '--pa-cols-m': columns.mobile ?? 1 };
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
