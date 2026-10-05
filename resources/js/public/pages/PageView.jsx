import BlockRenderer from '../../blocks/BlockRenderer.jsx';
import BlockStyles from '../../blocks/BlockStyles.jsx';
import { hasHeadingOne } from '../../blocks/utils.js';
import Breadcrumbs from '../components/common/Breadcrumbs.jsx';
import Image from '../components/common/Image.jsx';
import SeoHead from '../components/common/SeoHead.jsx';

/**
 * Renders a CMS page: blocks when the page has them, otherwise title, summary and
 * featured image. When the blocks provide their own H1 (e.g. a hero), the default page
 * header is left out so there is exactly one H1.
 *
 * @param {{page: import('../utils/initialData.js').PageData}} props
 */
export default function PageView({ page }) {
    const fullWidth = page.template === 'full-width';
    const blocks = page.blocks ?? [];
    const showHeader = !hasHeadingOne(blocks);

    return (
        <article>
            <SeoHead seo={page.seo} />
            {showHeader && (
                <header className="pa-page-header-public">
                    <div className={fullWidth ? 'container-fluid px-4' : 'container'}>
                        <Breadcrumbs crumbs={page.breadcrumbs} />
                        <h1 className="display-6 fw-bold" tabIndex={-1}>
                            {page.title}
                        </h1>
                        {page.excerpt && <p className="lead mb-0 pa-page-lead">{page.excerpt}</p>}
                    </div>
                </header>
            )}

            {page.featured_image && blocks.length === 0 && (
                <div className={fullWidth ? '' : 'container'}>
                    <Image image={page.featured_image} priority className="pa-page-featured" sizes={fullWidth ? '100vw' : '(min-width: 1320px) 1280px, 100vw'} />
                </div>
            )}

            {blocks.length > 0 && (
                <div className="pa-page-blocks">
                    <BlockStyles nodes={blocks} />
                    <BlockRenderer nodes={blocks} />
                </div>
            )}
        </article>
    );
}

