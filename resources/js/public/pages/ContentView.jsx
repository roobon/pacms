import { Link } from 'react-router';
import BlockRenderer from '../../blocks/BlockRenderer.jsx';
import GalleryGrid from '../../blocks/display/GalleryGrid.jsx';
import ItemsDisplay from '../../blocks/display/ItemsDisplay.jsx';
import LogoWall from '../../blocks/display/LogoWall.jsx';
import BlockStyles from '../../blocks/BlockStyles.jsx';
import { VideoBlock } from '../../blocks/components/basic.jsx';
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
    const date = item.show_date && item.published_at ? formatDate(item.published_at) : null;
    const sidebar = item.sidebar?.blocks?.length ? item.sidebar : null;
    const blocks = item.blocks ?? [];

    const main = (
        <>
            {item.featured_image && <Image image={item.featured_image} priority className="pa-page-featured" sizes={sidebar ? '(min-width: 992px) 66vw, 100vw' : '(min-width: 1320px) 1280px, 100vw'} />}
            {item.event && <EventDetails event={item.event} />}
            {(item.facts?.length > 0 || item.actions?.length > 0) && <Facts facts={item.facts ?? []} actions={item.actions ?? []} />}
            {item.coverage && <CoverageArchive coverage={item.coverage} title={item.title} />}
            {item.body && (
                // Article text: rich text cleaned on the server (same allowlist as Text blocks).
                <div className="pa-rich-text pa-article-body" dangerouslySetInnerHTML={{ __html: item.body }} />
            )}
            {(item.sections ?? []).map((section) => (
                <FieldSection key={section.key} section={section} />
            ))}
            {item.gallery?.length > 0 && <GalleryGrid items={item.gallery} columns={sidebar ? 3 : 4} showCaptions label={item.title} />}
            {(item.lists ?? []).map((list) => (
                <ContentList key={list.key} list={list} />
            ))}
            {(item.related ?? []).map((section) => (
                <Related key={section.key} section={section} narrow={Boolean(sidebar)} />
            ))}
            {item.documents?.length > 0 && <Documents documents={item.documents} />}
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

/** Key facts (status, period, author…) and actions (download, website) of a module item. */
function Facts({ facts, actions }) {
    return (
        <section className="pa-event-details" aria-label="Details">
            {facts.length > 0 && (
                <dl className="mb-0">
                    {facts.map((fact) => (
                        <div key={fact.label}>
                            <dt>
                                {fact.icon && <i className={`bi ${fact.icon}`} aria-hidden="true" />} {fact.label}
                            </dt>
                            <dd>{fact.url ? <Link to={fact.url}>{fact.value}</Link> : fact.value}</dd>
                        </div>
                    ))}
                </dl>
            )}
            {actions.length > 0 && (
                <div className={`d-flex flex-wrap gap-2${facts.length > 0 ? ' mt-3' : ''}`}>
                    {actions.map((action, index) => (
                        <a
                            key={action.url}
                            className={`btn ${index === 0 ? 'btn-accent' : 'btn-outline-primary'}`}
                            href={action.url}
                            {...(action.external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                            {...(action.download ? { download: '' } : {})}
                        >
                            {action.icon && <i className={`bi ${action.icon} me-1`} aria-hidden="true" />}
                            {action.label}
                            {action.meta && <span className="small opacity-75"> ({action.meta})</span>}
                            {action.external && <span className="visually-hidden"> (opens in new tab)</span>}
                        </a>
                    ))}
                </div>
            )}
        </section>
    );
}

/**
 * A field of an admin-made content type shown as its own section: rich text, text, an
 * image, a file to download, a video (click to load) or a list of rows.
 */
function FieldSection({ section }) {
    const headingId = `section-${section.key}`;

    return (
        <section className="pa-field-section" aria-labelledby={headingId}>
            <h2 id={headingId} className="h4">
                {section.title}
            </h2>
            {section.kind === 'html' && <div className="pa-rich-text" dangerouslySetInnerHTML={{ __html: section.html }} />}
            {section.kind === 'text' && <p className="pa-pre-line">{section.text}</p>}
            {section.kind === 'image' && <Image image={section.image} className="pa-field-section__image" />}
            {section.kind === 'file' && (
                <a className="btn btn-outline-primary" href={section.file.url} download>
                    <i className="bi bi-download me-1" aria-hidden="true" />
                    {section.file.name} <span className="small opacity-75">({section.file.extension}, {section.file.size})</span>
                </a>
            )}
            {section.kind === 'video' && <VideoBlock node={{ uuid: `field-${section.key}`, content: { url: section.video, title: section.title } }} preview={false} />}
            {section.kind === 'rows' && (
                <ul className="pa-field-section__rows list-unstyled">
                    {section.rows.map((row, index) => (
                        <li key={index}>
                            <dl className="mb-0">
                                {Object.entries(row).map(([key, value]) => (
                                    <div key={key}>
                                        <dt>{section.labels[key] ?? key}</dt>
                                        <dd className="pa-pre-line">{String(value)}</dd>
                                    </div>
                                ))}
                            </dl>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/**
 * Media coverage: a notice when the original is gone, and the archived copy (PDF viewer or
 * video player) when the rights to show it are confirmed. Decided on the server.
 */
function CoverageArchive({ coverage, title }) {
    const { notice, archive = {} } = coverage;
    if (!notice && !archive.pdf && !archive.video) return null;

    return (
        <section className="pa-coverage-archive" aria-label="Archived copy">
            {notice && (
                <p className="pa-coverage-archive__notice">
                    <i className="bi bi-info-circle" aria-hidden="true" /> {notice}
                </p>
            )}
            {archive.video && (
                <figure className="mb-3">
                    <video controls preload="metadata" className="pa-coverage-archive__video" aria-label={`Archived recording: ${title}`}>
                        <source src={archive.video.url} type={archive.video.mime} />
                    </video>
                    <figcaption className="small text-body-secondary">Archived recording</figcaption>
                </figure>
            )}
            {archive.pdf && (
                <div className="mb-3">
                    <object data={archive.pdf.url} type="application/pdf" className="pa-coverage-archive__pdf" aria-label={`Archived copy: ${title}`}>
                        <p>Your browser cannot show the PDF here.</p>
                    </object>
                    <a className="btn btn-outline-primary btn-sm mt-2" href={archive.pdf.url} target="_blank" rel="noopener noreferrer">
                        <i className="bi bi-file-earmark-pdf me-1" aria-hidden="true" />
                        Open the archived copy (PDF{archive.pdf.size ? `, ${archive.pdf.size}` : ''})
                        <span className="visually-hidden"> (opens in new tab)</span>
                    </a>
                </div>
            )}
        </section>
    );
}

/** Items linked from this one: partner logos, people or other cards, or an embedded gallery. */
function Related({ section, narrow }) {
    const headingId = `related-${section.key}`;
    return (
        <section className="pa-related" aria-labelledby={headingId}>
            <h2 id={headingId} className="h4">
                {section.title}
            </h2>
            {section.display === 'logos' && <LogoWall items={section.items} size="sm" label={section.title} />}
            {section.display === 'cards' && <ItemsDisplay items={section.items} display={{ mode: 'grid', columns: { desktop: narrow ? 2 : 3, tablet: 2, mobile: 1 } }} headingLevel={3} />}
            {section.display === 'gallery' && (
                <>
                    <GalleryGrid items={section.items} columns={narrow ? 3 : 4} label={section.link_label || section.title} />
                    {section.url && (
                        <p className="mt-2">
                            <Link to={section.url}>
                                View the gallery “{section.link_label}” <i className="bi bi-arrow-right" aria-hidden="true" />
                            </Link>
                        </p>
                    )}
                </>
            )}
        </section>
    );
}

/** A numbered list such as a program's objectives or activities. */
function ContentList({ list }) {
    return (
        <section className="pa-content-list" aria-labelledby={`list-${list.key}`}>
            <h2 id={`list-${list.key}`} className="h4">
                {list.title}
            </h2>
            <ol className="pa-content-list__items">
                {list.items.map((entry, index) => (
                    <li key={index}>
                        {entry.title && <strong className="d-block">{entry.title}</strong>}
                        {entry.text && <span className="pa-pre-line">{entry.text}</span>}
                    </li>
                ))}
            </ol>
        </section>
    );
}

/** Files to download (reports, briefs…). */
function Documents({ documents }) {
    return (
        <section className="pa-documents" aria-labelledby="documents-heading">
            <h2 id="documents-heading" className="h4">
                Documents
            </h2>
            <ul className="list-unstyled mb-0">
                {documents.map((document) => (
                    <li key={document.url}>
                        <a href={document.url} download className="pa-documents__link">
                            <i className="bi bi-file-earmark-arrow-down" aria-hidden="true" />
                            <span>
                                {document.label}
                                <span className="d-block small text-body-secondary">
                                    {document.extension} · {document.size}
                                </span>
                            </span>
                        </a>
                    </li>
                ))}
            </ul>
        </section>
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
