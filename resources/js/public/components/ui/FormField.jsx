import { useId } from 'react';

/**
 * Accessible labelled input with inline error text.
 *
 * @param {{
 *   label: string,
 *   name: string,
 *   type?: string,
 *   value: string,
 *   onChange: (value: string) => void,
 *   error?: string,
 *   help?: string,
 *   autoComplete?: string,
 *   required?: boolean,
 * }} props
 */
export default function FormField({ label, name, type = 'text', value, onChange, error, help, autoComplete, required = false }) {
    const id = useId();
    const describedBy = [help ? `${id}-help` : null, error ? `${id}-error` : null].filter(Boolean).join(' ') || undefined;

    return (
        <div className="mb-3">
            <label htmlFor={id} className="form-label">
                {label}
                {required && <span className="visually-hidden"> (required)</span>}
            </label>
            <input
                id={id}
                name={name}
                type={type}
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className={`form-control${error ? ' is-invalid' : ''}`}
                autoComplete={autoComplete}
                required={required}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
            />
            {help && (
                <div id={`${id}-help`} className="form-text">
                    {help}
                </div>
            )}
            {error && (
                <div id={`${id}-error`} className="invalid-feedback">
                    {error}
                </div>
            )}
        </div>
    );
}
