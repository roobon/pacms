import { describe, expect, it } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import TestimonialsDisplay from './TestimonialsDisplay.jsx';

const items = [
    { key: 'testimonials:1', title: 'Rahim Uddin', image: null, meta: { quote: 'Our students now sort their waste.', designation: 'Teacher', organization: 'Dhaka Model School', rating: 5 } },
    { key: 'testimonials:2', title: 'Nadia Islam', image: null, meta: { quote: 'A programme every school should join.' } },
];

describe('TestimonialsDisplay', () => {
    it('shows each quote with who said it and the rating as text for screen readers', () => {
        render(<TestimonialsDisplay items={items} display={{ mode: 'grid' }} />);

        expect(screen.getByText('Our students now sort their waste.')).toBeInTheDocument();
        expect(screen.getByText('Teacher, Dhaka Model School')).toBeInTheDocument();
        expect(screen.getByRole('img', { name: '5 out of 5 stars' })).toBeInTheDocument();
        expect(screen.getAllByRole('listitem')).toHaveLength(2);
    });

    it('shows one quote at a time in the quote slider', () => {
        render(<TestimonialsDisplay items={items} display={{ mode: 'quote-slider' }} showRating={false} />);

        expect(screen.getByText('Testimonial 1 of 2')).toBeInTheDocument();
        expect(screen.queryByText('A programme every school should join.')).toBeNull();
        expect(screen.queryByRole('img', { name: /stars/ })).toBeNull();

        fireEvent.click(screen.getByRole('button', { name: 'Next testimonial' }));
        expect(screen.getByText('A programme every school should join.')).toBeInTheDocument();
        fireEvent.click(screen.getByRole('button', { name: 'Next testimonial' }));
        expect(screen.getByText('Our students now sort their waste.')).toBeInTheDocument();
    });

    it('shows only the first one in single mode', () => {
        render(<TestimonialsDisplay items={items} display={{ mode: 'single' }} />);

        expect(screen.getByText('Rahim Uddin')).toBeInTheDocument();
        expect(screen.queryByText('Nadia Islam')).toBeNull();
    });
});
