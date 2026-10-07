import { useCallback, useEffect, useId, useRef, useState } from 'react';
import Popover from '../Popover.jsx';

const LIMIT = 120;

/** Shown before searching: icons organisations use most. */
const POPULAR = [
    'tree', 'flower1', 'globe2', 'recycle', 'droplet', 'sun', 'cloud-sun', 'water', 'leaf',
    'people', 'person', 'person-badge', 'heart', 'hand-thumbs-up', 'award', 'trophy', 'star',
    'book', 'mortarboard', 'lightbulb', 'calendar-event', 'clock', 'geo-alt', 'house', 'building',
    'envelope', 'telephone', 'chat-dots', 'megaphone', 'newspaper', 'camera', 'image', 'play-circle',
    'check-circle', 'check2', 'arrow-right-circle', 'arrow-right', 'info-circle', 'question-circle', 'shield-check',
    'facebook', 'instagram', 'youtube', 'linkedin', 'twitter-x', 'whatsapp', 'link-45deg', 'download', 'gift', 'cash-coin',
];

/** Icon names, loaded on first use (a separate ~13 KB chunk). */
let names = null;
async function loadNames() {
    names ??= Object.keys((await import('bootstrap-icons/font/bootstrap-icons.json')).default);
    return names;
}

/**
 * "Browse" button that opens a searchable grid of Bootstrap Icons; picking one calls
 * onPick("bi-<name>").
 *
 * @param {{value?: string, onPick: (icon: string) => void, disabled?: boolean}} props
 */
export default function IconPicker({ value, onPick, disabled = false }) {
    const [open, setOpen] = useState(false);
    const button = useRef(null);
    const close = useCallback(() => setOpen(false), []);

    return (
        <>
            <button ref={button} type="button" className="btn btn-outline-secondary" disabled={disabled} aria-expanded={open} onClick={() => setOpen(!open)}>
                Browse
            </button>
            {open && (
                <Popover anchor={button} onClose={close} align="end" label="Choose an icon" className="pa-icon-picker">
                    <IconGrid
                        value={value}
                        onPick={(icon) => {
                            onPick(icon);
                            close();
                            button.current?.focus();
                        }}
                    />
                </Popover>
            )}
        </>
    );
}

function IconGrid({ value, onPick }) {
    const id = useId();
    const [all, setAll] = useState(names);
    const [query, setQuery] = useState('');

    useEffect(() => {
        if (!all) loadNames().then(setAll);
    }, [all]);

    const q = query.trim().toLowerCase().replace(/^bi-/, '');
    const matches = q === '' ? POPULAR.filter((name) => !all || all.includes(name)) : (all ?? []).filter((name) => name.includes(q));
    const shown = matches.slice(0, LIMIT);

    return (
        <div>
            <label className="visually-hidden" htmlFor={`${id}-search`}>
                Search icons
            </label>
            <input id={`${id}-search`} type="search" className="form-control form-control-sm mb-2" placeholder="Search icons, e.g. leaf, people, phone" value={query} onChange={(e) => setQuery(e.target.value)} autoFocus />
            <p className="small text-body-secondary mb-2" aria-live="polite">
                {!all && q !== '' ? 'Loading icons…' : q === '' ? 'Popular icons — search to see all 2,000+.' : `${matches.length} found${matches.length > LIMIT ? `, showing ${LIMIT} — type more to narrow down` : ''}.`}
            </p>
            <div className="pa-icon-picker__grid">
                {shown.map((name) => (
                    <button key={name} type="button" className={`pa-icon-picker__item${value === `bi-${name}` ? ' is-selected' : ''}`} title={name} aria-label={name} aria-pressed={value === `bi-${name}`} onClick={() => onPick(`bi-${name}`)}>
                        <i className={`bi bi-${name}`} aria-hidden="true" />
                    </button>
                ))}
            </div>
            {all && q !== '' && matches.length === 0 && <p className="small mb-0">No icons match “{query}”.</p>}
        </div>
    );
}
