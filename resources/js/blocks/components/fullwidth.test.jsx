import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import BlockRenderer from '../BlockRenderer.jsx';
import ItemsDisplay from '../display/ItemsDisplay.jsx';
import GalleryGrid from '../display/GalleryGrid.jsx';
import { compileTree } from '../style/compile.js';

// Phase 11W: the Split block, images filling the width and the Tiles display.
const image = { id: 1, src: '/storage/media/1.jpg', srcset: null, sizes: null, width: 800, height: 600, alt: 'Children at a pond', placeholder: null, focal_point: null };
const heading = { uuid: '01k0000000000000000000head', type: 'heading', content: { text: 'Using nature as a classroom', level: '2' } };

function show(nodes) {
    return render(
        <MemoryRouter>
            <BlockRenderer nodes={nodes} />
        </MemoryRouter>,
    );
}

describe('Full-width blocks', () => {
    it('shows a split: the photo on the chosen side, the text beside it', () => {
        const { container } = show([{ uuid: '01k000000000000000000split', type: 'split', content: { image, image_side: 'right', image_width: 'wide', height: 'lg' }, children: [heading] }]);
        const split = container.querySelector('.pa-split');

        expect(split.className).toContain('pa-split--photo-right');
        expect(split.className).toContain('pa-split--w-wide');
        expect(split.className).toContain('pa-split--h-lg');
        expect(screen.getByRole('img', { name: 'Children at a pond' })).toBeInTheDocument();
        expect(split.querySelector('.pa-split__content h2').textContent).toBe('Using nature as a classroom');
    });

    it('keeps the photo half as a quiet panel until a photo is chosen', () => {
        const { container } = show([{ uuid: '01k000000000000000000split', type: 'split', content: {}, children: [heading] }]);

        expect(container.querySelector('.pa-split--photo-left .pa-split__placeholder')).not.toBeNull();
        // The editor's hint is for the builder only.
        expect(container.textContent).not.toContain('Choose a photo');
    });

    it('puts a split\'s padding around the text, never around the photo', () => {
        const css = compileTree([{ uuid: '01k000000000000000000split', type: 'split', content: {}, layout: { padding: { top: { $token: 'space.9' } } } }]);
        expect(css).toContain('--pa-split-pad-top: var(--pa-space-9);');
        expect(css).not.toContain('padding-top');
    });

    it('lets an image fill the width at a banner height, which wins over the crop', () => {
        const { container } = show([{ uuid: '01k000000000000000000image', type: 'image', content: { image, ratio: '4:3', size: 'fill', height: 'md' } }]);
        const figure = container.querySelector('figure');

        expect(figure.className).toContain('pa-figure--fill');
        expect(figure.className).toContain('pa-figure--h-md');
        expect(screen.getByRole('img').className).not.toContain('pa-ratio--4x3');
    });

    it('shows items as tiles: the title over the photo, one link per tile', () => {
        const items = [
            { key: 'a', kind: 'news', title: 'River clean-up', url: '/news/river', image, meta: { category: 'Stories' } },
            { key: 'b', kind: 'news', title: 'No photo yet', url: '/news/plain' },
        ];
        const { container } = render(
            <MemoryRouter>
                <ItemsDisplay items={items} display={{ mode: 'tiles', columns: { desktop: 5 } }} />
            </MemoryRouter>,
        );

        expect(container.querySelector('.pa-tiles').getAttribute('style')).toContain('--pa-cols-d: 5');
        expect(screen.getByRole('link', { name: 'River clean-up' }).getAttribute('href')).toBe('/news/river');
        expect(container.querySelectorAll('.pa-tile')).toHaveLength(2);
        expect(container.querySelectorAll('.pa-tile--plain')).toHaveLength(1);
        expect(container.querySelector('.pa-tile__meta').textContent).toContain('Stories');
    });

    it('shows gallery tiles without captions under the photos', () => {
        const { container } = render(<GalleryGrid items={[{ kind: 'image', image, caption: 'At the pond' }]} columns="6" showCaptions tiles />);

        expect(container.querySelector('.pa-gallery').className).toContain('pa-gallery--tiles');
        expect(container.querySelector('.pa-gallery').className).toContain('pa-gallery--cols-6');
        expect(container.querySelector('.pa-gallery__caption')).toBeNull();
    });
});
