import { useId, useRef, useState } from 'react';

/**
 * Ordered list of rows with a few text fields each. Inputs are named name[i][field], so the
 * rows are posted in their visible order.
 *
 * @param {{
 *   name: string, label: string, addLabel?: string, max?: number, disabled?: boolean,
 *   fields: Record<string, {type: 'text'|'textarea', label: string}>,
 *   rows?: Array<Record<string, string|null>>,
 *   errors?: Record<string, string>
 * }} props
 */
export function RepeaterField({ name, label, addLabel = 'Add', max = 30, disabled = false, fields, rows = [], errors = {} }) {
    const baseId = useId();
    const nextKey = useRef(rows.length);
    const [items, setItems] = useState(() => rows.map((row, index) => ({ key: index, values: row })));
    const [announcement, setAnnouncement] = useState('');
    const keys = Object.keys(fields);
    const firstField = keys[0];
    const rowLabel = fields[firstField]?.label ?? 'Item';

    function update(index, field, value) {
        setItems((previous) => previous.map((item, i) => (i === index ? { ...item, values: { ...item.values, [field]: value } } : item)));
    }

    function add() {
        const key = nextKey.current++;
        setItems((previous) => [...previous, { key, values: {} }]);
        setAnnouncement(`${rowLabel} ${items.length + 1} added.`);
        // Focus the new row's first input once it is rendered.
        requestAnimationFrame(() => document.getElementById(`${baseId}-${key}-${firstField}`)?.focus());
    }

    function remove(index) {
        setItems((previous) => previous.filter((_, i) => i !== index));
        setAnnouncement(`${rowLabel} ${index + 1} removed.`);
    }

    function move(index, direction) {
        const target = index + direction;
        if (target < 0 || target >= items.length) return;
        setItems((previous) => {
            const next = [...previous];
            [next[index], next[target]] = [next[target], next[index]];
            return next;
        });
        setAnnouncement(`${rowLabel} moved to position ${target + 1}.`);
    }

    return (
        <fieldset className="mb-3" disabled={disabled}>
            <legend className="form-label fs-6">{label}</legend>
            {items.length === 0 && <p className="small text-body-secondary">None yet.</p>}
            <ol className="pa-list-editor list-unstyled mb-2">
                {items.map((item, index) => (
                    <li key={item.key} className="pa-list-editor__row">
                        <span className="pa-list-editor__number" aria-hidden="true">
                            {index + 1}
                        </span>
                        <div className="pa-list-editor__fields">
                            {keys.map((field) => {
                                const id = `${baseId}-${item.key}-${field}`;
                                const error = errors[`${name}.${index}.${field}`];
                                const common = {
                                    id,
                                    name: `${name}[${index}][${field}]`,
                                    value: item.values[field] ?? '',
                                    onChange: (event) => update(index, field, event.target.value),
                                    className: `form-control form-control-sm${error ? ' is-invalid' : ''}`,
                                    'aria-invalid': error ? true : undefined,
                                };
                                return (
                                    <div key={field}>
                                        <label htmlFor={id} className="form-label small mb-1">
                                            {fields[field].label} {index + 1}
                                        </label>
                                        {fields[field].type === 'textarea' ? <textarea rows={2} {...common} /> : <input type="text" {...common} />}
                                        {error && <div className="invalid-feedback">{error}</div>}
                                    </div>
                                );
                            })}
                        </div>
                        <div className="pa-list-editor__actions">
                            <button type="button" className="btn btn-sm btn-link" onClick={() => move(index, -1)} disabled={index === 0}>
                                <i className="bi bi-arrow-up" aria-hidden="true" />
                                <span className="visually-hidden">
                                    Move {rowLabel.toLowerCase()} {index + 1} up
                                </span>
                            </button>
                            <button type="button" className="btn btn-sm btn-link" onClick={() => move(index, 1)} disabled={index === items.length - 1}>
                                <i className="bi bi-arrow-down" aria-hidden="true" />
                                <span className="visually-hidden">
                                    Move {rowLabel.toLowerCase()} {index + 1} down
                                </span>
                            </button>
                            <button type="button" className="btn btn-sm btn-link text-danger" onClick={() => remove(index)}>
                                <i className="bi bi-x-lg" aria-hidden="true" />
                                <span className="visually-hidden">
                                    Remove {rowLabel.toLowerCase()} {index + 1}
                                </span>
                            </button>
                        </div>
                    </li>
                ))}
            </ol>
            {items.length < max && (
                <button type="button" className="btn btn-sm btn-outline-primary" onClick={add}>
                    <i className="bi bi-plus-lg me-1" aria-hidden="true" />
                    {addLabel}
                </button>
            )}
            {errors[name] && <div className="invalid-feedback d-block">{errors[name]}</div>}
            <span className="visually-hidden" role="status" aria-live="polite">
                {announcement}
            </span>
        </fieldset>
    );
}
