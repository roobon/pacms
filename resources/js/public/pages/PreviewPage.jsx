import { useInitialData } from '../contexts/InitialDataContext.jsx';
import ContentView from './ContentView.jsx';
import NotFoundPage from './NotFoundPage.jsx';
import PageView from './PageView.jsx';

/**
 * Secure preview (CMS-ARCHITECTURE.md §4.4) of a page or a content item. The server
 * validated the signature and the editor's permission and embedded the draft payload;
 * nothing is fetched from the public API.
 */
export default function PreviewPage() {
    const { page, content, route } = useInitialData();

    if (!route?.preview || (!page && !content)) return <NotFoundPage />;

    return (
        <>
            <div className="pa-preview-banner" role="status">
                <i className="bi bi-eye" aria-hidden="true" /> Preview — this version is not public.
            </div>
            {page ? <PageView page={page} /> : <ContentView item={content} />}
        </>
    );
}
