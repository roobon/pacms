import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client.js';
import { useInitialData } from '../contexts/InitialDataContext.jsx';

/**
 * @typedef {{kind: 'page', data: import('../utils/initialData.js').PageData}
 *   | {kind: 'home'}
 *   | {kind: 'redirect', to: string, status: number}
 *   | {kind: 'not_found'}} Resolution
 */

/**
 * What to show at a public path: a CMS page, the default home, a redirect or 404.
 * The server-rendered first page load is used as initial data (no extra request).
 *
 * @param {string} pathname
 */
export function useResolvedPath(pathname) {
    const initial = useInitialData();
    const isInitialPath = initial.route?.path === pathname && !initial.route?.preview;

    /** @type {Resolution|undefined} */
    let initialData;
    if (isInitialPath) {
        if (initial.page) initialData = { kind: 'page', data: initial.page };
        else if (initial.news) initialData = { kind: 'news', data: initial.news };
        else if (initial.route?.status === 404) initialData = { kind: 'not_found' };
        else if (pathname === '/') initialData = { kind: 'home' };
    }

    return useQuery({
        queryKey: ['resolve', pathname],
        /** @returns {Promise<Resolution>} */
        queryFn: async () => {
            try {
                return (await api.get('/resolve', { params: { path: pathname } })).data;
            } catch (error) {
                if (error?.response?.status === 404) return { kind: 'not_found' };
                throw error;
            }
        },
        initialData,
        staleTime: 60 * 1000,
    });
}
