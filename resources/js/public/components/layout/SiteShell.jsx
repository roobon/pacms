import { useEffect, useMemo, useRef, useState } from 'react';
import { Link, Outlet, useLocation } from 'react-router';
import BlockRenderer from '../../../blocks/BlockRenderer.jsx';
import BlockStyles from '../../../blocks/BlockStyles.jsx';
import { VisitorContext } from '../../../blocks/common/visitor.js';
import { ChromeContext } from '../../contexts/ChromeContext.jsx';
import { useSite } from '../../hooks/useSite.js';
import { useMe } from '../../hooks/useMe.js';
import ErrorBoundary from '../common/ErrorBoundary.jsx';

/**
 * Site chrome (Phase 9): the header and footer are global blocks chosen in Design → Header
 * & footer; a page may choose others or none. Without one, a plain header and footer with
 * the site name are shown.
 */
export default function SiteShell() {
    const { data: site } = useSite();
    const { data: me } = useMe();
    const location = useLocation();
    const mainRef = useRef(/** @type {HTMLElement|null} */ (null));
    const firstRender = useRef(true);
    const [pageChrome, setPageChrome] = useState({});
    const [scrolled, setScrolled] = useState(false);

    // After client-side navigation, move focus to the new page's heading so
    // keyboard and screen-reader users start at the new content.
    useEffect(() => {
        if (firstRender.current) {
            firstRender.current = false;
            return;
        }
        const heading = mainRef.current?.querySelector('h1');
        (heading ?? mainRef.current)?.focus?.();
        window.scrollTo(0, 0);
    }, [location.pathname]);

    const chrome = site?.chrome ?? {};
    const header = pageChrome.header === 'none' ? null : Array.isArray(pageChrome.header) ? pageChrome.header : chrome.header;
    const footer = pageChrome.footer === 'none' ? null : Array.isArray(pageChrome.footer) ? pageChrome.footer : chrome.footer;
    const overHero = Boolean(chrome.transparent && pageChrome.startsWithHero && header);

    // The see-through header gets its background back once the page scrolls.
    useEffect(() => {
        if (!overHero) return undefined;
        const onScroll = () => setScrolled(window.scrollY > 40);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });
        return () => window.removeEventListener('scroll', onScroll);
    }, [overHero]);

    const visitor = useMemo(() => ({ signedIn: Boolean(me) }), [me]);
    const context = useMemo(() => ({ setPageChrome }), []);
    const siteName = site?.name ?? '';
    const headerClass = ['pa-site-header', header ? 'pa-site-header--blocks' : '', chrome.sticky === false ? 'is-static' : '', overHero ? 'is-over-hero' : '', overHero && scrolled ? 'is-scrolled' : '']
        .filter(Boolean)
        .join(' ');

    return (
        <VisitorContext.Provider value={visitor}>
            <ChromeContext.Provider value={context}>
                <a className="visually-hidden-focusable pa-skip-link" href="#main">
                    Skip to main content
                </a>
                {pageChrome.header !== 'none' && (
                    <header className={headerClass}>
                        {header ? (
                            <>
                                <BlockStyles nodes={header} />
                                <BlockRenderer nodes={header} />
                            </>
                        ) : (
                            <div className="container pa-site-header__inner">
                                <Link to="/" className="pa-site-header__brand">
                                    {siteName}
                                </Link>
                                <nav aria-label="Account">
                                    {me ? (
                                        <Link to="/account" className="btn btn-outline-primary btn-sm">
                                            <i className="bi bi-person-circle me-1" aria-hidden="true" />
                                            My account
                                        </Link>
                                    ) : (
                                        <Link to="/account/login" className="btn btn-outline-primary btn-sm">
                                            Sign in
                                        </Link>
                                    )}
                                </nav>
                            </div>
                        )}
                    </header>
                )}

                <main id="main" ref={mainRef} tabIndex={-1}>
                    <ErrorBoundary resetKey={location.pathname}>
                        <Outlet />
                    </ErrorBoundary>
                </main>

                {pageChrome.footer !== 'none' && (
                    <footer className={`pa-site-footer${footer ? ' pa-site-footer--blocks' : ''}`}>
                        {footer ? (
                            <>
                                <BlockStyles nodes={footer} />
                                <BlockRenderer nodes={footer} />
                            </>
                        ) : (
                            <div className="container">
                                <p className="fw-semibold mb-1">{siteName}</p>
                                {site?.contact?.email && (
                                    <p className="mb-1">
                                        <a href={`mailto:${site.contact.email}`}>{site.contact.email}</a>
                                    </p>
                                )}
                                <p className="small mb-0">
                                    © {new Date().getFullYear()} {siteName}
                                </p>
                            </div>
                        )}
                    </footer>
                )}
            </ChromeContext.Provider>
        </VisitorContext.Provider>
    );
}
