import { beforeEach, describe, expect, it, vi } from 'vitest';
import { loadWithReload } from './lazyWithReload.js';

describe('loadWithReload', () => {
    beforeEach(() => sessionStorage.clear());

    it('returns the module when the import succeeds', async () => {
        const module = { default: 'Page' };
        await expect(loadWithReload(() => Promise.resolve(module))).resolves.toBe(module);
    });

    it('reloads once when a chunk from an old release is missing', async () => {
        const reload = vi.fn();
        const result = loadWithReload(() => Promise.reject(new Error('Failed to fetch dynamically imported module')), { reload });

        await Promise.resolve();
        await Promise.resolve();
        expect(reload).toHaveBeenCalledTimes(1);
        expect(sessionStorage.getItem('pacms:chunk-reload')).toBe('1');
        void result;
    });

    it('does not loop: after one reload the error surfaces to the error boundary', async () => {
        sessionStorage.setItem('pacms:chunk-reload', '1');
        const reload = vi.fn();

        await expect(loadWithReload(() => Promise.reject(new Error('missing')), { reload })).rejects.toThrow('missing');
        expect(reload).not.toHaveBeenCalled();
    });

    it('clears the flag after a successful load', async () => {
        sessionStorage.setItem('pacms:chunk-reload', '1');
        await loadWithReload(() => Promise.resolve({ default: 'Page' }));
        expect(sessionStorage.getItem('pacms:chunk-reload')).toBeNull();
    });
});
