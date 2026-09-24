import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client.js';
import { useInitialData } from '../contexts/InitialDataContext.jsx';

/** Public site settings, seeded from the server-rendered shell so no request is needed on load. */
export function useSite() {
    const { site } = useInitialData();

    return useQuery({
        queryKey: ['site'],
        queryFn: async () => (await api.get('/site')).data.data,
        initialData: site ?? undefined,
        staleTime: 5 * 60 * 1000,
    });
}
