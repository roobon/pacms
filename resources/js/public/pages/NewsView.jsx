import Image from '../components/common/Image.jsx';
import SeoHead from '../components/common/SeoHead.jsx';
import Breadcrumbs from '../components/common/Breadcrumbs.jsx';
import Summary from '../components/common/Summary.jsx';

/**
 * News detail: summary, image and the article text. Builder content arrives with the full
 * News module in Phase 8.
 *
 * @param {{news: Object}} props
 */
export default function NewsView({ news }) {
    const date = news.published_at
        ? new Intl.DateTimeFormat(document.documentElement.lang || 'en', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(news.published_at))
        : null;

    return (
        <article className="pb-5">
            <SeoHead seo={news.seo} />
            <header className="pa-page-header-public">
                <div className="container pa-container-narrow">
                    <Breadcrumbs crumbs={news.breadcrumbs} />
                    <p className="pa-item__meta mb-2">
                        {news.category && <span className="pa-badge-public">{news.category}</span>}
                        {date && <time dateTime={news.published_at}>{date}</time>}
                    </p>
                    <h1 className="display-6 fw-bold" tabIndex={-1}>
                        {news.title}
                    </h1>
                    <Summary html={news.excerpt_html} text={news.excerpt} className="lead pa-page-lead" />
                </div>
            </header>
            {news.featured_image && (
                <div className="container pa-container-narrow">
                    <Image image={news.featured_image} priority className="pa-page-featured" />
                </div>
            )}
            {news.body && (
                // Article text: rich text cleaned on the server (same allowlist as Text blocks).
                <div className="container pa-container-narrow">
                    <div className="pa-rich-text pa-article-body" dangerouslySetInnerHTML={{ __html: news.body }} />
                </div>
            )}
        </article>
    );
}
