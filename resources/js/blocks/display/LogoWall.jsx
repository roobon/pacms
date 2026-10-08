import Image from '../common/Image.jsx';

/**
 * Partner logos in a responsive wall; each links to the partner's website when there is one.
 * The logo's alternative text is the organisation name.
 *
 * @param {{items: Array<{key: string, title: string, url: string|null, image: Object|null}>, size?: 'sm'|'md'|'lg', grayscale?: boolean, label?: string}} props
 */
export default function LogoWall({ items, size = 'md', grayscale = false, label = 'Partners' }) {
    if (!items?.length) return null;

    return (
        <ul className={`pa-logos pa-logos--${size}${grayscale ? ' pa-logos--grayscale' : ''} list-unstyled`} aria-label={label}>
            {items.map((item) => {
                const logo = item.image ? <Image image={{ ...item.image, alt: item.title }} className="pa-logos__image" sizes="12rem" /> : <span className="pa-logos__name">{item.title}</span>;
                return (
                    <li key={item.key} className="pa-logos__item">
                        {item.url ? (
                            <a href={item.url} target="_blank" rel="noopener noreferrer" className="pa-logos__link">
                                {logo}
                                <span className="visually-hidden"> (opens in new tab)</span>
                            </a>
                        ) : (
                            logo
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
