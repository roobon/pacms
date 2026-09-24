import { Link } from 'react-router';
import { useSite } from '../hooks/useSite.js';

/** Rendered for unknown URLs; the server already returned HTTP 404 for the first load. */
export default function NotFoundPage() {
    const { data: site } = useSite();

    return (
        <div className="container py-5">
            <title>{`Page not found · ${site?.name ?? ''}`}</title>
            <meta name="robots" content="noindex" />
            <p className="pa-eyebrow">Error 404</p>
            <h1 tabIndex={-1}>Page not found</h1>
            <p>The page you are looking for does not exist or has been moved.</p>
            <Link to="/" className="btn btn-primary">
                Go to the homepage
            </Link>
        </div>
    );
}
