import { useEffect, useId, useRef, useState } from 'react';
import { useLocation } from 'react-router';
import { api, ensureCsrfCookie, toFormErrors } from '../../public/api/client.js';
import { frameProps } from '../common/frame.js';

/**
 * Contact form (Phase 11A). The message goes to POST /api/v1/contact with this block's id;
 * the recipient stays on the server. A hidden field and the time since the form appeared
 * keep simple spam robots out (no third-party captcha, no cookies).
 */
export function ContactFormBlock({ node, preview }) {
    const { heading, show_subject: showSubject = true, consent_text: consentText = '', button_label: buttonLabel = 'Send message' } = node.content;
    const { pathname } = useLocation();
    const id = useId();
    const [values, setValues] = useState({ name: '', email: '', subject: '', message: '', consent: false, website: '' });
    const [errors, setErrors] = useState({ fields: {} });
    const [sending, setSending] = useState(false);
    const [done, setDone] = useState(null);
    const shownAt = useRef(0);
    const doneRef = useRef(null);

    useEffect(() => {
        shownAt.current = Date.now();
    }, []);
    useEffect(() => {
        if (done) doneRef.current?.focus();
    }, [done]);

    const set = (field) => (event) => setValues({ ...values, [field]: event.target.type === 'checkbox' ? event.target.checked : event.target.value });

    async function submit(event) {
        event.preventDefault();
        if (preview) return;
        setSending(true);
        setErrors({ fields: {} });
        try {
            await ensureCsrfCookie();
            const { data } = await api.post('/contact', { ...values, block: node.uuid, page: pathname, elapsed: Date.now() - shownAt.current });
            setDone(data?.message ?? 'Thank you!');
        } catch (error) {
            setErrors(toFormErrors(error));
        } finally {
            setSending(false);
        }
    }

    if (done) {
        return (
            <div {...frameProps(node, preview, 'pa-contact-form')}>
                <div ref={doneRef} tabIndex={-1} className="alert alert-success" role="status">
                    <i className="bi bi-check-circle me-2" aria-hidden="true" />
                    {done}
                </div>
            </div>
        );
    }

    const field = (name, label, props = {}) => (
        <div className="mb-3">
            <label htmlFor={`${id}-${name}`} className="form-label">
                {label}
                {props.required && <span className="visually-hidden"> (required)</span>}
            </label>
            {props.as === 'textarea' ? (
                <textarea id={`${id}-${name}`} className={`form-control${errors.fields[name] ? ' is-invalid' : ''}`} rows={6} value={values[name]} onChange={set(name)} required={props.required} maxLength={5000} aria-invalid={Boolean(errors.fields[name])} aria-describedby={errors.fields[name] ? `${id}-${name}-error` : undefined} />
            ) : (
                <input id={`${id}-${name}`} type={props.type ?? 'text'} autoComplete={props.autoComplete} className={`form-control${errors.fields[name] ? ' is-invalid' : ''}`} value={values[name]} onChange={set(name)} required={props.required} maxLength={props.maxLength ?? 200} aria-invalid={Boolean(errors.fields[name])} aria-describedby={errors.fields[name] ? `${id}-${name}-error` : undefined} />
            )}
            {errors.fields[name] && (
                <div id={`${id}-${name}-error`} className="invalid-feedback">
                    {errors.fields[name]}
                </div>
            )}
        </div>
    );

    return (
        <div {...frameProps(node, preview, 'pa-contact-form')}>
            {heading && <h2 className="pa-block-heading">{heading}</h2>}
            {errors.message && Object.keys(errors.fields).length === 0 && (
                <div className="alert alert-danger" role="alert">
                    {errors.message}
                </div>
            )}
            <form onSubmit={submit} noValidate>
                <div className="row g-3">
                    <div className="col-md-6">{field('name', 'Your name', { required: true, autoComplete: 'name', maxLength: 120 })}</div>
                    <div className="col-md-6">{field('email', 'Your e-mail', { required: true, type: 'email', autoComplete: 'email', maxLength: 191 })}</div>
                </div>
                {showSubject && field('subject', 'Subject (optional)')}
                {field('message', 'Message', { required: true, as: 'textarea' })}
                {/* Spam trap: hidden from people and screen readers; robots fill it in. */}
                <div className="pa-hp" aria-hidden="true">
                    <label htmlFor={`${id}-website`}>Leave this empty</label>
                    <input id={`${id}-website`} type="text" tabIndex={-1} autoComplete="off" value={values.website} onChange={set('website')} />
                </div>
                {consentText && (
                    <div className="form-check mb-3">
                        <input id={`${id}-consent`} type="checkbox" className={`form-check-input${errors.fields.consent ? ' is-invalid' : ''}`} checked={values.consent} onChange={set('consent')} required aria-invalid={Boolean(errors.fields.consent)} />
                        <label htmlFor={`${id}-consent`} className="form-check-label">
                            {consentText}
                        </label>
                        {errors.fields.consent && <div className="invalid-feedback">{errors.fields.consent}</div>}
                    </div>
                )}
                <button type="submit" className="btn btn-primary" disabled={sending}>
                    {sending ? 'Sending…' : buttonLabel}
                </button>
                {preview && <p className="small text-body-secondary mt-2 mb-0">In the editor the form does not send.</p>}
            </form>
        </div>
    );
}
