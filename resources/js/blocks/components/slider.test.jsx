import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { act, fireEvent, render, screen } from '@testing-library/react';
import BlockRenderer from '../BlockRenderer.jsx';

const slide = (n) => ({
    uuid: `01k0000000000000000000sld${n}`,
    type: 'slide',
    content: { image: null, shade: 'dark', align: 'start' },
    children: [{ uuid: `01k000000000000000000head${n}`, type: 'heading', content: { text: `Slide ${n} headline`, level: '2' } }],
});

const slider = (content = {}) => ({
    uuid: '01k00000000000000000slider',
    type: 'slider',
    content: { aria_label: 'Highlights', autoplay: true, interval: 5, show_arrows: true, show_dots: true, ...content },
    children: [slide(1), slide(2), slide(3)],
});

function current() {
    return document.querySelector('.pa-slider__slide.is-active').textContent;
}

describe('SliderBlock', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        window.matchMedia = vi.fn().mockReturnValue({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() });
    });
    afterEach(() => vi.useRealTimers());

    it('shows one slide at a time; others are hidden from screen readers and the keyboard', () => {
        render(<BlockRenderer nodes={[slider()]} />);

        expect(screen.getByRole('region', { name: 'Highlights' })).toBeInTheDocument();
        expect(current()).toBe('Slide 1 headline');
        const hidden = document.querySelectorAll('.pa-slider__slide:not(.is-active)');
        expect(hidden).toHaveLength(2);
        hidden.forEach((el) => expect(el).toHaveAttribute('aria-hidden', 'true'));

        fireEvent.click(screen.getByRole('button', { name: 'Next slide' }));
        expect(current()).toBe('Slide 2 headline');
        fireEvent.click(screen.getByRole('button', { name: 'Slide 1' }));
        expect(current()).toBe('Slide 1 headline');
        fireEvent.click(screen.getByRole('button', { name: 'Previous slide' }));
        expect(current()).toBe('Slide 3 headline');
    });

    it('moves on by itself, and stops when paused or hovered', () => {
        render(<BlockRenderer nodes={[slider()]} />);

        act(() => vi.advanceTimersByTime(5000));
        expect(current()).toBe('Slide 2 headline');

        fireEvent.click(screen.getByRole('button', { name: 'Pause slides' }));
        act(() => vi.advanceTimersByTime(20000));
        expect(current()).toBe('Slide 2 headline');
        fireEvent.click(screen.getByRole('button', { name: 'Play slides' }));

        fireEvent.mouseEnter(screen.getByRole('region', { name: 'Highlights' }));
        act(() => vi.advanceTimersByTime(20000));
        expect(current()).toBe('Slide 2 headline');
    });

    it('never moves by itself for visitors who prefer reduced motion', () => {
        window.matchMedia = vi.fn().mockReturnValue({ matches: true, addEventListener: vi.fn(), removeEventListener: vi.fn() });
        render(<BlockRenderer nodes={[slider()]} />);

        act(() => vi.advanceTimersByTime(30000));
        expect(current()).toBe('Slide 1 headline');
        expect(screen.queryByRole('button', { name: /Pause slides/ })).toBeNull();
    });
});
