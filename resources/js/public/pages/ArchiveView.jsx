import { Link, useLocation } from 'react-router';
import ItemsDisplay from '../../blocks/display/ItemsDisplay.jsx';
import Breadcrumbs from '../components/common/Breadcrumbs.jsx';
import SeoHead from '../components/common/SeoHead.jsx';

/**
 * Listing of a content module (/news, /events): views (upcoming / past), category filter
 * and pagination, all in the URL so every state can be shared and bookmarked.
 *
 * @param {{archive: Object}} props
 */
export default function ArchiveView({ archive }) {
    const { pathname } = useLocation();
    const link = (changes) => {
        const params = new URLSearchParams();
        const next = { view: archive.view, category: archive.category, page: 1, ...changes };
        if (next.view && next.view !== Object.keys(archive.views ?? {})[0]) params.set('view', next.view);
        if (next.category) params.set('category', next.category);
        if (next.page > 1) params.set('page', String(next.page));
        const query = params.toString();
        return query ? `${pathname}?${query}` : pathname;
    };
    const { page, pages } = archive.pagination;

    return (
        <div className="pb-5">
            <SeoHead seo={archive.seo} />
            <header className="pa-page-header-public">
                <div className="container">
                    <Breadcrumbs crumbs={archive.breadcrumbs} />
                    <h1 className="display-6 fw-bold" tabIndex={-1}>
                        {archive.title}
                    </h1>
                </div>
            </header>
            <div className="container">
                {(Object.keys(archive.views ?? {}).length > 0 || archive.categories.length > 0) && (
                    <nav className="pa-archive-filters" aria-label={`Filter ${archive.title.toLowerCase()}`}>
                        {Object.entries(archive.views ?? {}).map(([key, label]) => (
                            <Link key={key} to={link({ view: key })} className={`pa-chip${archive.view === key ? ' is-active' : ''}`} aria-current={archive.view === key ? 'page' : undefined}>
                                {label}
                            </Link>
                        ))}
                        {archive.categories.length > 0 && (
                            <>
                                <Link to={link({ category: null })} className={`pa-chip${!archive.category ? ' is-active' : ''}`} aria-current={!archive.category ? 'page' : undefined}>
                                    All categories
                                </Link>
                                {archive.categories.map((category) => (
                                    <Link key={category.slug} to={link({ category: category.slug })} className={`pa-chip${archive.category === category.slug ? ' is-active' : ''}`} aria-current={archive.category === category.slug ? 'page' : undefined}>
                                        {category.name}
                                    </Link>
                                ))}
                            </>
                        )}
                    </nav>
                )}

                {archive.items.length === 0 ? (
                    <p className="text-body-secondary py-4">{archive.view === 'upcoming' ? 'No upcoming events right now. Have a look at past events.' : 'Nothing here yet.'}</p>
                ) : (
                    <ItemsDisplay items={archive.items} display={{ mode: 'grid', columns: { desktop: 3, tablet: 2, mobile: 1 } }} headingLevel={2} />
                )}

                {pages > 1 && (
                    <nav className="pa-pagination" aria-label="Pages">
                        {page > 1 && (
                            <Link to={link({ page: page - 1 })} className="btn btn-outline-primary" rel="prev">
                                <i className="bi bi-arrow-left" aria-hidden="true" /> Previous
                            </Link>
                        )}
                        <span className="small text-body-secondary">
                            Page {page} of {pages}
                        </span>
                        {page < pages && (
                            <Link to={link({ page: page + 1 })} className="btn btn-outline-primary" rel="next">
                                Next <i className="bi bi-arrow-right" aria-hidden="true" />
                            </Link>
                        )}
                    </nav>
                )}
            </div>
        </div>
    );
}
