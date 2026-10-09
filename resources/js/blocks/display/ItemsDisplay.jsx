import { useId, useRef } from 'react';
import ItemCard from './ItemCard.jsx';

/**
 * Display modes for normalised items (CMS-ARCHITECTURE.md §7): grid, list, carousel,
 * featured. The mode never depends on where the items came from.
 *
 * @param {{items: Array<Object>, display?: Object, showExcerpt?: boolean, showDate?: boolean, headingLevel?: number}} props
 */
export default function ItemsDisplay({ items, display = {}, showExcerpt = true, showDate = true, headingLevel = 3 }) {
    const mode = display.mode ?? 'grid';
    const columns = display.columns ?? {};
    const vars = {
        '--pa-cols-d': columns.desktop ?? 3,
        '--pa-cols-t': columns.tablet ?? Math.min(2, columns.desktop ?? 3),
        '--pa-cols-m': columns.mobile ?? 1,
    };
    const cardProps = { showExcerpt, showDate, headingLevel, cardStyle: display.card_style ?? 'elevated', ratio: display.image_ratio ?? '16:9' };

    if (mode === 'list') {
        return (
            <ul className="pa-items-list list-unstyled">
                {items.map((item) => (
                    <li key={item.key}>
                        <ItemCard item={item} layout="list" {...cardProps} showImage={display.show_image !== false} />
                    </li>
                ))}
            </ul>
        );
    }

    if (mode === 'carousel') {
        return <Carousel items={items} vars={vars} renderItem={(item) => <ItemCard item={item} {...cardProps} />} />;
    }

    if (mode === 'featured' && items.length > 1) {
        const [first, ...rest] = items;
        return (
            <div className="pa-items-featured">
                <ItemCard item={first} layout="featured" {...cardProps} />
                <ul className="pa-items-grid list-unstyled" style={vars}>
                    {rest.map((item) => (
                        <li key={item.key}>
                            <ItemCard item={item} {...cardProps} />
                        </li>
                    ))}
                </ul>
            </div>
        );
    }

    return (
        <ul className="pa-items-grid list-unstyled" style={vars}>
            {items.map((item) => (
                <li key={item.key}>
                    <ItemCard item={item} {...cardProps} />
                </li>
            ))}
        </ul>
    );
}

/**
 * Scroll-snap carousel: works with touch, keyboard and screen readers; buttons scroll by
 * one card. No autoplay (WCAG 2.2.2), no third-party library.
 *
 * @param {{items: Array<Object>, vars: Object, renderItem: (item: Object) => import('react').ReactNode}} props
 */
export function Carousel({ items, vars, renderItem }) {
    const id = useId();
    const track = useRef(null);

    function scroll(direction) {
        const element = track.current;
        if (!element) return;
        const card = element.querySelector('li');
        element.scrollBy({ left: direction * (card ? card.getBoundingClientRect().width + 16 : element.clientWidth), behavior: 'smooth' });
    }

    return (
        <div className="pa-carousel" role="region" aria-roledescription="carousel" aria-labelledby={`${id}-label`}>
            <span id={`${id}-label`} className="visually-hidden">
                Carousel with {items.length} items
            </span>
            <ul ref={track} className="pa-carousel__track list-unstyled" style={vars} tabIndex={0}>
                {items.map((item, index) => (
                    <li key={item.key} aria-roledescription="slide" aria-label={`${index + 1} of ${items.length}`}>
                        {renderItem(item)}
                    </li>
                ))}
            </ul>
            <div className="pa-carousel__controls">
                <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => scroll(-1)}>
                    <i className="bi bi-chevron-left" aria-hidden="true" />
                    <span className="visually-hidden">Previous</span>
                </button>
                <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => scroll(1)}>
                    <i className="bi bi-chevron-right" aria-hidden="true" />
                    <span className="visually-hidden">Next</span>
                </button>
            </div>
        </div>
    );
}
