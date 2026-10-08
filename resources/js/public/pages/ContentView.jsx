import BlockRenderer from '../../blocks/BlockRenderer.jsx';
import BlockStyles from '../../blocks/BlockStyles.jsx';
import Breadcrumbs from '../components/common/Breadcrumbs.jsx';
import Image from '../components/common/Image.jsx';
import SeoHead from '../components/common/SeoHead.jsx';
import Summary from '../components/common/Summary.jsx';

/**
 * Detail page of any content module (news, events…): header, image, article text, builder
 * content and, when chosen, a sidebar (a global block) on the left or right.
 *
 * @param {{item: Object}} props
 */
export default function ContentView({ item }) {
    const date = item.type !== 'events' && item.published_at ? formatDate(item.published_at) : null;
    const sidebar = item.sidebar?.blocks?.length ? item.sidebar : null;
    const blocks = item.blocks ?? [];

    const main = (
        <>
            {item.featured_image && <Image image={item.featured_image} priority className="pa-page-featured" sizes={sidebar ? '(min-width: 992px) 66vw, 100vw' : '(min-width: 1320px) 1280px, 100vw'} />}
            {item.event && <EventDetails event={item.event} />}
            {item.body && (
                // Article text: rich text cleaned on the server (same allowlist as Text blocks).
                <div className="pa-rich-text pa-article-body" dangerouslySetInnerHTML={{ __html: item.body }} />
            )}
            {blocks.length > 0 && (
                <div className="pa-content-blocks">
                    <BlockStyles nodes={blocks} />
                    <BlockRenderer nodes={blocks} />
                </div>
            )}
        </>
    );

    return (
        <article className="pb-5">
            <SeoHead seo={item.seo} />
            <header className="pa-page-header-public">
                <div className={`container${sidebar ? '' : ' pa-container-narrow'}`}>
                    <Breadcrumbs crumbs={item.breadcrumbs} />
                    <p className="pa-item__meta mb-2">
                        {item.category && <span className="pa-badge-public">{item.category}</span>}
                        {date && <time dateTime={item.published_at}>{date}</time>}
                        {item.event && !item.event.upcoming && <span className="pa-badge-public pa-badge-public--muted">Past event</span>}
                    </p>
                    <h1 className="display-6 fw-bold" tabIndex={-1}>
                        {item.title}
                    </h1>
                    <Summary html={item.excerpt_html} text={item.excerpt} className="lead pa-page-lead" />
                </div>
            </header>

            {sidebar ? (
                <div className={`container pa-with-sidebar pa-with-sidebar--${sidebar.position}`}>
                    <div className="pa-with-sidebar__main">{main}</div>
                    <aside className="pa-with-sidebar__aside" aria-label="Sidebar">
                        <BlockStyles nodes={sidebar.blocks} />
                        <BlockRenderer nodes={sidebar.blocks} />
                    </aside>
                </div>
            ) : (
                <div className="container pa-container-narrow">{main}</div>
            )}
        </article>
    );
}

/** When, where and how to take part in an event. */
function EventDetails({ event }) {
    return (
        <section className="pa-event-details" aria-label="Event details">
            <dl className="mb-0">
                <div>
                    <dt>
                        <i className="bi bi-calendar-event" aria-hidden="true" /> When
                    </dt>
                    <dd>
                        <time dateTime={event.start_at}>{event.when}</time>
                        {!event.all_day && event.timezone && <span className="text-body-secondary small"> ({event.timezone})</span>}
                    </dd>
                </div>
                {(event.venue || event.address) && (
                    <div>
                        <dt>
                            <i className="bi bi-geo-alt" aria-hidden="true" /> Where
                        </dt>
                        <dd>
                            {event.venue && <span className="d-block fw-semibold">{event.venue}</span>}
                            {event.address && <span className="d-block pa-pre-line">{event.address}</span>}
                            {event.map_url && (
                                <a href={event.map_url} target="_blank" rel="noopener noreferrer">
                                    Open map<span className="visually-hidden"> (opens in new tab)</span>
                                </a>
                            )}
                        </dd>
                    </div>
                )}
                {event.organizer && (
                    <div>
                        <dt>
                            <i className="bi bi-people" aria-hidden="true" /> Organiser
                        </dt>
                        <dd>{event.organizer}</dd>
                    </div>
                )}
            </dl>
            {event.registration_url && (
                <a className="btn btn-accent mt-3" href={event.registration_url} target="_blank" rel="noopener noreferrer">
                    Register<span className="visually-hidden"> (opens in new tab)</span>
                </a>
            )}
        </section>
    );
}

function formatDate(iso) {
    return new Intl.DateTimeFormat(document.documentElement.lang || 'en', { day: 'numeric', month: 'long', year: 'numeric' }).format(new Date(iso));
}
