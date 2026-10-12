import { useQuery } from '@tanstack/react-query';
import { useId, useRef, useState } from 'react';
import { Link, useSearchParams } from 'react-router';
import { api } from '../api/client.js';
import { useSite } from '../hooks/useSite.js';
import { highlight } from '../utils/highlight.jsx';

const formatDate = (value) => new Intl.DateTimeFormat(document.documentElement.lang || 'en', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(value));

/**
 * /search?q=…&type=news&page=2 — site search (published content only). The address holds the
 * search, so results can be shared and the back button works.
 */
export default function SearchPage() {
    const { data: site } = useSite();
    const [params, setParams] = useSearchParams();
    const query = (params.get('q') ?? '').trim();
    const type = params.get('type') ?? '';
    const page = Math.max(1, Number(params.get('page')) || 1);
    const headingRef = useRef(null);

    const search = useQuery({
        queryKey: ['search', query, type, page],
        queryFn: async () => (await api.get('/search', { params: { q: query, page, ...(type ? { 'type[]': type } : {}) } })).data,
        enabled: query.length >= 2,
        placeholderData: (previous) => previous,
        staleTime: 60 * 1000,
    });

    const go = (changes) => {
        const next = { q: query, type, page: 1, ...changes };
        setParams(Object.fromEntries(Object.entries(next).filter(([, value]) => value !== '' && value !== 1 && value !== null)));
        window.scrollTo(0, 0);
    };

    const meta = search.data?.meta;
    const results = search.data?.data ?? [];

    return (
        <div className="container py-5 pa-search-page">
            <title>{`${query ? `${query} · ` : ''}Search · ${site?.name ?? ''}`}</title>
            <meta name="robots" content="noindex" />
            <h1 ref={headingRef} tabIndex={-1} className="mb-4">
                Search
            </h1>

            {/* Keyed by the query: a search started from the header refills the box. */}
            <SearchForm key={query} initial={query} onSearch={(q) => go({ q })} />

            {meta?.types && (
                <nav className="pa-chips my-4" aria-label="Show results from">
                    <button type="button" className={`pa-chip${type === '' ? ' is-active' : ''}`} aria-pressed={type === ''} onClick={() => go({ type: '' })}>
                        Everything
                    </button>
                    {Object.entries(meta.types).map(([key, label]) => (
                        <button key={key} type="button" className={`pa-chip${type === key ? ' is-active' : ''}`} aria-pressed={type === key} onClick={() => go({ type: key })}>
                            {label}
                        </button>
                    ))}
                </nav>
            )}

            <div aria-live="polite" className="visually-hidden">
                {meta && query ? `${meta.total} ${meta.total === 1 ? 'result' : 'results'} for ${query}` : ''}
            </div>

            {query.length < 2 ? (
                <p className="text-body-secondary mt-4">Type at least two letters, for example a programme, a place or a topic.</p>
            ) : search.isError ? (
                <div className="alert alert-warning mt-4" role="alert">
                    The search could not be completed.{' '}
                    <button type="button" className="btn btn-link p-0 align-baseline" onClick={() => search.refetch()}>
                        Try again
                    </button>
                </div>
            ) : search.isPending ? (
                <ul className="list-unstyled mt-4" aria-busy="true">
                    {[1, 2, 3].map((n) => (
                        <li key={n} className="pa-search-result pa-skeleton-block" />
                    ))}
                </ul>
            ) : results.length === 0 ? (
                <div className="mt-4">
                    <p className="fw-semibold">No results for “{query}”{type ? ` in ${meta?.types?.[type] ?? type}` : ''}.</p>
                    <ul className="text-body-secondary">
                        <li>Check the spelling, or try fewer or more general words.</li>
                        {type && (
                            <li>
                                <button type="button" className="btn btn-link p-0 align-baseline" onClick={() => go({ type: '' })}>
                                    Search everything
                                </button>{' '}
                                instead.
                            </li>
                        )}
                    </ul>
                </div>
            ) : (
                <>
                    <p className="text-body-secondary mt-4" aria-hidden="true">
                        {meta.total} {meta.total === 1 ? 'result' : 'results'}
                    </p>
                    <ol className="list-unstyled pa-search-results" aria-busy={search.isFetching}>
                        {results.map((result) => (
                            <li key={result.url} className="pa-search-result">
                                <p className="pa-item__meta mb-1">
                                    <span className="pa-badge-public">{result.type_label}</span>
                                    {result.date && <time dateTime={result.date}>{formatDate(result.date)}</time>}
                                </p>
                                <h2 className="h5 mb-1">
                                    <Link to={result.url}>{result.title}</Link>
                                </h2>
                                {result.excerpt && <p className="mb-0 text-body-secondary">{highlight(result.excerpt, query)}</p>}
                            </li>
                        ))}
                    </ol>
                    {meta.last_page > 1 && (
                        <nav className="pa-pagination" aria-label="Result pages">
                            <button type="button" className="btn btn-outline-primary" disabled={page <= 1} onClick={() => go({ page: page - 1 })}>
                                <i className="bi bi-arrow-left" aria-hidden="true" /> Previous
                            </button>
                            <span className="small">
                                Page {meta.current_page} of {meta.last_page}
                            </span>
                            <button type="button" className="btn btn-outline-primary" disabled={page >= meta.last_page} onClick={() => go({ page: page + 1 })}>
                                Next <i className="bi bi-arrow-right" aria-hidden="true" />
                            </button>
                        </nav>
                    )}
                </>
            )}
        </div>
    );
}

function SearchForm({ initial, onSearch }) {
    const [draft, setDraft] = useState(initial);
    const inputId = useId();

    return (
        <form
            role="search"
            className="pa-search-page__form"
            onSubmit={(event) => {
                event.preventDefault();
                onSearch(draft.trim());
            }}
        >
            <label htmlFor={inputId} className="visually-hidden">
                Search this site
            </label>
            <input id={inputId} type="search" className="form-control form-control-lg" value={draft} onChange={(event) => setDraft(event.target.value)} placeholder="What are you looking for?" minLength={2} maxLength={100} autoFocus={!initial} />
            <button type="submit" className="btn btn-primary btn-lg">
                <i className="bi bi-search" aria-hidden="true" />
                <span className="ms-2">Search</span>
            </button>
        </form>
    );
}
