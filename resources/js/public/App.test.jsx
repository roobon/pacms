import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { QueryClient } from '@tanstack/react-query';
import App from './App.jsx';
import { readInitialData } from './utils/initialData.js';

vi.mock('./api/client.js', async (importOriginal) => {
    const original = await importOriginal();
    return {
        ...original,
        // Guests: /me answers 401.
        api: { get: vi.fn(() => Promise.reject({ response: { status: 401 } })) },
    };
});

const site = {
    name: 'Example Organization',
    tagline: 'Learning for a greener future',
    description: '',
    contact: { email: 'info@example.org', phone: '', address: '' },
    timezone: 'Asia/Dhaka',
    locale: 'en',
    url: 'http://example.test',
    theme: { stylesheet: '/storage/theme/tokens.css' },
    features: { registration: true },
};

function renderAt(path) {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    return render(
        <MemoryRouter initialEntries={[path]}>
            <App initialData={{ site, route: { path, status: 200 } }} queryClient={queryClient} />
        </MemoryRouter>,
    );
}

describe('public SPA', () => {
    it('renders the homepage from server-provided site data without hard-coded content', () => {
        renderAt('/');
        expect(screen.getByRole('heading', { level: 1, name: 'Example Organization' })).toBeInTheDocument();
        expect(screen.getByText('Learning for a greener future')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute('href', '#main');
    });

    it('shows the not-found page for unknown paths', () => {
        renderAt('/does-not-exist');
        expect(screen.getByRole('heading', { level: 1, name: 'Page not found' })).toBeInTheDocument();
    });

    it('shows a sign-in link for guests', async () => {
        renderAt('/');
        expect(await screen.findByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/account/login');
    });
});

describe('readInitialData', () => {
    it('parses the embedded JSON payload', () => {
        document.body.innerHTML = '<script id="pacms-initial" type="application/json">{"site":{"name":"X"},"route":{"path":"/","status":200}}</script>';
        expect(readInitialData(document)).toEqual({ site: { name: 'X' }, route: { path: '/', status: 200 } });
    });

    it('tolerates a missing or invalid payload', () => {
        document.body.innerHTML = '<script id="pacms-initial" type="application/json">{not json</script>';
        expect(readInitialData(document)).toEqual({ site: null, route: null });
        document.body.innerHTML = '';
        expect(readInitialData(document)).toEqual({ site: null, route: null });
    });
});
