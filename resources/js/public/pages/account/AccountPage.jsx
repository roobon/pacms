import { useState } from 'react';
import { Navigate, useNavigate, useSearchParams } from 'react-router';
import { useQueryClient } from '@tanstack/react-query';
import { authHttp, ensureCsrfCookie } from '../../api/client.js';
import { useMe } from '../../hooks/useMe.js';
import PageSkeleton from '../../components/common/PageSkeleton.jsx';
import TestimonialSection from './TestimonialSection.jsx';

export default function AccountPage() {
    const { data: me, isPending } = useMe();
    const queryClient = useQueryClient();
    const navigate = useNavigate();
    const [searchParams] = useSearchParams();
    // Fortify redirects here with ?verified=1 after the e-mail link is clicked.
    const [notice, setNotice] = useState(searchParams.get('verified') === '1' ? 'Thank you — your e-mail address is verified.' : '');

    if (isPending) return <PageSkeleton />;
    if (!me) return <Navigate to="/account/login" replace />;

    async function signOut() {
        await ensureCsrfCookie();
        await authHttp.post('/logout');
        queryClient.setQueryData(['me'], null);
        navigate('/');
    }

    async function resendVerification() {
        await ensureCsrfCookie();
        await authHttp.post('/email/verification-notification');
        setNotice('A new verification link has been sent to your e-mail address.');
        await queryClient.invalidateQueries({ queryKey: ['me'] });
    }

    return (
        <div className="container py-5" style={{ maxWidth: '40rem' }}>
            <title>My account</title>
            <meta name="robots" content="noindex" />
            <h1 className="h2 mb-4" tabIndex={-1}>
                My account
            </h1>

            {notice && (
                <div className="alert alert-success" role="status">
                    {notice}
                </div>
            )}

            {!me.email_verified && (
                <div className="alert alert-warning">
                    <p className="mb-2">Please verify your e-mail address using the link we sent you.</p>
                    <button type="button" className="btn btn-sm btn-outline-primary" onClick={resendVerification}>
                        Resend verification e-mail
                    </button>
                </div>
            )}

            <dl className="row">
                <dt className="col-sm-3">Name</dt>
                <dd className="col-sm-9">{me.name}</dd>
                <dt className="col-sm-3">E-mail</dt>
                <dd className="col-sm-9">{me.email}</dd>
            </dl>

            <div className="d-flex flex-wrap gap-2">
                {me.can_access_admin && (
                    <a href="/admin" className="btn btn-primary">
                        Go to admin
                    </a>
                )}
                <button type="button" className="btn btn-outline-primary" onClick={signOut}>
                    Sign out
                </button>
            </div>

            <TestimonialSection me={me} />
        </div>
    );
}

