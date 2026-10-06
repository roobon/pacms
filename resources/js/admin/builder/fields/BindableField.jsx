import { useId } from 'react';
import { useBuilder } from '../store.js';
import FieldInput from './FieldInput.jsx';

/**
 * A block setting inside a custom block type's layout: either a fixed value (normal input)
 * or linked to one of the type's fields ({"$bind": "photo"}), so each placed block shows
 * the editor's value. Only compatible field types are offered (the server checks too).
 *
 * @param {{field: Object, value: any, scope: {options: Array<Object>}, onChange: (value: any) => void, errors: Record<string, string[]>, disabled?: boolean, bindable?: boolean}} props
 */
export default function BindableField({ field, value, scope, onChange, errors, disabled = false, bindable = true }) {
    const id = useId();
    const compatibility = useBuilder((state) => state.definitions?.bindings ?? {});
    const sources = compatibility[field.type] ?? [];
    const options = bindable ? scope.options.filter((option) => sources.includes(option.type)) : [];
    const bound = value && typeof value === 'object' && typeof value.$bind === 'string';
    const path = `content.${field.key}`;
    const error = errors[path]?.[0];

    if (bound) {
        return (
            <div className="mb-3 pa-binding">
                <label className="form-label small fw-semibold" htmlFor={id}>
                    {field.label} <span className="pa-badge pa-badge--info">Linked</span>
                </label>
                <div className="input-group input-group-sm">
                    <span className="input-group-text" aria-hidden="true">
                        <i className="bi bi-link-45deg" />
                    </span>
                    <select id={id} className={`form-select${error ? ' is-invalid' : ''}`} value={value.$bind} disabled={disabled} onChange={(e) => onChange({ $bind: e.target.value })}>
                        {!options.some((option) => option.path === value.$bind) && <option value={value.$bind}>{value.$bind} (not available)</option>}
                        {options.map((option) => (
                            <option key={option.path} value={option.path}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <button type="button" className="btn btn-outline-secondary" disabled={disabled} onClick={() => onChange(undefined)}>
                        Unlink
                    </button>
                </div>
                <div className="form-text">Shows the value editors enter in this field.</div>
                {error && <div className="invalid-feedback d-block">{error}</div>}
            </div>
        );
    }

    return (
        <div className="pa-binding">
            {options.length > 0 && !disabled && (
                <button type="button" className="btn btn-sm btn-link pa-binding__link" onClick={() => onChange({ $bind: options[0].path })} title={`Link ${field.label} to a field`}>
                    <i className="bi bi-link-45deg" aria-hidden="true" /> Link to field<span className="visually-hidden">: {field.label}</span>
                </button>
            )}
            <FieldInput field={field} value={value} path={path} errors={errors} disabled={disabled} onChange={onChange} />
        </div>
    );
}
