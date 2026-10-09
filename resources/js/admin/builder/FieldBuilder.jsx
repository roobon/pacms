import { useId, useState } from 'react';
import { useBuilder } from './store.js';

const MAX_DEPTH = 3;
const TEXT_TYPES = ['text', 'textarea', 'rich-text', 'url', 'email', 'video-url'];
const CHOICE_TYPES = ['select', 'radio', 'multi-select'];

/**
 * Field builder for a custom block type (CMS-ARCHITECTURE.md §11.2): the form editors
 * fill in. Definitions are validated on the server (FieldDefinitionValidator); errors come
 * back as "fields.<index>.<setting>".
 */
export default function FieldBuilder() {
    const fields = useBuilder((state) => state.fields) ?? [];
    const errors = useBuilder((state) => state.errors);
    const readonly = useBuilder((state) => state.readonly);
    const setFields = useBuilder((state) => state.setFields);
    const types = useBuilder((state) => state.definitions?.field_types ?? {});

    return (
        <section className="pa-field-builder" aria-labelledby="field-builder-heading">
            <div className="d-flex justify-content-between align-items-center mb-2">
                <h3 id="field-builder-heading" className="h6 mb-0">
                    Fields <span className="text-body-secondary fw-normal small">— what editors fill in for each block</span>
                </h3>
            </div>
            <FieldList list={fields} path="fields" depth={1} errors={errors} types={types} disabled={readonly} onChange={setFields} />
        </section>
    );
}

export function FieldList({ list, path, depth, errors, types, disabled, onChange }) {
    const [open, setOpen] = useState(null);
    const update = (index, field) => onChange(list.map((current, i) => (i === index ? field : current)));
    const move = (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= list.length) return;
        const copy = [...list];
        [copy[index], copy[target]] = [copy[target], copy[index]];
        onChange(copy);
    };

    function add() {
        const n = list.length + 1;
        onChange([...list, { key: `field_${n}`, type: 'text', label: `Field ${n}` }]);
        setOpen(list.length);
    }

    return (
        <div>
            {list.length === 0 && <p className="small text-body-secondary">No fields yet.</p>}
            <ul className="list-unstyled mb-2">
                {list.map((field, index) => {
                    const fieldPath = `${path}.${index}`;
                    const hasErrors = Object.keys(errors).some((key) => key === fieldPath || key.startsWith(`${fieldPath}.`));
                    const expanded = open === index || hasErrors;
                    return (
                        <li key={index} className={`pa-field-row${hasErrors ? ' is-invalid' : ''}`}>
                            <div className="pa-field-row__summary">
                                <button type="button" className="pa-field-row__toggle" aria-expanded={expanded} onClick={() => setOpen(expanded ? null : index)}>
                                    <i className={`bi ${expanded ? 'bi-chevron-down' : 'bi-chevron-right'}`} aria-hidden="true" />
                                    <strong>{field.label || field.key}</strong>
                                    <span className="small text-body-secondary">
                                        {types[field.type] ?? field.type} · {field.key}
                                        {field.required ? ' · required' : ''}
                                    </span>
                                    {hasErrors && <i className="bi bi-exclamation-circle-fill text-danger" aria-label="has errors" />}
                                </button>
                                {!disabled && (
                                    <span className="pa-field-row__actions">
                                        <button type="button" className="btn btn-icon-sm" onClick={() => move(index, -1)} disabled={index === 0} title="Move up">
                                            <i className="bi bi-arrow-up" aria-hidden="true" />
                                            <span className="visually-hidden">Move {field.label} up</span>
                                        </button>
                                        <button type="button" className="btn btn-icon-sm" onClick={() => move(index, 1)} disabled={index === list.length - 1} title="Move down">
                                            <i className="bi bi-arrow-down" aria-hidden="true" />
                                            <span className="visually-hidden">Move {field.label} down</span>
                                        </button>
                                        <button
                                            type="button"
                                            className="btn btn-icon-sm text-danger"
                                            title="Remove"
                                            onClick={() => window.confirm(`Remove the field “${field.label}”? Blocks linked to it will show nothing.`) && onChange(list.filter((_, i) => i !== index))}
                                        >
                                            <i className="bi bi-trash" aria-hidden="true" />
                                            <span className="visually-hidden">Remove {field.label}</span>
                                        </button>
                                    </span>
                                )}
                            </div>
                            {expanded && <FieldEditor field={field} path={fieldPath} depth={depth} errors={errors} types={types} disabled={disabled} onChange={(next) => update(index, next)} />}
                        </li>
                    );
                })}
            </ul>
            {!disabled && list.length < 40 && (
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={add}>
                    <i className="bi bi-plus-lg" aria-hidden="true" /> Add field
                </button>
            )}
        </div>
    );
}

function FieldEditor({ field, path, depth, errors, types, disabled, onChange }) {
    const id = useId();
    const set = (key, value) => {
        const next = { ...field };
        if (value === undefined || value === '' || value === false) delete next[key];
        else next[key] = value;
        onChange(next);
    };
    const error = (key) => errors[`${path}.${key}`]?.[0];
    const typeOptions = Object.entries(types).filter(([type]) => type !== 'repeater' || depth < MAX_DEPTH);

    function changeType(type) {
        const next = { key: field.key, type, label: field.label };
        if (field.required) next.required = true;
        if (field.help) next.help = field.help;
        if (CHOICE_TYPES.includes(type)) next.options = field.options ?? { option_1: 'Option 1' };
        if (type === 'repeater') next.fields = field.fields ?? [{ key: 'title', type: 'text', label: 'Title' }];
        onChange(next);
    }

    return (
        <div className="pa-field-row__body">
            <div className="row g-2">
                <div className="col-md-5">
                    <Input id={`${id}-label`} label="Label" value={field.label ?? ''} error={error('label')} disabled={disabled} onChange={(v) => set('label', v)} />
                </div>
                <div className="col-md-3">
                    <Input id={`${id}-key`} label="Key" value={field.key ?? ''} error={error('key')} disabled={disabled} help="a–z, 0–9, _" onChange={(v) => set('key', v.toLowerCase().replace(/[^a-z0-9_]/g, '_'))} />
                </div>
                <div className="col-md-4">
                    <label className="form-label small" htmlFor={`${id}-type`}>
                        Type
                    </label>
                    <select id={`${id}-type`} className={`form-select form-select-sm${error('type') ? ' is-invalid' : ''}`} value={field.type} disabled={disabled} onChange={(e) => changeType(e.target.value)}>
                        {typeOptions.map(([type, label]) => (
                            <option key={type} value={type}>
                                {label}
                            </option>
                        ))}
                    </select>
                    {error('type') && <div className="invalid-feedback d-block">{error('type')}</div>}
                </div>
            </div>

            <div className="row g-2 mt-1">
                <div className="col-md-8">
                    <Input id={`${id}-help`} label="Help text (optional)" value={field.help ?? ''} disabled={disabled} onChange={(v) => set('help', v)} />
                </div>
                <div className="col-md-4 d-flex align-items-end">
                    {field.type !== 'checkbox' && (
                        <div className="form-check mb-2">
                            <input id={`${id}-req`} type="checkbox" className="form-check-input" checked={Boolean(field.required)} disabled={disabled} onChange={(e) => set('required', e.target.checked)} />
                            <label className="form-check-label small" htmlFor={`${id}-req`}>
                                Required
                            </label>
                        </div>
                    )}
                </div>
            </div>

            {TEXT_TYPES.includes(field.type) && (
                <div className="row g-2 mt-1">
                    <div className="col-md-4">
                        <Input id={`${id}-max`} type="number" label="Maximum length" value={field.max ?? ''} error={error('max')} disabled={disabled} onChange={(v) => set('max', v === '' ? undefined : Number(v))} />
                    </div>
                </div>
            )}

            {field.type === 'number' && (
                <div className="row g-2 mt-1">
                    <div className="col-md-4">
                        <Input id={`${id}-min`} type="number" label="Minimum" value={field.min ?? ''} error={error('min')} disabled={disabled} onChange={(v) => set('min', v === '' ? undefined : Number(v))} />
                    </div>
                    <div className="col-md-4">
                        <Input id={`${id}-nmax`} type="number" label="Maximum" value={field.max ?? ''} error={error('max')} disabled={disabled} onChange={(v) => set('max', v === '' ? undefined : Number(v))} />
                    </div>
                </div>
            )}

            {CHOICE_TYPES.includes(field.type) && <OptionsEditor options={field.options ?? {}} error={error('options')} disabled={disabled} onChange={(options) => set('options', options)} />}

            {field.type === 'repeater' && (
                <div className="pa-field-row__nested mt-2">
                    <div className="row g-2 mb-2">
                        <div className="col-md-3">
                            <Input id={`${id}-minitems`} type="number" label="Min rows" value={field.min_items ?? ''} error={error('min_items')} disabled={disabled} onChange={(v) => set('min_items', v === '' ? undefined : Number(v))} />
                        </div>
                        <div className="col-md-3">
                            <Input id={`${id}-maxitems`} type="number" label="Max rows" value={field.max_items ?? ''} error={error('max_items')} disabled={disabled} onChange={(v) => set('max_items', v === '' ? undefined : Number(v))} />
                        </div>
                    </div>
                    <p className="small fw-semibold mb-1">Fields in each row</p>
                    {error('fields') && <div className="invalid-feedback d-block">{error('fields')}</div>}
                    <FieldList list={field.fields ?? []} path={`${path}.fields`} depth={depth + 1} errors={errors} types={types} disabled={disabled} onChange={(list) => set('fields', list)} />
                </div>
            )}
        </div>
    );
}

/** value → label pairs, edited as rows. */
function OptionsEditor({ options, error, disabled, onChange }) {
    const rows = Object.entries(options);
    // An empty value is kept while typing; the server rejects it on save.
    const write = (next) => onChange(Object.fromEntries(next));

    return (
        <fieldset className="mt-2">
            <legend className="form-label small fw-semibold">Options</legend>
            {rows.map(([value, label], index) => (
                <div className="input-group input-group-sm mb-1" key={index}>
                    <input
                        className="form-control"
                        aria-label={`Option ${index + 1} value`}
                        placeholder="value"
                        value={value}
                        disabled={disabled}
                        onChange={(e) => write(rows.map((row, i) => (i === index ? [e.target.value.replace(/[^A-Za-z0-9_-]/g, ''), row[1]] : row)))}
                    />
                    <input className="form-control" aria-label={`Option ${index + 1} label`} placeholder="Label shown to visitors" value={label} disabled={disabled} onChange={(e) => write(rows.map((row, i) => (i === index ? [row[0], e.target.value] : row)))} />
                    <button type="button" className="btn btn-outline-secondary" disabled={disabled || rows.length <= 1} onClick={() => write(rows.filter((_, i) => i !== index))}>
                        <i className="bi bi-x-lg" aria-hidden="true" />
                        <span className="visually-hidden">Remove option {index + 1}</span>
                    </button>
                </div>
            ))}
            {!disabled && (
                <button type="button" className="btn btn-sm btn-link p-0" onClick={() => write([...rows, [`option_${rows.length + 1}`, `Option ${rows.length + 1}`]])}>
                    <i className="bi bi-plus" aria-hidden="true" /> Add option
                </button>
            )}
            {error && <div className="invalid-feedback d-block">{error}</div>}
        </fieldset>
    );
}

function Input({ id, label, value, onChange, error, help, type = 'text', disabled }) {
    return (
        <div className="mb-1">
            <label className="form-label small" htmlFor={id}>
                {label}
            </label>
            <input id={id} type={type} className={`form-control form-control-sm${error ? ' is-invalid' : ''}`} value={value} disabled={disabled} onChange={(e) => onChange(e.target.value)} aria-describedby={help ? `${id}-help` : undefined} />
            {help && (
                <div id={`${id}-help`} className="form-text">
                    {help}
                </div>
            )}
            {error && <div className="invalid-feedback d-block">{error}</div>}
        </div>
    );
}
