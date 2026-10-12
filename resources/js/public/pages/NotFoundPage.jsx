import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { useSite } from '../hooks/useSite.js';

/** Rendered for unknown URLs; the server already returned HTTP 404 for the first load. */
export default function NotFoundPage() {
    const { data: site } = useSite();
    const navigate = useNavigate();
    const [query, setQuery] = useState('');

    return (
        <div className="container py-5">
            <title>{`Page not found · ${site?.name ?? ''}`}</title>
            <meta name="robots" content="noindex" />
            <p className="pa-eyebrow">Error 404</p>
            <h1 tabIndex={-1}>Page not found</h1>
            <p>The page you are looking for does not exist or has been moved.</p>
            <form
                role="search"
                className="pa-search-page__form my-4"
                style={{ maxWidth: '32rem' }}
                onSubmit={(event) => {
                    event.preventDefault();
                    if (query.trim().length >= 2) navigate(`/search?q=${encodeURIComponent(query.trim())}`);
                }}
            >
                <label htmlFor="not-found-search" className="visually-hidden">
                    Search this site
                </label>
                <input id="not-found-search" type="search" className="form-control" placeholder="Search this site" value={query} onChange={(event) => setQuery(event.target.value)} />
                <button type="submit" className="btn btn-outline-primary">
                    Search
                </button>
            </form>
            <Link to="/" className="btn btn-primary">
                Go to the homepage
            </Link>
        </div>
    );
}
