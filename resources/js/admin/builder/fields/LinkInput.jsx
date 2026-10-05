import { useEffect, useState } from 'react';
import { adminHttp } from '../../http.js';
import { useBuilder } from '../store.js';

const TYPES = { url: 'Web address', entity: 'Page or news', anchor: 'Section on this page', email: 'E-mail' };

/**
 * Link field: {type: url|entity|anchor|email, …, new_tab}. Entity links are stored by id,
 * so they keep working when a page's address changes.
 *
 * @param {{id: string, value: Object|null, onChange: (value: Object|null) => void, disabled?: boolean}} props
 */
export default function LinkInput({ id, value, onChange, disabled = false }) {
    const type = value?.type ?? 'url';
    const set = (patch) => onChange({ ...(value ?? { type }), ...patch });

    return (
        <div className="pa-link-input">
            <div className="d-flex gap-2 mb-2">
                <label className="visually-hidden" htmlFor={`${id}-type`}>
                    Link type
                </label>
                <select id={`${id}-type`} className="form-select form-select-sm" value={type} disabled={disabled} onChange={(e) => onChange({ type: e.target.value, new_tab: value?.new_tab ?? false })}>
                    {Object.entries(TYPES).map(([key, label]) => (
                        <option key={key} value={key}>
                            {label}
                        </option>
                    ))}
                </select>
                {value && (
                    <button type="button" className="btn btn-sm btn-link text-danger" onClick={() => onChange(null)} disabled={disabled}>
                        Clear
                    </button>
                )}
            </div>

            {type === 'url' && (
                <input id={id} className="form-control form-control-sm" placeholder="https://… or /page" value={value?.url ?? ''} disabled={disabled} onChange={(e) => set({ type: 'url', url: e.target.value })} />
            )}
            {type === 'entity' && <EntityPicker id={id} value={value} disabled={disabled} onPick={(target) => set({ type: 'entity', entity: target.entity, id: target.id, label: target.title })} />}
            {type === 'anchor' && (
                <input id={id} className="form-control form-control-sm" placeholder="section-name" value={value?.anchor ?? ''} disabled={disabled} onChange={(e) => set({ type: 'anchor', anchor: e.target.value })} />
            )}
            {type === 'email' && (
                <input id={id} type="email" className="form-control form-control-sm" placeholder="info@example.org" value={value?.email ?? ''} disabled={disabled} onChange={(e) => set({ type: 'email', email: e.target.value })} />
            )}

            {(type === 'url' || type === 'entity') && (
                <div className="form-check mt-1">
                    <input id={`${id}-tab`} type="checkbox" className="form-check-input" checked={Boolean(value?.new_tab)} disabled={disabled} onChange={(e) => set({ new_tab: e.target.checked })} />
                    <label className="form-check-label small" htmlFor={`${id}-tab`}>
                        Open in a new tab
                    </label>
                </div>
            )}
        </div>
    );
}

function EntityPicker({ id, value, onPick, disabled }) {
    const endpoint = useBuilder((state) => state.definitions?.endpoints?.linkTargets);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);

    useEffect(() => {
        if (!endpoint) return undefined;
        const timer = setTimeout(async () => {
            try {
                const { data } = await adminHttp.get(endpoint, { params: { q: query || undefined } });
                setResults(data.data);
            } catch {
                setResults([]);
            }
        }, 250);
        return () => clearTimeout(timer);
    }, [query, endpoint]);

    const current = value?.entity && value?.id ? results.find((r) => r.entity === value.entity && r.id === value.id) : null;

    return (
        <div>
            <input id={id} type="search" className="form-control form-control-sm mb-1" placeholder="Search pages and news" value={query} disabled={disabled} onChange={(e) => setQuery(e.target.value)} />
            {value?.id && (
                <p className="small mb-1">
                    Linked to: <strong>{current?.title ?? value.label ?? `${value.entity} #${value.id}`}</strong>
                </p>
            )}
            <ul className="list-group list-group-flush small pa-link-results">
                {results.map((target) => (
                    <li key={`${target.entity}-${target.id}`} className="list-group-item p-0">
                        <button type="button" className="btn btn-sm btn-link w-100 text-start" disabled={disabled} onClick={() => onPick(target)}>
                            {target.title} <span className="text-body-secondary">{target.path}</span>
                        </button>
                    </li>
                ))}
            </ul>
        </div>
    );
}
