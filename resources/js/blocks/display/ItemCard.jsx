import Image from '../common/Image.jsx';
import SmartLink from '../common/SmartLink.jsx';

/**
 * One normalised item as a card. The whole card is clickable through a single stretched
 * link on the title (one link per card, readable link text).
 *
 * @param {{item: Object, layout?: 'card'|'list'|'featured', showExcerpt?: boolean, showDate?: boolean, showImage?: boolean,
 *   headingLevel?: number, cardStyle?: string, ratio?: string}} props
 */
export default function ItemCard({ item, layout = 'card', showExcerpt = true, showDate = true, showImage = true, headingLevel = 3, cardStyle = 'elevated', ratio = '16:9' }) {
    const Heading = `h${Math.min(6, Math.max(2, headingLevel))}`;
    const link = item.link ?? (item.url ? { href: item.url, external: Boolean(item.external), new_tab: Boolean(item.external) } : null);
    // Modules may send their own date label (events: "12–14 March 2027, 10:00").
    const date = showDate && item.date ? (item.meta?.when ?? formatDate(item.date)) : null;

    return (
        <article className={`pa-item pa-item--${layout} pa-item--${cardStyle}`}>
            {showImage && item.image && (
                <div className={`pa-item__media pa-ratio pa-ratio--${String(ratio).replace(':', 'x')}`}>
                    <Image image={{ ...item.image, alt: '' }} />
                </div>
            )}
            <div className="pa-item__body">
                {item.icon && <i className={`bi ${item.icon} pa-item__icon`} aria-hidden="true" />}
                {(date || item.meta?.category || item.meta?.status) && (
                    <p className="pa-item__meta">
                        {item.meta?.category && <span className="pa-badge-public">{item.meta.category}</span>}
                        {item.meta?.status && <span className="pa-badge-public pa-badge-public--muted">{item.meta.status}</span>}
                        {date && <time dateTime={item.date}>{date}</time>}
                    </p>
                )}
                <Heading className="pa-item__title">
                    {link ? (
                        <SmartLink link={link} className="stretched-link">
                            {item.title}
                        </SmartLink>
                    ) : (
                        item.title
                    )}
                </Heading>
                {item.meta?.role && <p className="pa-item__role">{item.meta.role}</p>}
                {item.meta?.place && (
                    <p className="pa-item__place">
                        <i className="bi bi-geo-alt" aria-hidden="true" /> {item.meta.place}
                    </p>
                )}
                {showExcerpt && item.excerpt && <p className="pa-item__excerpt">{item.excerpt}</p>}
            </div>
        </article>
    );
}

function formatDate(value) {
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return null;
    return new Intl.DateTimeFormat(document.documentElement.lang || 'en', { day: 'numeric', month: 'long', year: 'numeric' }).format(date);
}
