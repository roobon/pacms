import { useId } from 'react';
import { useBuilder } from '../store.js';

const MODE_LABELS = { static: 'Enter items by hand', dynamic: 'Automatic (from the CMS)', external: 'External source' };

/**
 * [STATIC] [DYNAMIC] [EXTERNAL] switch and the whitelisted dynamic options
 * (CMS-ARCHITECTURE.md §6).
 *
 * @param {{type: Object, source: Object, onChange: (source: Object) => void, errors: Record<string, string[]>, disabled?: boolean}} props
 */
export default function SourcePanel({ type, source, onChange, errors, disabled = false }) {
    const id = useId();
    const sources = useBuilder((state) => state.definitions?.sources ?? {});
    const externalSources = useBuilder((state) => state.definitions?.external_sources ?? []);
    const providers = type.capabilities.external_providers ?? [];
    const modes = type.capabilities.source_modes;
    const mode = source?.mode ?? modes[0];
    const dynamic = sources[type.capabilities.dynamic_entity];

    if (modes.length < 2 && mode === 'static') return null;

    const set = (patch) => onChange({ ...source, mode, ...patch });

    return (
        <fieldset className="pa-panel">
            <legend className="pa-panel__title">Content source</legend>
            <div className="btn-group btn-group-sm mb-3 w-100" role="radiogroup" aria-label="Content source">
                {modes.map((option) => (
                    <button
                        key={option}
                        type="button"
                        role="radio"
                        aria-checked={mode === option}
                        className={`btn ${mode === option ? 'btn-primary' : 'btn-outline-primary'}`}
                        disabled={disabled}
                        onClick={() =>
                            onChange(
                                option === 'dynamic'
                                    ? { mode: 'dynamic', provider: 'cms', entity: type.capabilities.dynamic_entity, order: Object.keys(dynamic?.orders ?? { latest: '' })[0], limit: 6 }
                                    : option === 'external'
                                      ? { mode: 'external', provider: providers[0], source: '', limit: 6 }
                                      : { mode: option },
                            )
                        }
                    >
                        {MODE_LABELS[option]}
                    </button>
                ))}
            </div>

            {mode === 'external' && (
                <ExternalSourceFields id={id} source={source} sources={externalSources.filter((s) => providers.includes(s.provider))} set={set} errors={errors} disabled={disabled} />
            )}

            {mode === 'dynamic' && dynamic && (
                <>
                    <div className="row g-2 mb-2">
                        <div className="col-7">
                            <label className="form-label small" htmlFor={`${id}-order`}>
                                Order
                            </label>
                            <select id={`${id}-order`} className="form-select form-select-sm" value={source.order ?? ''} disabled={disabled} onChange={(e) => set({ order: e.target.value })}>
                                {Object.entries(dynamic.orders).map(([key, label]) => (
                                    <option key={key} value={key}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="col-5">
                            <label className="form-label small" htmlFor={`${id}-limit`}>
                                How many
                            </label>
                            <input id={`${id}-limit`} type="number" min={1} max={dynamic.max_limit} className="form-control form-control-sm" value={source.limit ?? 6} disabled={disabled} onChange={(e) => set({ limit: Number(e.target.value) })} />
                        </div>
                    </div>
                    {Object.entries(dynamic.filters).map(([key, filter]) =>
                        filter.type === 'bool' ? (
                            <div className="form-check" key={key}>
                                <input id={`${id}-${key}`} type="checkbox" className="form-check-input" checked={Boolean(source.filters?.[key])} disabled={disabled} onChange={(e) => set({ filters: { ...(source.filters ?? {}), [key]: e.target.checked || undefined } })} />
                                <label className="form-check-label small" htmlFor={`${id}-${key}`}>
                                    {filter.label}
                                </label>
                            </div>
                        ) : (
                            <div className="mb-2" key={key}>
                                <label className="form-label small" htmlFor={`${id}-${key}`}>
                                    {filter.label}
                                </label>
                                <select id={`${id}-${key}`} className="form-select form-select-sm" value={source.filters?.[key] ?? ''} disabled={disabled} onChange={(e) => set({ filters: { ...(source.filters ?? {}), [key]: e.target.value ? (filter.type === 'select' ? e.target.value : Number(e.target.value)) : undefined } })}>
                                    <option value="">{filter.type === 'item' ? 'Any' : 'All'}</option>
                                    {Object.entries(filter.options ?? {}).map(([value, label]) => (
                                        <option key={value} value={value}>
                                            {label}
                                        </option>
                                    ))}
                                </select>
                                {errors[`source.filters.${key}`] && <div className="invalid-feedback d-block">{errors[`source.filters.${key}`][0]}</div>}
                            </div>
                        ),
                    )}
                    <p className="form-text">Only published items are shown. The list updates automatically when items are published.</p>
                </>
            )}
        </fieldset>
    );
}

/** Which external source (Design → External sources) and how many of its items. */
function ExternalSourceFields({ id, source, sources, set, errors, disabled }) {
    const error = errors?.['source.source']?.[0];
    if (sources.length === 0) {
        return <p className="small text-body-secondary mb-0">No sources yet. Add a feed under Design → External sources, then choose it here.</p>;
    }
    return (
        <div className="row g-2">
            <div className="col-8">
                <label className="form-label small" htmlFor={`${id}-ext`}>
                    Source
                </label>
                <select
                    id={`${id}-ext`}
                    className={`form-select form-select-sm${error ? ' is-invalid' : ''}`}
                    value={source?.source ?? ''}
                    disabled={disabled}
                    onChange={(e) => set({ provider: sources.find((s) => s.slug === e.target.value)?.provider ?? source?.provider, source: e.target.value })}
                >
                    <option value="">Choose a source…</option>
                    {sources.map((s) => (
                        <option key={s.slug} value={s.slug}>
                            {s.name}
                            {s.status !== 'enabled' ? ' (disabled)' : s.last_status === 'never' ? ' (not synced yet)' : s.last_status === 'error' ? ' (last sync failed)' : ''}
                        </option>
                    ))}
                </select>
                {error && <div className="invalid-feedback">{error}</div>}
            </div>
            <div className="col-4">
                <label className="form-label small" htmlFor={`${id}-ext-limit`}>
                    How many
                </label>
                <input id={`${id}-ext-limit`} type="number" min={1} max={50} className="form-control form-control-sm" value={source?.limit ?? 6} disabled={disabled} onChange={(e) => set({ limit: Number(e.target.value) })} />
            </div>
        </div>
    );
}
