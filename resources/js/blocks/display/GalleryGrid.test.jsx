import { beforeAll, describe, expect, it } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import GalleryGrid from './GalleryGrid.jsx';
import LogoWall from './LogoWall.jsx';

// jsdom has no modal dialogs; open/close the element the way the browser would.
beforeAll(() => {
    HTMLDialogElement.prototype.showModal = function showModal() {
        this.setAttribute('open', '');
    };
    HTMLDialogElement.prototype.close = function close() {
        this.removeAttribute('open');
    };
});

const image = (id, alt) => ({ id, src: `/storage/media/${id}.jpg`, srcset: null, sizes: null, width: 800, height: 600, alt, placeholder: null, focal_point: null });

const items = [
    { kind: 'image', image: image(1, 'Children planting'), caption: 'Opening', credit: 'R. Ahmed' },
    { kind: 'video', video: { provider: 'youtube', id: 'abc123', url: 'https://youtu.be/abc123', thumbnail: null }, caption: 'Highlights', credit: null },
];

describe('GalleryGrid', () => {
    it('opens a viewer, moves with the arrow keys and loads videos only when opened', () => {
        render(<GalleryGrid items={items} label="Tree fair" />);

        expect(screen.getByRole('list', { name: 'Tree fair' })).toBeInTheDocument();
        expect(document.querySelector('iframe')).toBeNull();

        fireEvent.click(screen.getByRole('button', { name: /Open photo 1 of 2: Opening/ }));
        const viewer = screen.getByRole('dialog', { name: 'Photo 1 of 2' });
        expect(viewer).toHaveTextContent('Opening');
        expect(viewer).toHaveTextContent('R. Ahmed');

        fireEvent.keyDown(viewer, { key: 'ArrowRight' });
        expect(screen.getByRole('dialog', { name: 'Video 2 of 2' })).toBeInTheDocument();
        expect(document.querySelector('iframe')).toHaveAttribute('src', 'https://www.youtube-nocookie.com/embed/abc123?autoplay=1');

        fireEvent.click(screen.getByRole('button', { name: 'Close' }));
        expect(screen.queryByRole('dialog')).toBeNull();
    });
});

describe('LogoWall', () => {
    it('links logos to partner websites with the organisation name as text', () => {
        render(
            <LogoWall
                items={[
                    { key: 'partners:1', title: 'Green Earth Trust', url: 'https://greenearth.example', image: image(5, '') },
                    { key: 'partners:2', title: 'Blue Fund', url: null, image: null },
                ]}
            />,
        );

        const link = screen.getByRole('link', { name: /Green Earth Trust/ });
        expect(link).toHaveAttribute('href', 'https://greenearth.example');
        expect(screen.getByRole('img', { name: 'Green Earth Trust' })).toBeInTheDocument();
        expect(screen.getByText('Blue Fund')).toBeInTheDocument();
    });
});
