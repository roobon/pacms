import { frameProps } from '../common/frame.js';
import SmartLink from '../common/SmartLink.jsx';
import GalleryGrid from '../display/GalleryGrid.jsx';
import ItemsDisplay from '../display/ItemsDisplay.jsx';
import LogoWall from '../display/LogoWall.jsx';
import TestimonialsDisplay from '../display/TestimonialsDisplay.jsx';

/**
 * News collection. Items come from the server already normalised — dynamic (latest
 * published news) or static (hand-entered) — so the same display modes serve both.
 */
export function NewsBlock({ node, preview }) {
    const items = node.items ?? [];
    const { heading, empty_text: emptyText, show_excerpt: showExcerpt = true, show_date: showDate = true } = node.content;

    return (
        <div {...frameProps(node, preview, 'pa-collection pa-collection--news')}>
            {heading && <h2 className="pa-block-heading">{heading}</h2>}
            {items.length === 0 ? (
                <p className="text-body-secondary">{emptyText || 'No news yet.'}</p>
            ) : (
                <ItemsDisplay items={items} display={node.display} showExcerpt={showExcerpt} showDate={showDate} headingLevel={heading ? 3 : 2} />
            )}
        </div>
    );
}

/**
 * Collection of one content module (events, projects, programs, publications). Cards show
 * the module's own meta, such as an event's date label and place or a project's status.
 */
export function CollectionBlock({ node, preview }) {
    const items = node.items ?? [];
    const { heading, empty_text: emptyText, show_excerpt: showExcerpt = true, show_date: showDate = true } = node.content;

    return (
        <div {...frameProps(node, preview, `pa-collection pa-collection--${node.type.replace('/', '-')}`)}>
            {heading && <h2 className="pa-block-heading">{heading}</h2>}
            {items.length === 0 ? (
                <p className="text-body-secondary">{emptyText || 'Nothing to show yet.'}</p>
            ) : (
                <ItemsDisplay items={items} display={node.display} showExcerpt={showExcerpt} showDate={showDate} headingLevel={heading ? 3 : 2} />
            )}
        </div>
    );
}

/** Partners: a logo wall linking to their websites, or cards with a description. */
export function PartnersBlock({ node, preview }) {
    const items = node.items ?? [];
    const { heading, style = 'logos', logo_size: size = 'md', grayscale = false, empty_text: emptyText } = node.content;

    return (
        <div {...frameProps(node, preview, 'pa-collection pa-collection--partners')}>
            {heading && <h2 className="pa-block-heading">{heading}</h2>}
            {items.length === 0 ? (
                <p className="text-body-secondary">{emptyText || 'No partners yet.'}</p>
            ) : style === 'cards' ? (
                <ItemsDisplay items={items} display={node.display} showExcerpt showDate={false} headingLevel={heading ? 3 : 2} />
            ) : (
                <LogoWall items={items} size={size} grayscale={grayscale} label={heading || 'Partners'} />
            )}
        </div>
    );
}

/** One gallery's photos and videos with a full-screen viewer. */
export function GalleryBlock({ node, preview }) {
    const gallery = (node.items ?? [])[0];
    const { heading, columns = '4', show_captions: showCaptions = false, show_link: showLink = true } = node.content;

    if (!gallery?.media?.length) {
        return preview ? <div {...frameProps(node, preview, 'pa-placeholder')}>Choose a gallery with photos (Source → One gallery)</div> : null;
    }

    return (
        <div {...frameProps(node, preview, 'pa-collection pa-collection--gallery')}>
            {heading && <h2 className="pa-block-heading">{heading}</h2>}
            <GalleryGrid items={gallery.media} columns={columns} showCaptions={showCaptions} label={heading || gallery.title} />
            {showLink && gallery.url && (
                <p className="mt-3 mb-0">
                    <SmartLink link={{ href: gallery.url, external: false, new_tab: false }}>
                        View the gallery “{gallery.title}” <i className="bi bi-arrow-right" aria-hidden="true" />
                    </SmartLink>
                </p>
            )}
        </div>
    );
}

/** Published testimonials as quote cards, a slider or one large quote. */
export function TestimonialsBlock({ node, preview }) {
    const items = node.items ?? [];
    const { heading, empty_text: emptyText, show_photo: showPhoto = true, show_rating: showRating = true } = node.content;

    return (
        <div {...frameProps(node, preview, 'pa-collection pa-collection--testimonials')}>
            {heading && <h2 className="pa-block-heading">{heading}</h2>}
            {items.length === 0 ? (
                <p className="text-body-secondary">{emptyText || 'No testimonials yet.'}</p>
            ) : (
                <TestimonialsDisplay items={items} display={node.display} showPhoto={showPhoto} showRating={showRating} />
            )}
        </div>
    );
}
