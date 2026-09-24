import { useSite } from '../hooks/useSite.js';

/**
 * Placeholder homepage. From Phase 4 the homepage is assembled from CMS blocks;
 * nothing organisational is hard-coded here — all text comes from site settings.
 */
export default function HomePage() {
    const { data: site } = useSite();
    const title = site?.tagline ? `${site.name} — ${site.tagline}` : (site?.name ?? '');

    return (
        <>
            <title>{title}</title>
            <section className="pa-hero-placeholder">
                <div className="container">
                    <p className="pa-eyebrow">Welcome</p>
                    <h1 className="display-5 fw-bold" tabIndex={-1}>
                        {site?.name}
                    </h1>
                    {site?.tagline && <p className="lead mb-0">{site.tagline}</p>}
                    {site?.description && <p className="mt-3 mb-0" style={{ maxWidth: '42rem' }}>{site.description}</p>}
                </div>
            </section>
        </>
    );
}
