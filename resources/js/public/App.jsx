import { useState } from 'react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { InitialDataProvider } from './contexts/InitialDataContext.jsx';
import AppRoutes from './routes/AppRoutes.jsx';

/**
 * @param {{initialData: import('./utils/initialData.js').InitialData, queryClient?: QueryClient}} props
 */
export default function App({ initialData, queryClient }) {
    const [client] = useState(
        () =>
            queryClient ??
            new QueryClient({
                defaultOptions: {
                    queries: { staleTime: 60 * 1000, refetchOnWindowFocus: false, retry: 1 },
                },
            }),
    );

    return (
        <QueryClientProvider client={client}>
            <InitialDataProvider value={initialData}>
                <AppRoutes />
            </InitialDataProvider>
        </QueryClientProvider>
    );
}
