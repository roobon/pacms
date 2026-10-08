import { frameProps } from '../common/frame.js';
import ItemsDisplay from '../display/ItemsDisplay.jsx';

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
 * Events collection (upcoming by default). Cards show the event's own date label and venue.
 */
export function EventsBlock({ node, preview }) {
    const items = node.items ?? [];
    const { heading, empty_text: emptyText, show_excerpt: showExcerpt = true, show_date: showDate = true } = node.content;

    return (
        <div {...frameProps(node, preview, 'pa-collection pa-collection--events')}>
            {heading && <h2 className="pa-block-heading">{heading}</h2>}
            {items.length === 0 ? (
                <p className="text-body-secondary">{emptyText || 'No upcoming events right now.'}</p>
            ) : (
                <ItemsDisplay items={items} display={node.display} showExcerpt={showExcerpt} showDate={showDate} headingLevel={heading ? 3 : 2} />
            )}
        </div>
    );
}
