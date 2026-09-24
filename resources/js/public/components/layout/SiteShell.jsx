import { useEffect, useRef } from 'react';
import { Link, Outlet, useLocation } from 'react-router';
import { useSite } from '../../hooks/useSite.js';
import { useMe } from '../../hooks/useMe.js';
import ErrorBoundary from '../common/ErrorBoundary.jsx';

/**
 * Temporary site chrome for Phase 2. In Phase 9 the header and footer become
 * CMS-managed global blocks; this component then renders those instead.
 */
export default function SiteShell() {
    const { data: site } = useSite();
    const { data: me } = useMe();
    const location = useLocation();
    const mainRef = useRef(/** @type {HTMLElement|null} */ (null));
    const firstRender = useRef(true);

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

    const siteName = site?.name ?? '';

    return (
        <>
            <a className="visually-hidden-focusable pa-skip-link" href="#main">
                Skip to main content
            </a>
            <header className="pa-site-header">
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
            </header>

            <main id="main" ref={mainRef} tabIndex={-1}>
                <ErrorBoundary resetKey={location.pathname}>
                    <Outlet />
                </ErrorBoundary>
            </main>

            <footer className="pa-site-footer">
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
            </footer>
        </>
    );
}
