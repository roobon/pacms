import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client.js';
import { useInitialData } from '../contexts/InitialDataContext.jsx';

/**
 * @typedef {{kind: 'page', data: import('../utils/initialData.js').PageData}
 *   | {kind: 'content', data: Object}
 *   | {kind: 'archive', data: Object}
 *   | {kind: 'home'}
 *   | {kind: 'redirect', to: string, status: number}
 *   | {kind: 'not_found'}} Resolution
 */

/**
 * What to show at a public path: a CMS page, the default home, a redirect or 404.
 * The server-rendered first page load is used as initial data (no extra request).
 *
 * @param {string} pathname
 * @param {string} [search] query string ('?view=past&page=2'): archive view, category and page
 */
export function useResolvedPath(pathname, search = '') {
    const initial = useInitialData();
    const isInitialPath = initial.route?.path === pathname && (initial.route?.search ?? '') === search && !initial.route?.preview;

    /** @type {Resolution|undefined} */
    let initialData;
    if (isInitialPath) {
        if (initial.page) initialData = { kind: 'page', data: initial.page };
        else if (initial.content) initialData = { kind: 'content', data: initial.content };
        else if (initial.archive) initialData = { kind: 'archive', data: initial.archive };
        else if (initial.route?.status === 404) initialData = { kind: 'not_found' };
        else if (pathname === '/') initialData = { kind: 'home' };
    }

    return useQuery({
        queryKey: ['resolve', pathname, search],
        /** @returns {Promise<Resolution>} */
        queryFn: async () => {
            try {
                const query = Object.fromEntries(new URLSearchParams(search));
                return (await api.get('/resolve', { params: { ...query, path: pathname } })).data;
            } catch (error) {
                if (error?.response?.status === 404) return { kind: 'not_found' };
                throw error;
            }
        },
        initialData,
        staleTime: 60 * 1000,
    });
}
