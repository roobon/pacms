import { useEffect, useId, useRef, useState } from 'react';
import { createPortal } from 'react-dom';

/**
 * Small modal form (native <dialog>: focus trap, Escape, backdrop) for builder actions
 * such as "Save as template". Shows the server's validation message on failure.
 *
 * @param {{title: string, description?: string, submitLabel: string, fields: Array<{key: string, label: string, type?: string, options?: Object, required?: boolean, initial?: string}>, onSubmit: (values: Object) => Promise<void>, onClose: () => void}} props
 */
export default function ActionDialog({ title, description, submitLabel, fields, onSubmit, onClose }) {
    const id = useId();
    const dialog = useRef(null);
    const [values, setValues] = useState(() => Object.fromEntries(fields.map((field) => [field.key, field.initial ?? (field.options ? Object.keys(field.options)[0] : '')])));
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState(null);

    useEffect(() => {
        const element = dialog.current;
        element?.showModal();
        return () => element?.close();
    }, []);

    async function submit(event) {
        event.preventDefault();
        setBusy(true);
        setError(null);
        try {
            await onSubmit(values);
            onClose();
        } catch (e) {
            const errors = e?.response?.data?.errors;
            setError(errors ? Object.values(errors).flat()[0] : (e?.response?.data?.message ?? 'Something went wrong. Please try again.'));
            setBusy(false);
        }
    }

    return createPortal(
        <dialog ref={dialog} className="pa-dialog" aria-labelledby={`${id}-title`} onClose={onClose} onCancel={onClose}>
            <form onSubmit={submit}>
                <h2 id={`${id}-title`} className="h5">
                    {title}
                </h2>
                {description && <p className="small text-body-secondary">{description}</p>}
                {fields.map((field) => (
                    <div className="mb-3" key={field.key}>
                        <label className="form-label small" htmlFor={`${id}-${field.key}`}>
                            {field.label}
                        </label>
                        {field.options ? (
                            <select id={`${id}-${field.key}`} className="form-select form-select-sm" value={values[field.key]} onChange={(e) => setValues({ ...values, [field.key]: e.target.value })}>
                                {Object.entries(field.options).map(([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ))}
                            </select>
                        ) : (
                            <input
                                id={`${id}-${field.key}`}
                                className="form-control form-control-sm"
                                required={field.required}
                                maxLength={255}
                                value={values[field.key]}
                                onChange={(e) => setValues({ ...values, [field.key]: e.target.value })}
                            />
                        )}
                    </div>
                ))}
                {error && (
                    <div className="alert alert-danger pa-alert small" role="alert">
                        {error}
                    </div>
                )}
                <div className="d-flex justify-content-end gap-2">
                    <button type="button" className="btn btn-sm btn-link" onClick={onClose}>
                        Cancel
                    </button>
                    <button type="submit" className="btn btn-sm btn-primary" disabled={busy}>
                        {busy ? 'Saving…' : submitLabel}
                    </button>
                </div>
            </form>
        </dialog>,
        document.body,
    );
}
