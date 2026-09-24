import { lazy, Suspense } from 'react';
import { Route, Routes } from 'react-router';
import SiteShell from '../components/layout/SiteShell.jsx';
import PageSkeleton from '../components/common/PageSkeleton.jsx';
import HomePage from '../pages/HomePage.jsx';
import NotFoundPage from '../pages/NotFoundPage.jsx';

// Account pages are only needed by visitors who sign in — keep them out of the initial bundle.
const LoginPage = lazy(() => import('../pages/account/LoginPage.jsx'));
const RegisterPage = lazy(() => import('../pages/account/RegisterPage.jsx'));
const AccountPage = lazy(() => import('../pages/account/AccountPage.jsx'));

/**
 * Route table. Must stay in sync with App\Http\Controllers\Public\SpaController::ROUTES,
 * which returns the correct HTTP status for each URL. CMS pages and content types are
 * resolved dynamically in later phases.
 */
export default function AppRoutes() {
    return (
        <Routes>
            <Route element={<SiteShell />}>
                <Route index element={<HomePage />} />
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
                <Route path="*" element={<NotFoundPage />} />
            </Route>
        </Routes>
    );
}
