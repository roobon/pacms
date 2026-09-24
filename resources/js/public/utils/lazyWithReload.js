import { lazy } from 'react';

const RELOAD_KEY = 'pacms:chunk-reload';

/**
 * React.lazy() that survives deployments.
 *
 * Each release replaces the hashed JS files in public/build. A visitor who still has the
 * previous version open would fail to load a lazily imported page ("Failed to fetch
 * dynamically imported module"). In that case we reload once to pick up the new release.
 * A sessionStorage flag prevents a reload loop if the file is genuinely missing.
 *
 * @template T
 * @param {() => Promise<{default: T}>} importer
 * @param {{storage?: Storage, reload?: () => void}} [options] injectable for tests
 */
export function lazyWithReload(importer, options = {}) {
    return lazy(() => loadWithReload(importer, options));
}

/**
 * @template T
 * @param {() => Promise<T>} importer
 * @param {{storage?: Storage, reload?: () => void}} [options]
 * @returns {Promise<T>}
 */
export async function loadWithReload(importer, { storage = window.sessionStorage, reload = () => window.location.reload() } = {}) {
    try {
        const module = await importer();
        storage.removeItem(RELOAD_KEY);
        return module;
    } catch (error) {
        if (!storage.getItem(RELOAD_KEY)) {
            storage.setItem(RELOAD_KEY, '1');
            reload();
            // Keep React suspended while the page reloads.
            return new Promise(() => {});
        }
        throw error;
    }
}
