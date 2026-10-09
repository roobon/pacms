import { describe, expect, it } from 'vitest';
import { blockClass, compileTree } from './compile.js';

const node = (overrides = {}) => ({ uuid: '01k00000000000000000000abc', type: 'section', ...overrides });

describe('compileTree', () => {
    it('turns tokens and lengths into scoped CSS', () => {
        const css = compileTree([
            node({
                layout: { padding: { top: { $token: 'space.section' } }, min_height: { value: 60, unit: 'vh' }, text_align: 'center' },
                style: { background: { type: 'color', color: { $token: 'color.primary' } }, radius: { $token: 'radius.lg' } },
            }),
        ]);

        expect(css).toContain('.b-01k00000000000000000000abc {');
        expect(css).toContain('padding-top: var(--pa-space-section);');
        expect(css).toContain('min-height: 60vh;');
        expect(css).toContain('background-color: var(--pa-color-primary);');
        expect(css).toContain('border-radius: var(--pa-radius-lg);');
    });

    it('gives filled backgrounds their readable text colour, unless a text colour is chosen', () => {
        const dark = compileTree([node({ style: { background: { type: 'color', color: { $token: 'color.bg-dark' } } } })]);
        expect(dark).toContain('color: var(--pa-color-on-bg-dark);');
        expect(dark).toContain('--bs-heading-color: var(--pa-color-on-bg-dark);');
        expect(dark).toContain('--pa-btn-outline: var(--pa-color-on-bg-dark);');

        const chosen = compileTree([node({ style: { background: { type: 'color', color: { $token: 'color.bg-dark' } }, typography: { color: '#FFCC00' } } })]);
        expect(chosen).toContain('color: #FFCC00;');
        expect(chosen).not.toContain('on-bg-dark');

        // A chosen text colour reaches headings, eyebrows and outline buttons too.
        expect(chosen).toContain('--pa-color-heading: #FFCC00;');
        expect(chosen).toContain('--pa-btn-outline: #FFCC00;');

        // A gradient uses its first colour.
        const gradient = compileTree([node({ style: { background: { type: 'gradient', gradient: { angle: 135, stops: [{ color: { $token: 'color.primary' }, at: 0 }, { color: { $token: 'color.secondary' }, at: 100 }] } } } })]);
        expect(gradient).toContain('--pa-color-heading: var(--pa-color-on-primary);');

        // A light surface or a hex colour has no automatic text colour.
        expect(compileTree([node({ style: { background: { type: 'color', color: '#F6F8FA' } } })])).not.toContain('--pa-color-on-');
    });

    it('compiles responsive overrides and visibility into media queries', () => {
        const css = compileTree([
            node({
                responsive: { mobile: { layout: { padding: { top: { $token: 'space.4' } } } } },
                advanced: { visibility: { hide_on: ['mobile'] } },
            }),
        ]);

        expect(css).toMatch(/@media \(max-width: 767\.98px\) \{\n\.b-01k00000000000000000000abc \{ padding-top: var\(--pa-space-4\); \}/);
        expect(css).toContain('@media (max-width: 767.98px) { .b-01k00000000000000000000abc { display: none !important; } }');
    });

    it('lays out columns on a 12-column grid with mobile stacking', () => {
        const css = compileTree([node({ type: 'columns', layout: { columns: { desktop: [4, 8], mobile: [12, 12] } } })]);

        expect(css).toContain('> :nth-child(2n + 1) { grid-column: span 4; }');
        expect(css).toContain('> :nth-child(2n + 2) { grid-column: span 8; }');
        expect(css).toMatch(/@media \(max-width: 767\.98px\)[^@]*span 12/);
    });

    it('walks nested children', () => {
        const css = compileTree([node({ children: [node({ uuid: '01k00000000000000000000def', type: 'heading', style: { typography: { color: '#112233' } } })] })]);
        expect(css).toContain('.b-01k00000000000000000000def { color: #112233;');
    });

    it('ignores anything that is not a validated value (no CSS injection)', () => {
        const css = compileTree([
            node({
                layout: { padding: { top: { value: '1px;} body{display:none', unit: 'px' } }, min_height: { value: 10, unit: 'px;}' } },
                style: {
                    background: { type: 'color', color: 'red;}body{display:none' },
                    typography: { color: { $token: 'color.x;}' }, weight: '900;}' },
                    radius: { $token: '../../evil' },
                },
            }),
        ]);

        expect(css).not.toContain('display:none');
        expect(css).not.toContain('}body');
        expect(css).not.toContain('evil');
    });

    it('sanitises the class name', () => {
        expect(blockClass('ab"c<d>')).toBe('b-abcd');
    });

    it('compiles line height, side padding and gap; ignores out-of-range line heights', () => {
        const css = compileTree([
            node({
                type: 'list',
                layout: { padding: { left: { $token: 'space.4' }, right: { value: 12, unit: 'px' } }, gap: { $token: 'space.2' } },
                style: { typography: { line_height: 1.8 } },
            }),
            node({ uuid: '01k00000000000000000000xyz', style: { typography: { line_height: 9 } } }),
        ]);

        expect(css).toContain('line-height: 1.8;');
        expect(css).toContain('padding-left: var(--pa-space-4);');
        expect(css).toContain('padding-right: 12px;');
        expect(css).toContain('gap: var(--pa-space-2);');
        expect(css).not.toContain('line-height: 9');
    });
});
