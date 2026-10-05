import { useId } from 'react';
import ColorInput from './ColorInput.jsx';
import ImageInput from './ImageInput.jsx';
import LinkInput from './LinkInput.jsx';
import RepeaterInput from './RepeaterInput.jsx';
import RichTextInput from './RichTextInput.jsx';

/**
 * Renders one field definition (CMS-ARCHITECTURE.md §11.2) as an accessible form control.
 *
 * @param {{field: Object, value: any, onChange: (value: any) => void, errors?: Record<string, string[]>, path?: string, disabled?: boolean}} props
 */
export default function FieldInput({ field, value, onChange, errors = {}, path = field.key, disabled = false }) {
    const id = useId();
    const error = errors[path]?.[0];
    const helpId = field.help ? `${id}-help` : undefined;
    const errorId = error ? `${id}-error` : undefined;
    const describedBy = [helpId, errorId].filter(Boolean).join(' ') || undefined;
    const invalid = error ? ' is-invalid' : '';

    let control;
    switch (field.type) {
        case 'textarea':
            control = <textarea id={id} rows={3} className={`form-control form-control-sm${invalid}`} value={value ?? ''} maxLength={field.max} disabled={disabled} aria-describedby={describedBy} onChange={(e) => onChange(e.target.value)} />;
            break;
        case 'rich-text':
            control = <RichTextInput id={id} value={value ?? ''} onChange={onChange} disabled={disabled} describedBy={describedBy} />;
            break;
        case 'number':
            control = <input id={id} type="number" className={`form-control form-control-sm${invalid}`} value={value ?? ''} min={field.min} max={field.max} disabled={disabled} aria-describedby={describedBy} onChange={(e) => onChange(e.target.value === '' ? undefined : Number(e.target.value))} />;
            break;
        case 'checkbox':
            return (
                <div className="form-check mb-3">
                    <input id={id} type="checkbox" className="form-check-input" checked={Boolean(value)} disabled={disabled} onChange={(e) => onChange(e.target.checked)} />
                    <label className="form-check-label" htmlFor={id}>
                        {field.label}
                    </label>
                </div>
            );
        case 'select':
            control = (
                <select id={id} className={`form-select form-select-sm${invalid}`} value={value ?? ''} disabled={disabled} aria-describedby={describedBy} onChange={(e) => onChange(e.target.value || undefined)}>
                    {!field.required && field.default === undefined && <option value="">—</option>}
                    {Object.entries(field.options ?? {}).map(([optionValue, label]) => (
                        <option key={optionValue} value={optionValue}>
                            {label}
                        </option>
                    ))}
                </select>
            );
            break;
        case 'link':
            control = <LinkInput id={id} value={value ?? null} onChange={onChange} disabled={disabled} />;
            break;
        case 'image':
        case 'media':
            control = <ImageInput value={value ?? null} onChange={onChange} label={field.label} disabled={disabled} />;
            break;
        case 'color':
            control = <ColorInput id={id} value={value ?? null} onChange={onChange} disabled={disabled} />;
            break;
        case 'icon':
            control = (
                <div className="input-group input-group-sm">
                    <span className="input-group-text" aria-hidden="true">
                        <i className={`bi ${/^bi-[a-z0-9-]+$/.test(value ?? '') ? value : 'bi-question'}`} />
                    </span>
                    <input id={id} className={`form-control${invalid}`} placeholder="bi-tree" value={value ?? ''} disabled={disabled} aria-describedby={describedBy} onChange={(e) => onChange(e.target.value.trim())} />
                </div>
            );
            break;
        case 'date':
            control = <input id={id} type="date" className={`form-control form-control-sm${invalid}`} value={value ?? ''} disabled={disabled} onChange={(e) => onChange(e.target.value)} />;
            break;
        case 'repeater':
            return (
                <fieldset className="mb-3">
                    <legend className="form-label">{field.label}</legend>
                    <RepeaterInput field={field} value={Array.isArray(value) ? value : []} onChange={onChange} errors={errors} path={path} disabled={disabled} />
                    {error && <div className="invalid-feedback d-block">{error}</div>}
                </fieldset>
            );
        default:
            control = (
                <input
                    id={id}
                    type={field.type === 'email' ? 'email' : field.type === 'video-url' ? 'url' : 'text'}
                    className={`form-control form-control-sm${invalid}`}
                    value={value ?? ''}
                    maxLength={field.max}
                    placeholder={field.placeholder}
                    disabled={disabled}
                    aria-describedby={describedBy}
                    onChange={(e) => onChange(e.target.value)}
                />
            );
    }

    return (
        <div className="mb-3">
            <label className="form-label small fw-semibold" htmlFor={id}>
                {field.label}
                {field.required && <span className="pa-required">(required)</span>}
            </label>
            {control}
            {field.help && (
                <div id={helpId} className="form-text">
                    {field.help}
                </div>
            )}
            {error && (
                <div id={errorId} className="invalid-feedback d-block">
                    {error}
                </div>
            )}
        </div>
    );
}
