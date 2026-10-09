import { useId, useState } from 'react';
import { useQuery, useQueryClient } from '@tanstack/react-query';
import { api, ensureCsrfCookie, toFormErrors } from '../../api/client.js';
import FormField from '../../components/ui/FormField.jsx';

const EMPTY = { name: '', organization: '', designation: '', body: '', program_id: '', project_id: '', consent: false, homepage: '' };

/**
 * "Share a testimonial" in the account area (CMS-ARCHITECTURE.md §18.2): the form for
 * verified users and the list of one's own submissions with their status. Submissions go
 * to moderation; nothing is published straight away.
 *
 * @param {{me: {name: string, email_verified: boolean}}} props
 */
export default function TestimonialSection({ me }) {
    const queryClient = useQueryClient();
    const id = useId();
    const [form, setForm] = useState({ ...EMPTY, name: me.name ?? '' });
    const [photo, setPhoto] = useState(/** @type {File|null} */ (null));
    const [errors, setErrors] = useState(/** @type {{message?: string, fields: Record<string, string>}} */ ({ fields: {} }));
    const [submitting, setSubmitting] = useState(false);
    const [sent, setSent] = useState(false);

    const options = useQuery({
        queryKey: ['testimonial-options'],
        queryFn: async () => (await api.get('/testimonials/options')).data.data,
        enabled: me.email_verified,
        staleTime: 5 * 60 * 1000,
    });
    const mine = useQuery({
        queryKey: ['my-testimonials'],
        queryFn: async () => (await api.get('/me/testimonials')).data.data,
    });

    const set = (field) => (value) => setForm({ ...form, [field]: value });
    const max = options.data?.body_max ?? 1500;

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({ fields: {} });
        try {
            await ensureCsrfCookie();
            const data = new FormData();
            for (const [key, value] of Object.entries(form)) {
                if (key === 'consent') {
                    if (value) data.append('consent', '1');
                } else if (value !== '') {
                    data.append(key, String(value));
                }
            }
            if (photo) data.append('photo', photo);
            await api.post('/testimonials', data);
            setSent(true);
            setForm({ ...EMPTY, name: me.name ?? '' });
            setPhoto(null);
            await queryClient.invalidateQueries({ queryKey: ['my-testimonials'] });
        } catch (error) {
            const status = /** @type {any} */ (error)?.response?.status;
            setErrors(status === 429 ? { message: 'You have sent several testimonials today. Please try again tomorrow.', fields: {} } : toFormErrors(error));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <section className="mt-5" aria-labelledby={`${id}-heading`}>
            <h2 id={`${id}-heading`} className="h4">
                Share a testimonial
            </h2>

            {!me.email_verified ? (
                <p className="text-body-secondary">Verify your e-mail address first; then you can share a testimonial here.</p>
            ) : sent ? (
                <div className="alert alert-success" role="status">
                    <p className="mb-2">Thank you! Your testimonial has been sent. We review every testimonial before it is published.</p>
                    <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => setSent(false)}>
                        Write another
                    </button>
                </div>
            ) : (
                <form onSubmit={submit} noValidate>
                    {errors.message && Object.keys(errors.fields).length === 0 && (
                        <div className="alert alert-danger" role="alert">
                            {errors.message}
                        </div>
                    )}
                    <FormField label="Your name" name="name" autoComplete="name" required value={form.name} onChange={set('name')} error={errors.fields.name} />
                    <div className="row">
                        <div className="col-sm-6">
                            <FormField label="Organisation" name="organization" autoComplete="organization" value={form.organization} onChange={set('organization')} error={errors.fields.organization} />
                        </div>
                        <div className="col-sm-6">
                            <FormField label="Designation" name="designation" autoComplete="organization-title" help="e.g. Teacher, Student" value={form.designation} onChange={set('designation')} error={errors.fields.designation} />
                        </div>
                    </div>

                    <div className="mb-3">
                        <label htmlFor={`${id}-body`} className="form-label">
                            Your testimonial<span className="visually-hidden"> (required)</span>
                        </label>
                        <textarea
                            id={`${id}-body`}
                            name="body"
                            rows={6}
                            maxLength={max}
                            required
                            className={`form-control${errors.fields.body ? ' is-invalid' : ''}`}
                            value={form.body}
                            onChange={(e) => set('body')(e.target.value)}
                            aria-invalid={errors.fields.body ? true : undefined}
                            aria-describedby={`${id}-body-help${errors.fields.body ? ` ${id}-body-error` : ''}`}
                        />
                        <div id={`${id}-body-help`} className="form-text">
                            {form.body.length} / {max} characters
                        </div>
                        {errors.fields.body && (
                            <div id={`${id}-body-error`} className="invalid-feedback">
                                {errors.fields.body}
                            </div>
                        )}
                    </div>

                    <div className="row">
                        {[
                            ['program_id', 'Related program', options.data?.programs],
                            ['project_id', 'Related project', options.data?.projects],
                        ].map(([name, label, list]) =>
                            list?.length ? (
                                <div className="col-sm-6 mb-3" key={name}>
                                    <label htmlFor={`${id}-${name}`} className="form-label">
                                        {label}
                                    </label>
                                    <select id={`${id}-${name}`} className={`form-select${errors.fields[name] ? ' is-invalid' : ''}`} value={form[name]} onChange={(e) => set(name)(e.target.value)}>
                                        <option value="">— None —</option>
                                        {list.map((option) => (
                                            <option key={option.id} value={option.id}>
                                                {option.title}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.fields[name] && <div className="invalid-feedback">{errors.fields[name]}</div>}
                                </div>
                            ) : null,
                        )}
                    </div>

                    <div className="mb-3">
                        <label htmlFor={`${id}-photo`} className="form-label">
                            Photo (optional)
                        </label>
                        <input
                            id={`${id}-photo`}
                            type="file"
                            accept=".jpg,.jpeg,.png,.webp"
                            className={`form-control${errors.fields.photo ? ' is-invalid' : ''}`}
                            onChange={(e) => setPhoto(e.target.files?.[0] ?? null)}
                            aria-describedby={`${id}-photo-help`}
                        />
                        <div id={`${id}-photo-help`} className="form-text">
                            JPG, PNG or WebP, up to 2 MB.
                        </div>
                        {errors.fields.photo && <div className="invalid-feedback">{errors.fields.photo}</div>}
                    </div>

                    {/* Honeypot: hidden from people; bots fill in every field. */}
                    <div className="pa-honeypot" aria-hidden="true">
                        <label htmlFor={`${id}-homepage`}>Leave this empty</label>
                        <input id={`${id}-homepage`} name="homepage" tabIndex={-1} autoComplete="off" value={form.homepage} onChange={(e) => set('homepage')(e.target.value)} />
                    </div>

                    <div className="form-check mb-3">
                        <input
                            id={`${id}-consent`}
                            type="checkbox"
                            className={`form-check-input${errors.fields.consent ? ' is-invalid' : ''}`}
                            checked={form.consent}
                            onChange={(e) => setForm({ ...form, consent: e.target.checked })}
                            required
                        />
                        <label htmlFor={`${id}-consent`} className="form-check-label">
                            {options.data?.consent?.text ?? 'I agree that this testimonial may be published on this website.'}
                        </label>
                        {errors.fields.consent && <div className="invalid-feedback">{errors.fields.consent}</div>}
                    </div>

                    <button type="submit" className="btn btn-primary" disabled={submitting}>
                        {submitting ? 'Sending…' : 'Send testimonial'}
                    </button>
                </form>
            )}

            {mine.data?.length > 0 && (
                <>
                    <h3 className="h5 mt-5">Your testimonials</h3>
                    <ul className="list-unstyled">
                        {mine.data.map((item) => (
                            <li key={item.id} className="border rounded p-3 mb-2">
                                <p className="mb-1 pa-pre-line">{item.quote}</p>
                                <p className="small text-body-secondary mb-0">
                                    {item.status_label}
                                    {item.submitted_at && ` · sent ${new Date(item.submitted_at).toLocaleDateString()}`}
                                </p>
                                {item.rejection_reason && <p className="small mb-0 mt-1">{item.rejection_reason}</p>}
                            </li>
                        ))}
                    </ul>
                </>
            )}
        </section>
    );
}
