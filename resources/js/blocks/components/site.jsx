import { useCallback, useContext, useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import { Link, useLocation } from 'react-router';
import { frameProps } from '../common/frame.js';
import SmartLink from '../common/SmartLink.jsx';
import { VisitorContext, visibleItems } from '../common/visitor.js';

/**
 * Header and footer building blocks (Phase 9). They show site data the server adds as
 * `node.data` (logo, menus, contact details, social profiles).
 */

const DESKTOP = '(min-width: 992px)';

function linkOf(item) {
    return item.url ? { href: item.url, new_tab: item.new_tab, external: item.external } : null;
}

function isCurrent(item, path) {
    return Boolean(item.url) && !item.external && item.url.split(/[?#]/)[0] === path;
}

function hasCurrent(item, path) {
    return isCurrent(item, path) || item.children.some((child) => hasCurrent(child, path));
}

function Label({ item }) {
    return (
        <>
            {item.icon && <i className={`bi ${item.icon} me-1`} aria-hidden="true" />}
            {item.label}
        </>
    );
}

export function SiteLogoBlock({ node, preview }) {
    const { size = 'md', show_name: showName = true } = node.content;
    const { name = '', logo = null } = node.data ?? {};

    return (
        <div {...frameProps(node, preview, `pa-site-logo pa-site-logo--${size}`)}>
            <Link to="/" className="pa-site-logo__link">
                {logo && <img src={logo.src} srcSet={logo.srcset || undefined} sizes="240px" width={logo.width} height={logo.height} alt={showName ? '' : name} className="pa-site-logo__image" />}
                {(showName || !logo) && <span className="pa-site-logo__name">{name}</span>}
            </Link>
        </div>
    );
}

export function MenuBlock({ node, preview }) {
    const { style = 'horizontal', aria_label: ariaLabel = 'Main', heading } = node.content;
    const { signedIn } = useContext(VisitorContext);
    const { pathname } = useLocation();
    const items = visibleItems(node.data?.items, signedIn);

    if (items.length === 0) {
        return preview ? (
            <div {...frameProps(node, preview, 'pa-placeholder')} role="note">
                <i className="bi bi-list" aria-hidden="true" /> Choose a menu with items (Design → Menus).
            </div>
        ) : null;
    }

    return style === 'vertical' ? (
        <nav {...frameProps(node, preview, 'pa-menu pa-menu--vertical')} aria-label={ariaLabel || 'Menu'}>
            {heading && <h2 className="pa-menu__heading">{heading}</h2>}
            <VerticalList items={items} />
        </nav>
    ) : (
        // A new page starts with every sub-menu and the drawer closed.
        <HorizontalMenu key={pathname} node={node} preview={preview} items={items} ariaLabel={ariaLabel || 'Main'} pathname={pathname} />
    );
}

function VerticalList({ items }) {
    const { pathname } = useLocation();
    return (
        <ul className="pa-menu__list">
            {items.map((item) => (
                <li key={item.id} className={item.class || undefined}>
                    <SmartLink link={linkOf(item)} className="pa-menu__link" aria-current={isCurrent(item, pathname) ? 'page' : undefined}>
                        <Label item={item} />
                    </SmartLink>
                    {item.children.length > 0 && <VerticalList items={item.children} />}
                </li>
            ))}
        </ul>
    );
}

/**
 * Disclosure navigation (WAI-ARIA): sub-menus open with buttons, never on hover only; Esc
 * closes and returns focus. Below the desktop width the menu moves into a drawer.
 */
function HorizontalMenu({ node, preview, items, ariaLabel, pathname }) {
    const [open, setOpen] = useState(null);
    const [drawer, setDrawer] = useState(false);
    const navRef = useRef(null);
    const toggleRef = useRef(null);
    const drawerId = useId();

    // Clicks and focus outside close an open sub-menu.
    useEffect(() => {
        if (open === null) return undefined;
        const outside = (event) => {
            if (!navRef.current?.contains(event.target)) setOpen(null);
        };
        document.addEventListener('pointerdown', outside);
        document.addEventListener('focusin', outside);
        return () => {
            document.removeEventListener('pointerdown', outside);
            document.removeEventListener('focusin', outside);
        };
    }, [open]);

    const closeDrawer = useCallback(() => {
        setDrawer(false);
        toggleRef.current?.focus();
    }, []);

    return (
        <nav {...frameProps(node, preview, 'pa-menu pa-menu--horizontal')} aria-label={ariaLabel} ref={navRef}>
            <ul className="pa-menu__bar">
                {items.map((item) => (
                    <TopItem key={item.id} item={item} open={open === item.id} onToggle={() => setOpen(open === item.id ? null : item.id)} onClose={() => setOpen(null)} pathname={pathname} />
                ))}
            </ul>
            <button ref={toggleRef} type="button" className="pa-menu__toggle" aria-expanded={drawer} aria-controls={drawerId} onClick={() => setDrawer(true)}>
                <i className="bi bi-list" aria-hidden="true" />
                <span>Menu</span>
            </button>
            {/* At page level: the header's blur would otherwise confine a fixed drawer to the header. */}
            {drawer && createPortal(<Drawer id={drawerId} items={items} label={ariaLabel} onClose={closeDrawer} pathname={pathname} />, document.body)}
        </nav>
    );
}

function TopItem({ item, open, onToggle, onClose, pathname }) {
    const buttonRef = useRef(null);
    const panelId = useId();
    const current = hasCurrent(item, pathname);

    if (item.children.length === 0) {
        return (
            <li className={item.class || undefined}>
                <SmartLink link={linkOf(item)} className={`pa-menu__link${current ? ' is-current' : ''}`} aria-current={isCurrent(item, pathname) ? 'page' : undefined}>
                    <Label item={item} />
                </SmartLink>
            </li>
        );
    }

    const onKeyDown = (event) => {
        if (event.key === 'Escape' && open) {
            event.stopPropagation();
            onClose();
            buttonRef.current?.focus();
        }
    };

    return (
        <li className={`pa-menu__has-sub${item.class ? ` ${item.class}` : ''}`} onKeyDown={onKeyDown}>
            {/* A parent with its own page: the link plus a separate button for its sub-menu. */}
            {item.url ? (
                <span className="pa-menu__split">
                    <SmartLink link={linkOf(item)} className={`pa-menu__link${current ? ' is-current' : ''}`} aria-current={isCurrent(item, pathname) ? 'page' : undefined}>
                        <Label item={item} />
                    </SmartLink>
                    <button ref={buttonRef} type="button" className="pa-menu__chevron" aria-expanded={open} aria-controls={panelId} onClick={onToggle}>
                        <i className="bi bi-chevron-down" aria-hidden="true" />
                        <span className="visually-hidden">{item.label}: more</span>
                    </button>
                </span>
            ) : (
                <button ref={buttonRef} type="button" className={`pa-menu__link pa-menu__button${current ? ' is-current' : ''}`} aria-expanded={open} aria-controls={panelId} onClick={onToggle}>
                    <Label item={item} />
                    <i className="bi bi-chevron-down ms-1 pa-menu__caret" aria-hidden="true" />
                </button>
            )}
            <div id={panelId} className="pa-menu__panel" hidden={!open}>
                <SubList items={item.children} pathname={pathname} />
            </div>
        </li>
    );
}

/** Deeper levels inside a dropdown are listed, indented, rather than opening more flyouts. */
function SubList({ items, pathname }) {
    return (
        <ul className="pa-menu__sub">
            {items.map((item) => (
                <li key={item.id} className={item.class || undefined}>
                    {item.url ? (
                        <SmartLink link={linkOf(item)} className="pa-menu__sublink" aria-current={isCurrent(item, pathname) ? 'page' : undefined}>
                            <Label item={item} />
                        </SmartLink>
                    ) : (
                        <span className="pa-menu__group">
                            <Label item={item} />
                        </span>
                    )}
                    {item.children.length > 0 && <SubList items={item.children} pathname={pathname} />}
                </li>
            ))}
        </ul>
    );
}

/** The menu on small screens: a modal drawer with accordion sub-levels; focus stays inside. */
function Drawer({ id, items, label, onClose, pathname }) {
    const ref = useRef(null);

    useEffect(() => {
        const previous = document.body.style.overflow;
        document.body.style.overflow = 'hidden';
        ref.current?.querySelector('button, a')?.focus();
        return () => {
            document.body.style.overflow = previous;
        };
    }, []);

    // Back on a desktop-width screen, the drawer is not needed.
    useEffect(() => {
        const query = window.matchMedia?.(DESKTOP);
        if (!query) return undefined;
        const close = () => query.matches && onClose();
        query.addEventListener?.('change', close);
        return () => query.removeEventListener?.('change', close);
    }, [onClose]);

    const onKeyDown = (event) => {
        if (event.key === 'Escape') {
            event.stopPropagation();
            onClose();
            return;
        }
        if (event.key !== 'Tab') return;
        const focusable = [...ref.current.querySelectorAll('a[href], button:not([disabled])')].filter((element) => element.offsetParent !== null || element === document.activeElement);
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    };

    return (
        <div className="pa-menu__overlay" onPointerDown={(event) => event.target === event.currentTarget && onClose()}>
            <div id={id} ref={ref} className="pa-menu__drawer" role="dialog" aria-modal="true" aria-label={`${label} navigation`} onKeyDown={onKeyDown}>
                <div className="pa-menu__drawer-head">
                    <button type="button" className="pa-menu__close" onClick={onClose}>
                        <i className="bi bi-x-lg" aria-hidden="true" />
                        <span className="visually-hidden">Close menu</span>
                    </button>
                </div>
                <AccordionList items={items} pathname={pathname} />
            </div>
        </div>
    );
}

function AccordionList({ items, pathname }) {
    return (
        <ul className="pa-menu__accordion">
            {items.map((item) => (
                <AccordionItem key={item.id} item={item} pathname={pathname} />
            ))}
        </ul>
    );
}

function AccordionItem({ item, pathname }) {
    const [open, setOpen] = useState(() => hasCurrent(item, pathname) && item.children.length > 0);
    const panelId = useId();

    return (
        <li className={item.class || undefined}>
            <div className="pa-menu__row">
                {item.url ? (
                    <SmartLink link={linkOf(item)} className="pa-menu__link" aria-current={isCurrent(item, pathname) ? 'page' : undefined}>
                        <Label item={item} />
                    </SmartLink>
                ) : (
                    <button type="button" className="pa-menu__link pa-menu__button pa-menu__button--row" aria-expanded={open} aria-controls={panelId} onClick={() => setOpen(!open)}>
                        <Label item={item} />
                        <i className="bi bi-chevron-down pa-menu__caret" aria-hidden="true" />
                    </button>
                )}
                {item.url && item.children.length > 0 && (
                    <button type="button" className="pa-menu__chevron" aria-expanded={open} aria-controls={panelId} onClick={() => setOpen(!open)}>
                        <i className="bi bi-chevron-down" aria-hidden="true" />
                        <span className="visually-hidden">{item.label}: more</span>
                    </button>
                )}
            </div>
            {item.children.length > 0 && (
                <div id={panelId} hidden={!open}>
                    <AccordionList items={item.children} pathname={pathname} />
                </div>
            )}
        </li>
    );
}

export function SocialLinksBlock({ node, preview }) {
    const { style = 'icons', heading } = node.content;
    const links = node.data?.links ?? [];

    if (links.length === 0) {
        return preview ? (
            <div {...frameProps(node, preview, 'pa-placeholder')} role="note">
                <i className="bi bi-share" aria-hidden="true" /> Add your social profiles under Design → Header &amp; footer.
            </div>
        ) : null;
    }

    return (
        <div {...frameProps(node, preview, `pa-social pa-social--${style}`)}>
            {heading && <h2 className="pa-menu__heading">{heading}</h2>}
            <ul className="pa-social__list">
                {links.map((link) => (
                    <li key={link.network}>
                        <a href={link.url} className="pa-social__link" target="_blank" rel="noopener noreferrer me">
                            <i className={`bi ${link.icon}`} aria-hidden="true" />
                            <span className={style === 'labels' ? undefined : 'visually-hidden'}>{link.label}</span>
                            <span className="visually-hidden"> (opens in new tab)</span>
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

export function ContactInfoBlock({ node, preview }) {
    const { heading, layout = 'stacked' } = node.content;
    const { email, phone, address } = node.data ?? {};

    if (!email && !phone && !address) {
        return preview ? (
            <div {...frameProps(node, preview, 'pa-placeholder')} role="note">
                <i className="bi bi-person-lines-fill" aria-hidden="true" /> Add contact details under Settings → General.
            </div>
        ) : null;
    }

    return (
        <div {...frameProps(node, preview, `pa-contact-info pa-contact-info--${layout}`)}>
            {heading && <h2 className="pa-menu__heading">{heading}</h2>}
            <ul className="pa-contact-info__list">
                {email && (
                    <li>
                        <i className="bi bi-envelope" aria-hidden="true" /> <a href={`mailto:${email}`}>{email}</a>
                    </li>
                )}
                {phone && (
                    <li>
                        <i className="bi bi-telephone" aria-hidden="true" /> <a href={`tel:${phone.replace(/[^0-9+]/g, '')}`}>{phone}</a>
                    </li>
                )}
                {address && (
                    <li>
                        <i className="bi bi-geo-alt" aria-hidden="true" /> <span className="pa-contact-info__address">{address}</span>
                    </li>
                )}
            </ul>
        </div>
    );
}

export function CopyrightBlock({ node, preview }) {
    return <p {...frameProps(node, preview, 'pa-copyright')}>{node.data?.text ?? ''}</p>;
}

/** Sign in, or the visitor's account once signed in. */
export function AccountLinkBlock({ node, preview }) {
    const { signedIn } = useContext(VisitorContext);
    const { sign_in_label: signIn = 'Sign in', account_label: account = 'My account' } = node.content;

    return (
        <div {...frameProps(node, preview, 'pa-account-link')}>
            <Link to={signedIn ? '/account' : '/account/login'} className="btn btn-outline-primary btn-sm">
                {signedIn && <i className="bi bi-person-circle me-1" aria-hidden="true" />}
                {signedIn ? account : signIn}
            </Link>
        </div>
    );
}
