import { createContext, useContext, useEffect } from 'react';

/**
 * Lets the page being shown tell the site shell about its header and footer (Phase 9): the
 * site's default, none, or its own global blocks, and whether it opens with a hero or slider
 * (for the see-through header).
 */
export const ChromeContext = createContext({ setPageChrome: () => {} });

/**
 * @param {{header?: 'none'|Array<Object>, footer?: 'none'|Array<Object>}|undefined} chrome
 * @param {boolean} [startsWithHero]
 */
export function usePageChrome(chrome, startsWithHero = false) {
    const { setPageChrome } = useContext(ChromeContext);
    useEffect(() => {
        setPageChrome({ header: chrome?.header, footer: chrome?.footer, startsWithHero });
        return () => setPageChrome({});
    }, [chrome, startsWithHero, setPageChrome]);
}
