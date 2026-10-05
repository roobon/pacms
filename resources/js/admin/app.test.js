import { describe, expect, it } from 'vitest';
import { slugify } from './app.js';

describe('slugify', () => {
    it('matches the server slug rules', () => {
        expect(slugify('Our Programs')).toBe('our-programs');
        expect(slugify('  Eco-Schools: 2026 edition!  ')).toBe('eco-schools-2026-edition');
        expect(slugify('Café Crème')).toBe('cafe-creme');
        expect(slugify('---')).toBe('');
    });
});
