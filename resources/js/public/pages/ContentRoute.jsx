import { Navigate, useLocation } from 'react-router';
import { useResolvedPath } from '../hooks/useResolvedPath.js';
import PageSkeleton from '../components/common/PageSkeleton.jsx';
import HomePage from './HomePage.jsx';
import NotFoundPage from './NotFoundPage.jsx';
import NewsView from './NewsView.jsx';
import PageView from './PageView.jsx';

/**
 * Catch-all route for CMS-defined URLs (CMS-ARCHITECTURE.md §24.2): resolves the path
 * through the API (or the server-rendered initial data) and renders what it maps to.
 */
export default function ContentRoute() {
    const { pathname } = useLocation();
    const { data, isPending, isError, refetch } = useResolvedPath(pathname);

    if (isPending) return <PageSkeleton />;

    if (isError) {
        return (
            <div className="container py-5" role="alert">
                <h1 className="h3" tabIndex={-1}>
                    This page could not be loaded
                </h1>
                <p>Please check your connection and try again.</p>
                <button type="button" className="btn btn-primary" onClick={() => refetch()}>
                    Try again
                </button>
            </div>
        );
    }

    switch (data.kind) {
        case 'page':
            return <PageView page={data.data} />;
        case 'news':
            return <NewsView news={data.data} />;
        case 'home':
            return <HomePage />;
        case 'redirect':
            if (/^https?:\/\//i.test(data.to)) {
                window.location.replace(data.to);
                return <PageSkeleton />;
            }
            return <Navigate to={data.to} replace />;
        default:
            return <NotFoundPage />;
    }
}
