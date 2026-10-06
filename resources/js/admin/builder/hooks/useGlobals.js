import { useEffect, useState } from 'react';
import { adminHttp } from '../../http.js';
import { useBuilder } from '../store.js';

/** Template and global block lists, fetched once per page load and shared by every palette. */
export const cache = { templates: null, globals: null };

/** Global block names for labels in the tree and inspector. */
export function useGlobals() {
    const endpoints = useBuilder((state) => state.definitions?.endpoints);
    const [list, setList] = useState(cache.globals);
    useEffect(() => {
        if (list || !endpoints) return;
        adminHttp.get(endpoints.globals).then(({ data }) => {
            cache.globals = data.data;
            setList(data.data);
        });
    }, [list, endpoints]);
    return list ?? [];
}
