import { useId, useState } from 'react';
import Image from '../common/Image.jsx';
import { Carousel } from './ItemsDisplay.jsx';

/**
 * Testimonials (CMS-ARCHITECTURE.md §18): grid of quote cards, list, carousel, quote slider
 * (one at a time), single, featured (first one large) and masonry. Items are the public
 * allowlist from the server: name, quote, designation, organisation, photo, rating.
 *
 * @param {{items: Array<Object>, display?: Object, showPhoto?: boolean, showRating?: boolean}} props
 */
export default function TestimonialsDisplay({ items, display = {}, showPhoto = true, showRating = true }) {
    const mode = display.mode ?? 'grid';
    const columns = display.columns ?? {};
    const vars = {
        '--pa-cols-d': columns.desktop ?? 3,
        '--pa-cols-t': columns.tablet ?? Math.min(2, columns.desktop ?? 3),
        '--pa-cols-m': columns.mobile ?? 1,
    };
    const cardProps = { showPhoto, showRating, cardStyle: display.card_style ?? 'elevated' };

    if (mode === 'single') {
        return <TestimonialCard item={items[0]} size="large" {...cardProps} />;
    }

    if (mode === 'quote-slider') {
        return <QuoteSlider items={items} cardProps={cardProps} />;
    }

    if (mode === 'carousel') {
        return <Carousel items={items} vars={vars} renderItem={(item) => <TestimonialCard item={item} {...cardProps} />} />;
    }

    if (mode === 'list') {
        return (
            <ul className="pa-items-list list-unstyled">
                {items.map((item) => (
                    <li key={item.key}>
                        <TestimonialCard item={item} {...cardProps} />
                    </li>
                ))}
            </ul>
        );
    }

    if (mode === 'featured' && items.length > 1) {
        const [first, ...rest] = items;
        return (
            <div className="pa-items-featured">
                <TestimonialCard item={first} size="large" {...cardProps} />
                <ul className="pa-items-grid list-unstyled" style={vars}>
                    {rest.map((item) => (
                        <li key={item.key}>
                            <TestimonialCard item={item} {...cardProps} />
                        </li>
                    ))}
                </ul>
            </div>
        );
    }

    return (
        <ul className={`${mode === 'masonry' ? 'pa-masonry' : 'pa-items-grid'} list-unstyled`} style={vars}>
            {items.map((item) => (
                <li key={item.key}>
                    <TestimonialCard item={item} {...cardProps} />
                </li>
            ))}
        </ul>
    );
}

/**
 * One testimonial: the quote, then who said it.
 *
 * @param {{item: Object, size?: 'normal'|'large', showPhoto?: boolean, showRating?: boolean, cardStyle?: string}} props
 */
export function TestimonialCard({ item, size = 'normal', showPhoto = true, showRating = true, cardStyle = 'elevated' }) {
    const meta = item.meta ?? {};
    const role = [meta.designation, meta.organization].filter(Boolean).join(', ');
    const rating = showRating && meta.rating ? Math.max(1, Math.min(5, Number(meta.rating))) : null;

    return (
        <figure className={`pa-testimonial pa-testimonial--${size} pa-item--${cardStyle}`}>
            {rating && (
                <p className="pa-testimonial__rating" role="img" aria-label={`${rating} out of 5 stars`}>
                    {'★'.repeat(rating)}
                    <span className="pa-testimonial__rating-empty">{'★'.repeat(5 - rating)}</span>
                </p>
            )}
            <blockquote className="pa-testimonial__quote">
                <p>{meta.quote}</p>
            </blockquote>
            <figcaption className="pa-testimonial__by">
                {showPhoto && item.image && (
                    <span className="pa-testimonial__photo">
                        <Image image={{ ...item.image, alt: '' }} />
                    </span>
                )}
                <span>
                    <span className="pa-testimonial__name">{item.title}</span>
                    {role && <span className="pa-testimonial__role">{role}</span>}
                    {meta.citation && <cite className="pa-testimonial__cite">{meta.citation}</cite>}
                </span>
            </figcaption>
        </figure>
    );
}

/** One quote at a time with previous / next buttons (no autoplay, WCAG 2.2.2). */
function QuoteSlider({ items, cardProps }) {
    const id = useId();
    const [index, setIndex] = useState(0);
    const count = items.length;
    const go = (step) => setIndex((current) => (current + step + count) % count);

    return (
        <div className="pa-quote-slider" role="region" aria-roledescription="carousel" aria-labelledby={`${id}-label`}>
            <span id={`${id}-label`} className="visually-hidden">
                Testimonials
            </span>
            <div aria-live="polite" aria-atomic="true">
                <span className="visually-hidden">
                    Testimonial {index + 1} of {count}
                </span>
                <TestimonialCard item={items[index]} size="large" {...cardProps} />
            </div>
            {count > 1 && (
                <div className="pa-carousel__controls justify-content-center">
                    <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => go(-1)}>
                        <i className="bi bi-chevron-left" aria-hidden="true" />
                        <span className="visually-hidden">Previous testimonial</span>
                    </button>
                    <span className="small align-self-center" aria-hidden="true">
                        {index + 1} / {count}
                    </span>
                    <button type="button" className="btn btn-outline-primary btn-sm" onClick={() => go(1)}>
                        <i className="bi bi-chevron-right" aria-hidden="true" />
                        <span className="visually-hidden">Next testimonial</span>
                    </button>
                </div>
            )}
        </div>
    );
}
