import { Link } from 'react-router';

/**
 * Renders a resolved link ({href, new_tab, external}): client-side navigation for
 * internal paths, a normal anchor otherwise. New tabs always get rel="noopener noreferrer"
 * and an announcement for screen readers.
 *
 * @param {{link: {href: string, new_tab?: boolean, external?: boolean}|null, className?: string, children: import('react').ReactNode, [key: string]: any}} props
 */
export default function SmartLink({ link, className, children, ...rest }) {
    if (!link?.href) {
        return <span className={className}>{children}</span>;
    }

    const newTab = Boolean(link.new_tab);
    const internal = link.href.startsWith('/') && !link.href.startsWith('//') && !newTab;

    if (internal) {
        return (
            <Link to={link.href} className={className} {...rest}>
                {children}
            </Link>
        );
    }

    return (
        <a
            href={link.href}
            className={className}
            target={newTab ? '_blank' : undefined}
            rel={newTab || link.external ? 'noopener noreferrer' : undefined}
            {...rest}
        >
            {children}
            {newTab && <span className="visually-hidden"> (opens in new tab)</span>}
        </a>
    );
}
