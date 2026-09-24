import { useState } from 'react';
import { Link, useNavigate } from 'react-router';
import { useQueryClient } from '@tanstack/react-query';
import { authHttp, ensureCsrfCookie, toFormErrors } from '../../api/client.js';
import FormField from '../../components/ui/FormField.jsx';

export default function LoginPage() {
    const navigate = useNavigate();
    const queryClient = useQueryClient();
    const [form, setForm] = useState({ email: '', password: '' });
    const [errors, setErrors] = useState(/** @type {{message?: string, fields: Record<string, string>}} */ ({ fields: {} }));
    const [submitting, setSubmitting] = useState(false);

    async function submit(event) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({ fields: {} });

        try {
            await ensureCsrfCookie();
            const { data } = await authHttp.post('/login', form);

            if (data?.two_factor) {
                // Staff accounts with 2FA finish signing in on the server-rendered challenge page.
                window.location.assign('/auth/two-factor-challenge');
                return;
            }

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
            <title>Sign in</title>
            <meta name="robots" content="noindex" />
            <h1 className="h2 mb-4" tabIndex={-1}>
                Sign in
            </h1>
            {errors.message && Object.keys(errors.fields).length === 0 && (
                <div className="alert alert-danger" role="alert">
                    {errors.message}
                </div>
            )}
            <form onSubmit={submit} noValidate>
                <FormField label="E-mail" name="email" type="email" autoComplete="username" required
                    value={form.email} onChange={(email) => setForm({ ...form, email })} error={errors.fields.email} />
                <FormField label="Password" name="password" type="password" autoComplete="current-password" required
                    value={form.password} onChange={(password) => setForm({ ...form, password })} error={errors.fields.password} />
                <button type="submit" className="btn btn-primary w-100" disabled={submitting}>
                    {submitting ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
            <p className="mt-3 small">
                <a href="/auth/forgot-password">Forgot your password?</a>
            </p>
            <p className="small">
                No account yet? <Link to="/account/register">Create one</Link>
            </p>
        </div>
    );
}
