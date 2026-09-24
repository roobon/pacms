import { useQuery } from '@tanstack/react-query';
import { api } from '../api/client.js';

/**
 * The signed-in registered user, or null for guests.
 */
export function useMe() {
    return useQuery({
        queryKey: ['me'],
        queryFn: async () => {
            try {
                return (await api.get('/me')).data.data;
            } catch (error) {
                if (error?.response?.status === 401) return null;
                throw error;
            }
        },
        staleTime: 60 * 1000,
        retry: false,
    });
}
