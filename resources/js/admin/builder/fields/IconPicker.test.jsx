import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';
import IconPicker from './IconPicker.jsx';

describe('IconPicker', () => {
    it('shows popular icons, searches all icons and picks one', async () => {
        const onPick = vi.fn();
        render(<IconPicker value="bi-tree" onPick={onPick} />);

        fireEvent.click(screen.getByRole('button', { name: 'Browse' }));
        expect(screen.getByRole('button', { name: 'tree' })).toHaveAttribute('aria-pressed', 'true');

        fireEvent.change(screen.getByRole('searchbox', { name: 'Search icons' }), { target: { value: 'bi-bicycle' } });
        fireEvent.click(await screen.findByRole('button', { name: 'bicycle' }));

        expect(onPick).toHaveBeenCalledWith('bi-bicycle');
        expect(screen.queryByRole('dialog', { name: 'Choose an icon' })).toBeNull();
    });
});
