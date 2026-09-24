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
 * @typedef {Object} InitialData
 * @property {SiteData|null} site
 * @property {{path: string, status: number}|null} route
 */

/**
 * Reads the JSON payload the server embeds in the SPA shell (#pacms-initial).
 * The data is parsed, never executed.
 *
 * @param {Document} doc
 * @returns {InitialData}
 */
export function readInitialData(doc) {
    const element = doc.getElementById('pacms-initial');

    if (!element) {
        return { site: null, route: null };
    }

    try {
        const data = JSON.parse(element.textContent || '{}');
        return { site: data.site ?? null, route: data.route ?? null };
    } catch {
        return { site: null, route: null };
    }
}
