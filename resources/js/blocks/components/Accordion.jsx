import { useId, useState } from 'react';

/**
 * Accessible accordion (WAI-ARIA accordion pattern): each header is a button with
 * aria-expanded/aria-controls; panels are labelled regions.
 *
 * @param {{items: Array<{key: string, title: string, body: import('react').ReactNode}>, firstOpen?: boolean, multiple?: boolean, headingLevel?: number}} props
 */
export default function Accordion({ items, firstOpen = false, multiple = false, headingLevel = 3 }) {
    const id = useId();
    const [open, setOpen] = useState(() => new Set(firstOpen && items[0] ? [items[0].key] : []));
    const Heading = `h${headingLevel}`;

    function toggle(key) {
        setOpen((current) => {
            const next = new Set(multiple ? current : []);
            if (!current.has(key)) next.add(key);
            return next;
        });
    }

    return (
        <div className="accordion pa-accordion">
            {items.map((item, index) => {
                const expanded = open.has(item.key);
                const headerId = `${id}-h${index}`;
                const panelId = `${id}-p${index}`;

                return (
                    <div className="accordion-item" key={item.key}>
                        <Heading className="accordion-header" id={headerId}>
                            <button
                                type="button"
                                className={`accordion-button${expanded ? '' : ' collapsed'}`}
                                aria-expanded={expanded}
                                aria-controls={panelId}
                                onClick={() => toggle(item.key)}
                            >
                                {item.title}
                            </button>
                        </Heading>
                        <div id={panelId} role="region" aria-labelledby={headerId} className={`accordion-collapse collapse${expanded ? ' show' : ''}`}>
                            <div className="accordion-body">{item.body}</div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}
