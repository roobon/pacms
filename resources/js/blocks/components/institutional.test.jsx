import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import BlockRenderer from '../BlockRenderer.jsx';
import ItemsDisplay from '../display/ItemsDisplay.jsx';
import { compileTree } from '../style/compile.js';

// Phase 11I: image shapes, the Logo row, letter spacing and compact grids.
const image = { id: 1, src: '/storage/media/1.jpg', srcset: null, sizes: null, width: 800, height: 600, alt: 'Children planting', placeholder: null, focal_point: null };

function show(nodes) {
    return render(
        <MemoryRouter>
            <BlockRenderer nodes={nodes} />
        </MemoryRouter>,
    );
}

describe('Institutional blocks', () => {
    it('crops a circle image to a square, whatever crop was chosen', () => {
        show([{ uuid: '01k000000000000000000image', type: 'image', content: { image, ratio: '16:9', shape: 'circle' } }]);
        const img = screen.getByRole('img', { name: 'Children planting' });
        expect(img.className).toContain('pa-shape--circle');
        expect(img.className).not.toContain('pa-ratio--16x9');
    });

    it('shows a logo row: name tiles until logos are added, links to pages here', () => {
        show([
            {
                uuid: '01k0000000000000000000logs',
                type: 'logos',
                content: { heading: 'Our programmes', size: 'lg', show_labels: false, items: [{ label: 'Eco-Schools', link: { href: '/programs', external: false, new_tab: false } }, { label: 'Young Reporters', image }] },
            },
        ]);
        const list = screen.getByRole('list', { name: 'Our programmes' });
        expect(list.querySelector('.pa-logos__tile').textContent).toBe('Eco-Schools');
        expect(screen.getByRole('link', { name: 'Eco-Schools' }).getAttribute('href')).toBe('/programs');
        // With a logo and names hidden, the name is the logo's text alternative.
        expect(screen.getByRole('img', { name: 'Young Reporters' })).toBeInTheDocument();
    });

    it('compiles letter spacing from named steps only', () => {
        const node = (letterSpacing) => ({ uuid: '01k000000000000000000title', type: 'heading', content: { text: 'X' }, style: { typography: { letter_spacing: letterSpacing } } });
        expect(compileTree([node('wider')])).toContain('letter-spacing: 0.06em;');
        expect(compileTree([node('5px;}')])).not.toContain('letter-spacing');
    });

    it('lets 5 or 6 columns fit with compact items', () => {
        const items = [{ key: 'a', kind: 'news', title: 'One', url: '/news/one' }];
        const grid = (desktop) => (
            <MemoryRouter>
                <ItemsDisplay items={items} display={{ mode: 'grid', columns: { desktop } }} />
            </MemoryRouter>
        );
        const { container, rerender } = render(grid(5));
        expect(container.querySelector('[style*="--pa-item-min"]')).not.toBeNull();
        rerender(grid(3));
        expect(container.querySelector('[style*="--pa-item-min"]')).toBeNull();
    });
});
