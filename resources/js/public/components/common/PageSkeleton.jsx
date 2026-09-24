/** Loading placeholder shaped like a page, so content does not jump when it arrives. */
export default function PageSkeleton() {
    return (
        <div className="container py-5" aria-busy="true">
            <span className="visually-hidden" role="status">
                Loading…
            </span>
            <div className="pa-skeleton mb-3" style={{ height: '2.5rem', width: '55%' }} />
            <div className="pa-skeleton mb-2" style={{ height: '1rem', width: '90%' }} />
            <div className="pa-skeleton mb-2" style={{ height: '1rem', width: '80%' }} />
            <div className="pa-skeleton" style={{ height: '1rem', width: '65%' }} />
        </div>
    );
}
