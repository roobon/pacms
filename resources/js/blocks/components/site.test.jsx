import { beforeEach, describe, expect, it, vi } from 'vitest';
import { fireEvent, render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router';
import BlockRenderer from '../BlockRenderer.jsx';
import { VisitorContext } from '../common/visitor.js';

const item = (id, label, url, extra = {}) => ({ id, label, url, external: false, new_tab: false, icon: null, class: null, visibility: 'everyone', children: [], ...extra });

const menu = (style = 'horizontal') => ({
    uuid: '01k0000000000000000000menu',
    type: 'menu',
    content: { menu: 'main', style, aria_label: 'Main' },
    data: {
        items: [
            item(1, 'Home', '/'),
            item(2, 'About', null, { children: [item(3, 'Team', '/team'), item(4, 'History', '/history')] }),
            item(5, 'Donate', 'https://give.example.org', { external: true, new_tab: true, visibility: 'guests' }),
            item(6, 'My testimonials', '/account', { visibility: 'members' }),
        ],
    },
});

function show(nodes, { signedIn = false, path = '/' } = {}) {
    return render(
        <MemoryRouter initialEntries={[path]}>
            <VisitorContext.Provider value={{ signedIn }}>
                <BlockRenderer nodes={nodes} />
            </VisitorContext.Provider>
        </MemoryRouter>,
    );
}

describe('MenuBlock', () => {
    beforeEach(() => {
        window.matchMedia = vi.fn().mockReturnValue({ matches: false, addEventListener: vi.fn(), removeEventListener: vi.fn() });
    });

    it('shows guest items to visitors and member items once signed in', () => {
        const { unmount } = show([menu()]);
        expect(screen.getAllByText('Donate').length).toBeGreaterThan(0);
        expect(screen.queryByText('My testimonials')).toBeNull();
        unmount();

        show([menu()], { signedIn: true });
        expect(screen.queryByText('Donate')).toBeNull();
        expect(screen.getAllByText('My testimonials').length).toBeGreaterThan(0);
    });

    it('opens sub-menus with a button, and Esc closes them and returns focus', () => {
        show([menu()]);
        const nav = screen.getByRole('navigation', { name: 'Main' });
        const button = nav.querySelector('.pa-menu__bar button');
        const panel = document.getElementById(button.getAttribute('aria-controls'));

        expect(button.getAttribute('aria-expanded')).toBe('false');
        expect(panel.hidden).toBe(true);

        fireEvent.click(button);
        expect(button.getAttribute('aria-expanded')).toBe('true');
        expect(panel.hidden).toBe(false);

        panel.querySelector('a').focus();
        fireEvent.keyDown(panel.querySelector('a'), { key: 'Escape' });
        expect(panel.hidden).toBe(true);
        expect(document.activeElement).toBe(button);
    });

    it('marks the current page and opens new tabs safely', () => {
        show([menu()], { path: '/team' });
        const team = document.querySelector('.pa-menu__bar a[href="/team"]');
        expect(team.getAttribute('aria-current')).toBe('page');
        const donate = document.querySelector('.pa-menu__bar a[href="https://give.example.org"]');
        expect(donate.getAttribute('rel')).toContain('noopener');
        expect(donate.textContent).toContain('opens in new tab');
    });

    it('moves into a drawer on small screens; Esc closes it and returns focus', () => {
        show([menu()]);
        const toggle = screen.getByRole('button', { name: 'Menu' });
        fireEvent.click(toggle);

        const drawer = screen.getByRole('dialog', { name: 'Main navigation' });
        expect(toggle.getAttribute('aria-expanded')).toBe('true');
        expect(drawer.contains(document.activeElement)).toBe(true);

        // Sub-levels are accordions.
        const about = [...drawer.querySelectorAll('button')].find((b) => b.textContent.includes('About'));
        fireEvent.click(about);
        expect(about.getAttribute('aria-expanded')).toBe('true');

        fireEvent.keyDown(drawer, { key: 'Escape' });
        expect(screen.queryByRole('dialog')).toBeNull();
        expect(document.activeElement).toBe(toggle);
    });

    it('lists every level in a vertical menu', () => {
        show([menu('vertical')]);
        const nav = screen.getByRole('navigation', { name: 'Main' });
        expect([...nav.querySelectorAll('a')].map((a) => a.textContent)).toEqual(['Home', 'Team', 'History', expect.stringContaining('Donate')]);
    });
});

describe('Site blocks', () => {
    it('shows the logo, contact details, social links and copyright from the server data', () => {
        show([
            { uuid: '01k0000000000000000000logo', type: 'site-logo', content: { show_name: true }, data: { name: 'Aurora', logo: null } },
            { uuid: '01k000000000000000000contact', type: 'contact-info', content: {}, data: { email: 'hi@example.org', phone: '+880 1700 000000' } },
            { uuid: '01k0000000000000000000social', type: 'social-links', content: { style: 'icons' }, data: { links: [{ network: 'facebook', label: 'Facebook', icon: 'bi-facebook', url: 'https://facebook.com/x' }] } },
            { uuid: '01k000000000000000000copyrt', type: 'copyright', content: {}, data: { text: '© 2026 Aurora' } },
        ]);
        expect(screen.getByRole('link', { name: 'Aurora' }).getAttribute('href')).toBe('/');
        expect(screen.getByRole('link', { name: 'hi@example.org' }).getAttribute('href')).toBe('mailto:hi@example.org');
        expect(screen.getByRole('link', { name: '+880 1700 000000' }).getAttribute('href')).toBe('tel:+8801700000000');
        expect(screen.getByRole('link', { name: /Facebook/ }).getAttribute('href')).toBe('https://facebook.com/x');
        expect(screen.getByText('© 2026 Aurora')).toBeTruthy();
    });
});
