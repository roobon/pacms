import { useCallback, useEffect, useId, useState } from 'react';
import BlockRenderer from '../BlockRenderer.jsx';
import Image from '../common/Image.jsx';
import { frameProps } from '../common/frame.js';

/**
 * Full-width slideshow, one slide at a time (crossfade). Arrows, dots, and optional autoplay
 * with a pause button; autoplay stops while the pointer is over the slider or keyboard focus
 * is inside it, and never runs for visitors who prefer reduced motion (WCAG 2.2.2).
 * Hidden slides are inert, so screen readers and the Tab key only reach the current one.
 */
export function SliderBlock({ node, preview }) {
    const id = useId();
    const slides = node.children ?? [];
    const count = slides.length;
    const { aria_label: label = 'Highlights', height = 'medium', autoplay = true, interval = 7, show_arrows: arrows = true, show_dots: dots = true } = node.content;

    const [index, setIndex] = useState(0);
    const [paused, setPaused] = useState(false); // the visitor pressed Pause
    const [held, setHeld] = useState(false); // pointer over the slider or focus inside it
    const reducedMotion = usePrefersReducedMotion();
    const playing = autoplay && count > 1 && !paused && !held && !reducedMotion && !preview;

    const go = useCallback((step) => setIndex((current) => (current + step + count) % count), [count]);

    useEffect(() => {
        if (!playing) return undefined;
        const timer = setTimeout(() => go(1), Math.max(4, Number(interval) || 7) * 1000);
        return () => clearTimeout(timer);
    }, [playing, index, interval, go]);

    if (count === 0) {
        return preview ? <div {...frameProps(node, preview, 'pa-placeholder')}>Add slides to the slider</div> : null;
    }
    const current = Math.min(index, count - 1);

    return (
        <section
            {...frameProps(node, preview, `pa-slider pa-slider--${height}`)}
            aria-roledescription="carousel"
            aria-label={label}
            onMouseEnter={() => setHeld(true)}
            onMouseLeave={() => setHeld(false)}
            onFocus={() => setHeld(true)}
            onBlur={(event) => !event.currentTarget.contains(event.relatedTarget) && setHeld(false)}
        >
            <div className="pa-slider__slides" aria-live={playing ? 'off' : 'polite'}>
                {slides.map((slide, i) => (
                    <div
                        key={slide.uuid}
                        id={`${id}-slide-${i}`}
                        className={`pa-slider__slide${i === current ? ' is-active' : ''}`}
                        role="group"
                        aria-roledescription="slide"
                        aria-label={`${i + 1} of ${count}`}
                        aria-hidden={i === current ? undefined : true}
                        inert={i === current ? undefined : true}
                    >
                        <BlockRenderer nodes={[slide]} />
                    </div>
                ))}
            </div>

            {count > 1 && (
                <div className="pa-slider__controls">
                    {autoplay && !reducedMotion && (
                        <button type="button" className="pa-slider__button" onClick={() => setPaused(!paused)}>
                            <i className={`bi ${paused ? 'bi-play-fill' : 'bi-pause-fill'}`} aria-hidden="true" />
                            <span className="visually-hidden">{paused ? 'Play slides' : 'Pause slides'}</span>
                        </button>
                    )}
                    {arrows && (
                        <button type="button" className="pa-slider__button" onClick={() => go(-1)} aria-controls={`${id}-slide-${current}`}>
                            <i className="bi bi-chevron-left" aria-hidden="true" />
                            <span className="visually-hidden">Previous slide</span>
                        </button>
                    )}
                    {dots && (
                        <div className="pa-slider__dots">
                            {slides.map((slide, i) => (
                                <button key={slide.uuid} type="button" className="pa-slider__dot" aria-current={i === current ? 'true' : undefined} onClick={() => setIndex(i)}>
                                    <span className="visually-hidden">Slide {i + 1}</span>
                                </button>
                            ))}
                        </div>
                    )}
                    {arrows && (
                        <button type="button" className="pa-slider__button" onClick={() => go(1)} aria-controls={`${id}-slide-${current}`}>
                            <i className="bi bi-chevron-right" aria-hidden="true" />
                            <span className="visually-hidden">Next slide</span>
                        </button>
                    )}
                </div>
            )}
        </section>
    );
}

/** One slide: background image, a shade for readable text, and the slide's content. */
export function SlideBlock({ node, preview, children }) {
    const { image, shade = 'dark', align = 'start' } = node.content;

    return (
        <div {...frameProps(node, preview, `pa-slide pa-slide--${shade} pa-slide--${align}`)}>
            {image && <Image image={{ ...image, alt: '' }} className="pa-slide__image" sizes="100vw" />}
            <div className="container pa-slide__content">{children}</div>
        </div>
    );
}

function usePrefersReducedMotion() {
    const query = '(prefers-reduced-motion: reduce)';
    const [reduced, setReduced] = useState(() => typeof window !== 'undefined' && window.matchMedia?.(query).matches === true);

    useEffect(() => {
        const media = window.matchMedia?.(query);
        if (!media) return undefined;
        const update = () => setReduced(media.matches);
        media.addEventListener('change', update);
        return () => media.removeEventListener('change', update);
    }, []);

    return reduced;
}
