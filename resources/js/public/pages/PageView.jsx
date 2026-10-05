import { Link } from 'react-router';
import Image from '../components/common/Image.jsx';
import SeoHead from '../components/common/SeoHead.jsx';

/**
 * Renders a CMS page payload. The block body is rendered by the Block Engine from Phase 4;
 * until then a page shows its title, summary and featured image.
 *
 * @param {{page: import('../utils/initialData.js').PageData}} props
 */
export default function PageView({ page }) {
    const fullWidth = page.template === 'full-width';

    return (
        <article>
            <SeoHead seo={page.seo} />
            <header className="pa-page-header-public">
                <div className={fullWidth ? 'container-fluid px-4' : 'container'}>
                    {page.breadcrumbs.length > 1 && (
                        <nav aria-label="Breadcrumb">
                            <ol className="breadcrumb small">
                                {page.breadcrumbs.map((crumb, index) =>
                                    index === page.breadcrumbs.length - 1 ? (
                                        <li key={crumb.url} className="breadcrumb-item active" aria-current="page">
                                            {crumb.title}
                                        </li>
                                    ) : (
                                        <li key={crumb.url} className="breadcrumb-item">
                                            <Link to={crumb.url}>{crumb.title}</Link>
                                        </li>
                                    ),
                                )}
                            </ol>
                        </nav>
                    )}
                    <h1 className="display-6 fw-bold" tabIndex={-1}>
                        {page.title}
                    </h1>
                    {page.excerpt && <p className="lead mb-0 pa-page-lead">{page.excerpt}</p>}
                </div>
            </header>

            {page.featured_image && (
                <div className={fullWidth ? '' : 'container'}>
                    <Image image={page.featured_image} priority className="pa-page-featured" sizes={fullWidth ? '100vw' : '(min-width: 1320px) 1280px, 100vw'} />
                </div>
            )}

            {/* Block Engine output arrives in Phase 4. */}
            {page.blocks.length > 0 && <div className="container py-5" data-blocks={page.blocks.length} />}
        </article>
    );
}
