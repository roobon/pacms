import { createContext, useContext } from 'react';

/** @type {import('react').Context<import('../utils/initialData.js').InitialData>} */
const InitialDataContext = createContext({ site: null, route: null });

export const InitialDataProvider = InitialDataContext.Provider;

// eslint-disable-next-line react-refresh/only-export-components -- context + its hook belong together
export function useInitialData() {
    return useContext(InitialDataContext);
}
