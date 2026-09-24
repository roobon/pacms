import { useInitialData } from '../contexts/InitialDataContext.jsx';
import PageView from './PageView.jsx';
import NotFoundPage from './NotFoundPage.jsx';

/**
 * Secure preview (CMS-ARCHITECTURE.md §4.4). The server validated the signature and the
 * editor's permission and embedded the draft payload; nothing is fetched from the public API.
 */
export default function PreviewPage() {
    const { page, route } = useInitialData();

    if (!route?.preview || !page) return <NotFoundPage />;

    return (
        <>
            <div className="pa-preview-banner" role="status">
                <i className="bi bi-eye" aria-hidden="true" /> Preview — this version is not public.
            </div>
            <PageView page={page} />
        </>
    );
}
