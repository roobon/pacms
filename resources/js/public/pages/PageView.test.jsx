import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import PageView from './PageView.jsx';

function renderPage(overrides = {}) {
    const page = { title: 'About us', excerpt: 'Who we are.', template: 'default', breadcrumbs: [], seo: {}, blocks: [], ...overrides };
    return render(
        <MemoryRouter>
            <PageView page={page} />
        </MemoryRouter>,
    );
}

describe('PageView', () => {
    it('shows the page header by default', () => {
        renderPage();
        expect(screen.getByRole('heading', { level: 1, name: 'About us' })).not.toHaveClass('visually-hidden');
        expect(screen.getByText('Who we are.')).toBeInTheDocument();
    });

    it('keeps a visually hidden H1 when the title is turned off', () => {
        renderPage({ show_title: false });
        expect(screen.getByRole('heading', { level: 1, name: 'About us' })).toHaveClass('visually-hidden');
        expect(screen.queryByText('Who we are.')).not.toBeInTheDocument();
    });

    it('leaves the H1 to the blocks when they provide one', () => {
        renderPage({
            show_title: false,
            blocks: [{ uuid: 'aaaaaaaaaaaaaaaaaaaaaaaaaa', type: 'heading', content: { text: 'Welcome', level: '1' } }],
        });
        expect(screen.getAllByRole('heading', { level: 1 })).toHaveLength(1);
        expect(screen.getByRole('heading', { level: 1, name: 'Welcome' })).toBeInTheDocument();
    });
});
