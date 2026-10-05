import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { fireEvent } from '@testing-library/react';
import BlockRenderer from './BlockRenderer.jsx';
import { PreviewContext } from './common/frame.js';

function renderBlocks(nodes, preview = false) {
    return render(
        <MemoryRouter>
            <PreviewContext value={preview}>
                <BlockRenderer nodes={nodes} />
            </PreviewContext>
        </MemoryRouter>,
    );
}

describe('BlockRenderer', () => {
    it('renders nested blocks recursively with scoped classes', () => {
        renderBlocks([
            {
                uuid: 'aaaaaaaaaaaaaaaaaaaaaaaaaa',
                type: 'section',
                content: {},
                layout: { container: 'narrow' },
                children: [
                    { uuid: 'bbbbbbbbbbbbbbbbbbbbbbbbbb', type: 'heading', content: { text: 'Our programs', level: '2', eyebrow: 'What we do' } },
                    { uuid: 'cccccccccccccccccccccccccc', type: 'rich-text', content: { html: '<p>Clean <strong>text</strong></p>' } },
                    { uuid: 'dddddddddddddddddddddddddd', type: 'button', content: { label: 'Contact', variant: 'accent', link: { href: '/contact', new_tab: false, external: false } } },
                ],
            },
        ]);

        const heading = screen.getByRole('heading', { level: 2 });
        expect(heading).toHaveTextContent('What we doOur programs');
        expect(heading.closest('section')).toHaveClass('b-aaaaaaaaaaaaaaaaaaaaaaaaaa');
        expect(heading.parentElement).toHaveClass('container', 'pa-container-narrow');
        expect(screen.getByText('text').tagName).toBe('STRONG');
        expect(screen.getByRole('link', { name: 'Contact' })).toHaveAttribute('href', '/contact');
        expect(screen.getByRole('link', { name: 'Contact' })).toHaveClass('btn', 'btn-accent');
    });

    it('escapes plain-text fields', () => {
        renderBlocks([{ uuid: 'eeeeeeeeeeeeeeeeeeeeeeeeee', type: 'heading', content: { text: '<img src=x onerror=alert(1)>', level: '2' } }]);
        expect(screen.getByRole('heading')).toHaveTextContent('<img src=x onerror=alert(1)>');
        expect(document.querySelector('img')).toBeNull();
    });

    it('marks external new-tab links safely', () => {
        renderBlocks([{ uuid: 'ffffffffffffffffffffffffff', type: 'button', content: { label: 'Partner', link: { href: 'https://partner.example', new_tab: true, external: true } } }]);
        const link = screen.getByRole('link', { name: /Partner/ });
        expect(link).toHaveAttribute('target', '_blank');
        expect(link).toHaveAttribute('rel', 'noopener noreferrer');
        expect(link).toHaveTextContent('(opens in new tab)');
    });

    it('renders collection items with a display mode, independent of the source', () => {
        renderBlocks([
            {
                uuid: 'gggggggggggggggggggggggggg',
                type: 'news',
                content: { heading: 'Latest news', show_date: false },
                display: { mode: 'grid', columns: { desktop: 3 } },
                items: [
                    { key: 'news:1', kind: 'news', title: 'First story', url: '/news/first', excerpt: 'Summary one', image: null, date: null, meta: {} },
                    { key: 'news:2', kind: 'news', title: 'Second story', url: '/news/second', excerpt: null, image: null, date: null, meta: {} },
                ],
            },
        ]);

        expect(screen.getByRole('heading', { level: 2, name: 'Latest news' })).toBeInTheDocument();
        expect(screen.getAllByRole('article')).toHaveLength(2);
        expect(screen.getByRole('link', { name: 'First story' })).toHaveAttribute('href', '/news/first');
    });

    it('shows the empty text when a dynamic collection has no items', () => {
        renderBlocks([{ uuid: 'hhhhhhhhhhhhhhhhhhhhhhhhhh', type: 'news', content: { empty_text: 'Nothing yet' }, items: [] }]);
        expect(screen.getByText('Nothing yet')).toBeInTheDocument();
    });

    it('renders an accessible accordion from child blocks', () => {
        renderBlocks([
            {
                uuid: 'iiiiiiiiiiiiiiiiiiiiiiiiii',
                type: 'accordion',
                content: {},
                display: { mode: 'accordion' },
                children: [
                    { uuid: 'jjjjjjjjjjjjjjjjjjjjjjjjjj', type: 'accordion-item', content: { title: 'Who can join?' }, children: [{ uuid: 'kkkkkkkkkkkkkkkkkkkkkkkkkk', type: 'rich-text', content: { html: '<p>Any school.</p>' } }] },
                ],
            },
        ]);

        const button = screen.getByRole('button', { name: 'Who can join?' });
        expect(button).toHaveAttribute('aria-expanded', 'false');
        fireEvent.click(button);
        expect(button).toHaveAttribute('aria-expanded', 'true');
        expect(screen.getByRole('region', { name: 'Who can join?' })).toHaveTextContent('Any school.');
    });

    it('never crashes on unknown block types (hidden publicly, flagged in preview)', () => {
        const { container } = renderBlocks([{ uuid: 'llllllllllllllllllllllllll', type: 'mystery', content: {} }]);
        expect(container).toBeEmptyDOMElement();

        renderBlocks([{ uuid: 'mmmmmmmmmmmmmmmmmmmmmmmmmm', type: 'mystery', content: {} }], true);
        expect(screen.getByRole('note')).toHaveTextContent('Unknown block type');
    });

    it('loads videos only after the visitor clicks play', () => {
        renderBlocks([{ uuid: 'nnnnnnnnnnnnnnnnnnnnnnnnnn', type: 'video', content: { title: 'Our story', url: { provider: 'youtube', id: 'dQw4w9WgXcQ', thumbnail: null } } }]);
        expect(document.querySelector('iframe')).toBeNull();
        fireEvent.click(screen.getByRole('button', { name: /Play video: Our story/ }));
        expect(document.querySelector('iframe').getAttribute('src')).toContain('youtube-nocookie.com/embed/dQw4w9WgXcQ');
    });
});
