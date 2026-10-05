import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import { QueryClient } from '@tanstack/react-query';
import App from './App.jsx';
import { api } from './api/client.js';
import { readInitialData } from './utils/initialData.js';

vi.mock('./api/client.js', async (importOriginal) => {
    const original = await importOriginal();
    return { ...original, api: { get: vi.fn() } };
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

/** @returns {import('./utils/initialData.js').PageData} */
function pageData(overrides = {}) {
    return {
        type: 'page',
        id: 7,
        title: 'Our Team',
        path: '/about/team',
        url: 'http://example.test/about/team',
        is_home: false,
        excerpt: 'The people behind our work.',
        featured_image: {
            id: 3, src: '/storage/media/a.jpg', srcset: '/storage/media/a-640.webp 640w', sizes: '100vw',
            width: 1600, height: 900, alt: 'Team photo', placeholder: null, focal_point: null,
        },
        template: 'default',
        published_at: null,
        updated_at: null,
        breadcrumbs: [
            { title: 'Home', url: '/' },
            { title: 'About', url: '/about' },
            { title: 'Our Team', url: '/about/team' },
        ],
        seo: { title: 'Our Team · Example', description: 'The people', canonical: 'http://example.test/about/team', robots: 'index,follow', og: {} },
        blocks: [],
        ...overrides,
    };
}

function renderAt(path, { status = 200, page = null, preview = false } = {}) {
    const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } });
    return render(
        <MemoryRouter initialEntries={[path]}>
            <App initialData={{ site, route: { path, status, preview }, page }} queryClient={queryClient} />
        </MemoryRouter>,
    );
}

beforeEach(() => {
    vi.mocked(api.get).mockReset();
    // Default: guests (/me → 401), unknown paths → 404.
    vi.mocked(api.get).mockImplementation((url) =>
        Promise.reject({ response: { status: url === '/me' ? 401 : 404 } }),
    );
});

describe('public SPA', () => {
    it('renders the default homepage from server-provided site data', () => {
        renderAt('/');
        expect(screen.getByRole('heading', { level: 1, name: 'Example Organization' })).toBeInTheDocument();
        expect(screen.getByText('Learning for a greener future')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Skip to main content' })).toHaveAttribute('href', '#main');
    });

    it('renders a CMS page from the initial payload without an API request', () => {
        renderAt('/about/team', { page: pageData() });

        expect(screen.getByRole('heading', { level: 1, name: 'Our Team' })).toBeInTheDocument();
        expect(screen.getByText('The people behind our work.')).toBeInTheDocument();
        const image = screen.getByRole('img', { name: 'Team photo' });
        expect(image).toHaveAttribute('srcset', '/storage/media/a-640.webp 640w');
        expect(image).toHaveAttribute('width', '1600');
        const breadcrumb = screen.getByRole('navigation', { name: 'Breadcrumb' });
        expect(breadcrumb).toHaveTextContent('About');
        expect(vi.mocked(api.get)).not.toHaveBeenCalledWith('/resolve', expect.anything());
    });

    it('resolves other paths through the API', async () => {
        vi.mocked(api.get).mockImplementation((url) =>
            url === '/resolve'
                ? Promise.resolve({ data: { kind: 'page', data: pageData({ title: 'Contact', breadcrumbs: [], featured_image: null }) } })
                : Promise.reject({ response: { status: 401 } }),
        );

        renderAt('/contact');
        expect(await screen.findByRole('heading', { level: 1, name: 'Contact' })).toBeInTheDocument();
        expect(vi.mocked(api.get)).toHaveBeenCalledWith('/resolve', { params: { path: '/contact' } });
    });

    it('shows the not-found page when the server said 404', () => {
        renderAt('/does-not-exist', { status: 404 });
        expect(screen.getByRole('heading', { level: 1, name: 'Page not found' })).toBeInTheDocument();
    });

    it('shows the not-found page when the API cannot resolve a path', async () => {
        renderAt('/somewhere-else', { status: 200 });
        expect(await screen.findByRole('heading', { level: 1, name: 'Page not found' })).toBeInTheDocument();
    });

    it('shows the preview banner for previews', () => {
        renderAt('/preview/pages/7', { page: pageData({ preview: true }), preview: true });
        expect(screen.getByRole('status')).toHaveTextContent('Preview');
        expect(screen.getByRole('heading', { level: 1, name: 'Our Team' })).toBeInTheDocument();
    });

    it('shows a sign-in link for guests', async () => {
        renderAt('/');
        expect(await screen.findByRole('link', { name: 'Sign in' })).toHaveAttribute('href', '/account/login');
    });
});

describe('readInitialData', () => {
    it('parses the embedded JSON payload', () => {
        document.body.innerHTML = '<script id="pacms-initial" type="application/json">{"site":{"name":"X"},"route":{"path":"/","status":200}}</script>';
        expect(readInitialData(document)).toEqual({ site: { name: 'X' }, route: { path: '/', status: 200 }, page: null });
    });

    it('tolerates a missing or invalid payload', () => {
        document.body.innerHTML = '<script id="pacms-initial" type="application/json">{not json</script>';
        expect(readInitialData(document)).toEqual({ site: null, route: null, page: null });
        document.body.innerHTML = '';
        expect(readInitialData(document)).toEqual({ site: null, route: null, page: null });
    });
});
