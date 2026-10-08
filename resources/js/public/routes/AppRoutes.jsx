import { Suspense } from 'react';
import { Route, Routes } from 'react-router';
import { lazyWithReload } from '../utils/lazyWithReload.js';
import SiteShell from '../components/layout/SiteShell.jsx';
import PageSkeleton from '../components/common/PageSkeleton.jsx';
import ContentRoute from '../pages/ContentRoute.jsx';
import PreviewPage from '../pages/PreviewPage.jsx';

// Account pages are only needed by visitors who sign in — keep them out of the initial bundle.
const LoginPage = lazyWithReload(() => import('../pages/account/LoginPage.jsx'));
const RegisterPage = lazyWithReload(() => import('../pages/account/RegisterPage.jsx'));
const AccountPage = lazyWithReload(() => import('../pages/account/AccountPage.jsx'));
// Only used inside the admin Block Builder.
const BuilderPreviewPage = lazyWithReload(() => import('../pages/BuilderPreviewPage.jsx'));

/**
 * Route table. Must stay in sync with App\Http\Controllers\Public\SpaController::ROUTES,
 * which returns the correct HTTP status for each URL. CMS pages and content types are
 * resolved dynamically in later phases.
 */
export default function AppRoutes() {
    return (
        <Routes>
            {/* Builder preview frame: blocks only, no site header/footer. */}
            <Route
                path="__builder-preview"
                element={
                    <Suspense fallback={null}>
                        <BuilderPreviewPage />
                    </Suspense>
                }
            />
            <Route element={<SiteShell />}>
                <Route index element={<ContentRoute />} />
                <Route path="preview/:kind/:id" element={<PreviewPage />} />
                <Route
                    path="account"
                    element={
                        <Suspense fallback={<PageSkeleton />}>
                            <AccountPage />
                        </Suspense>
                    }
                />
                <Route
                    path="account/login"
                    element={
                        <Suspense fallback={<PageSkeleton />}>
                            <LoginPage />
                        </Suspense>
                    }
                />
                <Route
                    path="account/register"
                    element={
                        <Suspense fallback={<PageSkeleton />}>
                            <RegisterPage />
                        </Suspense>
                    }
                />
                {/* CMS pages, redirects and 404s are resolved by the server-side PathResolver. */}
                <Route path="*" element={<ContentRoute />} />
            </Route>
        </Routes>
    );
}
