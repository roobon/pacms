import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { useQueryClient } from '@tanstack/react-query';
import { authHttp, ensureCsrfCookie, toFormErrors } from '../../api/client.js';
import FormField from '../../components/ui/FormField.jsx';
import { useSite } from '../../hooks/useSite.js';

export default function RegisterPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const { data: site } = useSite();
    const [form, setForm] = useState({ name: '', email: '', password: '', password_confirmation: '' });
    const [errors, setErrors] = useState(/** @type {{message?: string, fields: Record<string, string>}} */ ({ fields: {} }));
    const [submitting, setSubmitting] = useState(false);

    if (site && !site.features?.registration) {
        return (
            <div className="container py-5">
                <title>Registration closed</title>
                <h1 className="h2" tabIndex={-1}>
                    Registration is closed
                </h1>
                <p>New accounts cannot be created at the moment.</p>
            </div>
        );
    }

    const set = (field) => (value) => setForm({ ...form, [field]: value });

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({ fields: {} });

        try {
            await ensureCsrfCookie();
            await authHttp.post('/register', form);
            await queryClient.invalidateQueries({ queryKey: ['me'] });
            navigate('/account');
        } catch (error) {
            setErrors(toFormErrors(error));
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <div className="container py-5" style={{ maxWidth: '28rem' }}>
            <title>Create an account</title>
            <meta name="robots" content="noindex" />
            <h1 className="h2 mb-2" tabIndex={-1}>
                Create an account
            </h1>
            <p className="text-body-secondary">An account lets you share a testimonial about our work.</p>
            {errors.message && Object.keys(errors.fields).length === 0 && (
                <div className="alert alert-danger" role="alert">
                    {errors.message}
                </div>
            )}
            <form onSubmit={submit} noValidate>
                <FormField label="Name" name="name" autoComplete="name" required value={form.name} onChange={set('name')} error={errors.fields.name} />
                <FormField label="E-mail" name="email" type="email" autoComplete="email" required value={form.email} onChange={set('email')} error={errors.fields.email} />
                <FormField label="Password" name="password" type="password" autoComplete="new-password" required
                    help="At least 12 characters with letters and numbers."
                    value={form.password} onChange={set('password')} error={errors.fields.password} />
                <FormField label="Confirm password" name="password_confirmation" type="password" autoComplete="new-password" required
                    value={form.password_confirmation} onChange={set('password_confirmation')} error={errors.fields.password_confirmation} />
                <button type="submit" className="btn btn-primary w-100" disabled={submitting}>
                    {submitting ? 'Creating account…' : 'Create account'}
                </button>
            </form>
            <p className="mt-3 small">
                Already registered? <Link to="/account/login">Sign in</Link>
            </p>
        </div>
    );
}
