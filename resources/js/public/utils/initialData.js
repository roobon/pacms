/**
 * @typedef {Object} SiteData
 * @property {string} name
 * @property {string} tagline
 * @property {string} description
 * @property {{email: string, phone: string, address: string}} contact
 * @property {string} timezone
 * @property {string} locale
 * @property {string} url
 * @property {{stylesheet: string}} theme
 * @property {{registration: boolean}} features
 */

/**
 * @typedef {Object} ImageData
 * @property {number} id
 * @property {string} src
 * @property {string|null} srcset
 * @property {string|null} sizes
 * @property {number|null} width
 * @property {number|null} height
 * @property {string} alt
 * @property {string|null} placeholder
 * @property {{x: number, y: number}|null} focal_point
 */

/**
 * @typedef {Object} PageData
 * @property {'page'} type
 * @property {number} id
 * @property {string} title
 * @property {string} path
 * @property {string} url
 * @property {boolean} is_home
 * @property {string|null} excerpt
 * @property {ImageData|null} featured_image
 * @property {string} template
 * @property {string|null} published_at
 * @property {string|null} updated_at
 * @property {{title: string, url: string}[]} breadcrumbs
 * @property {Object} seo
 * @property {Array<Object>} blocks
 * @property {boolean} [preview]
 */

/**
 * @typedef {Object} InitialData
 * @property {SiteData|null} site
 * @property {{path: string, status: number, preview?: boolean}|null} route
 * @property {PageData|null} page
 */

/**
 * Reads the JSON payload the server embeds in the SPA shell (#pacms-initial).
 * The data is parsed, never executed.
 *
 * @param {Document} doc
 * @returns {InitialData}
 */
export function readInitialData(doc) {
    const empty = { site: null, route: null, page: null };
    const element = doc.getElementById('pacms-initial');

    if (!element) {
        return empty;
    }

    try {
        const data = JSON.parse(element.textContent || '{}');
        const result = { site: data.site ?? null, route: data.route ?? null, page: data.page ?? null };
        if (data.news) result.news = data.news;
        return result;
    } catch {
        return empty;
    }
}
