/**
 * Page metadata rendered with React 19 document metadata (hoisted into <head>).
 * The server shell already rendered the same tags for crawlers on the first load.
 *
 * @param {{seo: {title: string, description?: string|null, canonical?: string|null, robots?: string,
 *   og?: {type?: string, title?: string, description?: string|null, image?: string|null}}}} props
 */
export default function SeoHead({ seo }) {
    return (
        <>
            <title>{seo.title}</title>
            {seo.description && <meta name="description" content={seo.description} />}
            {seo.robots && <meta name="robots" content={seo.robots} />}
            {seo.canonical && <link rel="canonical" href={seo.canonical} />}
            {seo.og?.title && <meta property="og:title" content={seo.og.title} />}
            {seo.og?.description && <meta property="og:description" content={seo.og.description} />}
            {seo.og?.image && <meta property="og:image" content={seo.og.image} />}
            {seo.canonical && <meta property="og:url" content={seo.canonical} />}
        </>
    );
}
