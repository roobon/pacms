import { fireEvent, render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it } from 'vitest';
import Palette from './Palette.jsx';
import { useBuilder } from './store.js';

const cap = { allowed_children: ['*'], excluded_children: [], allowed_parents: null, contexts: null, transparent: false, display_modes: [] };

beforeEach(() => {
    useBuilder.getState().init({
        definitions: { types: [{ slug: 'section', label: 'Section', category: 'layout', icon: 'bi-square', fields: [], defaults: {}, capabilities: cap }], endpoints: {} },
        nodes: [],
        context: 'template',
    });
});

describe('Palette', () => {
    it('opens outside the scrolling column and closes on an outside click', () => {
        const { container } = render(
            <div style={{ overflow: 'auto' }}>
                <Palette parentUuid={null} label="Add block" />
            </div>,
        );

        fireEvent.click(screen.getByRole('button', { name: /Add block/ }));
        const panel = screen.getByRole('dialog', { name: 'Add block' });
        expect(container.contains(panel)).toBe(false);
        expect(panel.closest('.pa-popover')).not.toBeNull();
        expect(screen.getByRole('tab', { name: 'Templates' })).toBeInTheDocument();
        expect(screen.getByRole('tab', { name: 'Global' })).toBeInTheDocument();

        fireEvent.pointerDown(document.body);
        expect(screen.queryByRole('dialog', { name: 'Add block' })).toBeNull();
    });

    it('adds a block and closes', () => {
        render(<Palette parentUuid={null} label="Add block" />);
        fireEvent.click(screen.getByRole('button', { name: /Add block/ }));
        fireEvent.click(screen.getByRole('button', { name: 'Section' }));

        expect(useBuilder.getState().nodes).toHaveLength(1);
        expect(screen.queryByRole('dialog')).toBeNull();
    });
});
