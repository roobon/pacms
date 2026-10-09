import { color, enumValue, length, spacing, token, url } from './values.js';

/**
 * Compiles a block tree's layout/style/responsive/visibility settings into one scoped
 * stylesheet (CMS-ARCHITECTURE.md §8.4, §9). Used by the public site and the builder
 * preview, so editors see exactly what visitors get.
 *
 * Breakpoints (desktop-first): tablet ≤ 991.98px, mobile ≤ 767.98px.
 */

const MEDIA = {
    tablet: '@media (max-width: 991.98px)',
    mobile: '@media (max-width: 767.98px)',
};

const HIDE_MEDIA = {
    desktop: '@media (min-width: 992px)',
    tablet: '@media (min-width: 768px) and (max-width: 991.98px)',
    mobile: '@media (max-width: 767.98px)',
};

const SIDES = ['top', 'right', 'bottom', 'left'];

/** CSS class for a block. */
export function blockClass(uuid) {
    return `b-${String(uuid).replace(/[^0-9a-z]/gi, '')}`;
}

/**
 * @param {Array<Object>} nodes
 * @returns {string}
 */
export function compileTree(nodes) {
    const rules = [];
    walk(nodes ?? [], (node) => compileNode(node, rules));
    return rules.join('\n');
}

function walk(nodes, visit) {
    for (const node of nodes) {
        visit(node);
        if (Array.isArray(node.children)) walk(node.children, visit);
    }
}

function compileNode(node, rules) {
    const selector = `.${blockClass(node.uuid)}`;

    pushRule(rules, selector, [...layoutDeclarations(node.layout, node.type), ...styleDeclarations(node.style)]);
    overlayRules(rules, selector, node.style);
    columnRules(rules, selector, node.layout?.columns?.desktop);

    for (const breakpoint of ['tablet', 'mobile']) {
        const overrides = node.responsive?.[breakpoint];
        const declarations = [
            ...layoutDeclarations(overrides?.layout, node.type),
            ...styleDeclarations(overrides?.style),
        ];
        const nested = [];
        pushRule(nested, selector, declarations);
        columnRules(nested, selector, breakpointColumns(node, breakpoint));
        if (nested.length) rules.push(`${MEDIA[breakpoint]} {\n${nested.join('\n')}\n}`);
    }

    for (const device of node.advanced?.visibility?.hide_on ?? []) {
        if (HIDE_MEDIA[device]) rules.push(`${HIDE_MEDIA[device]} { ${selector} { display: none !important; } }`);
    }
}

/** Column spans for tablet/mobile; mobile falls back to tablet (desktop-first inheritance). */
function breakpointColumns(node, breakpoint) {
    const columns = node.layout?.columns ?? {};
    const override = node.responsive?.[breakpoint]?.layout?.columns?.[breakpoint];
    return override ?? columns[breakpoint] ?? (breakpoint === 'mobile' ? columns.tablet : undefined);
}

function layoutDeclarations(layout, type) {
    if (!layout) return [];
    const out = [];

    for (const side of SIDES) {
        add(out, `padding-${side}`, spacing(layout.padding?.[side]));
        add(out, `margin-${side}`, spacing(layout.margin?.[side]));
    }
    add(out, 'min-height', length(layout.min_height));
    const maxWidth = token(layout.max_width) ?? length(layout.max_width);
    if (maxWidth) {
        add(out, 'max-width', maxWidth);
        add(out, 'margin-inline', 'auto');
    }
    add(out, 'gap', spacing(layout.gap));
    add(out, 'align-items', enumValue('align', layout.align));
    add(out, 'justify-content', enumValue('justify', layout.justify));
    add(out, 'text-align', enumValue('textAlign', layout.text_align));
    if (type === 'hero' && layout.min_height) add(out, 'display', 'flex');

    return out;
}

/** Filled-colour tokens that have a readable text colour (DesignTokenService::FILLS). */
const FILLS = ['primary', 'primary-strong', 'secondary', 'accent', 'success', 'warning', 'danger', 'bg-dark'];

function styleDeclarations(style) {
    if (!style) return [];
    const out = [];
    const background = style.background;

    if (background?.type === 'color') add(out, 'background-color', color(background.color));
    if (background?.type === 'gradient' && Array.isArray(background.gradient?.stops)) {
        const stops = background.gradient.stops
            .map((stop) => (color(stop.color) ? `${color(stop.color)} ${Number(stop.at) || 0}%` : null))
            .filter(Boolean);
        if (stops.length >= 2) add(out, 'background-image', `linear-gradient(${Number(background.gradient.angle) || 0}deg, ${stops.join(', ')})`);
    }
    if (background?.type === 'image' && background.image?.src) {
        add(out, 'background-image', url(background.image.src));
        add(out, 'background-size', enumValue('bgSize', background.size) ?? 'cover');
        add(out, 'background-position', enumValue('bgPosition', background.position) ?? 'center');
        add(out, 'background-repeat', 'no-repeat');
    }
    if (background?.overlay) add(out, 'position', 'relative');

    const typography = style.typography ?? {};
    add(out, 'color', color(typography.color));
    // A filled colour background gets its readable text colour (design tokens: --pa-color-on-*),
    // headings and muted text included, unless the editor chose a text colour.
    const fill = background?.type === 'color' && typeof background.color?.$token === 'string' ? background.color.$token.replace(/^color\./, '') : null;
    if (!typography.color && FILLS.includes(fill)) {
        const on = `var(--pa-color-on-${fill})`;
        add(out, 'color', on);
        for (const variable of ['--pa-color-heading', '--pa-color-body', '--bs-heading-color', '--bs-body-color', '--bs-emphasis-color']) add(out, variable, on);
        for (const variable of ['--pa-color-muted', '--bs-secondary-color']) add(out, variable, `color-mix(in srgb, ${on} 80%, transparent)`);
        add(out, '--pa-color-eyebrow', on);
        add(out, '--pa-btn-outline', on);
        add(out, '--pa-btn-outline-hover', `var(--pa-color-${fill})`);
    }
    add(out, 'font-family', token(typography.font));
    add(out, 'font-size', token(typography.size));
    if ([300, 400, 500, 600, 700, 800, 900].includes(typography.weight)) add(out, 'font-weight', String(typography.weight));
    const lineHeight = Number(typography.line_height);
    if (lineHeight >= 0.8 && lineHeight <= 3) add(out, 'line-height', String(lineHeight));
    add(out, 'text-align', enumValue('textAlign', typography.align));
    add(out, 'text-transform', enumValue('transform', typography.transform));
    if (typography.color) add(out, '--bs-heading-color', 'inherit');

    const border = style.border;
    if (border?.width) {
        const value = `${length(border.width)} ${enumValue('borderStyle', border.style) ?? 'solid'} ${color(border.color) ?? 'var(--pa-color-border)'}`;
        for (const side of border.sides ?? SIDES) {
            if (SIDES.includes(side) && length(border.width)) add(out, `border-${side}`, value);
        }
    }

    const radius = token(style.radius);
    if (radius) {
        add(out, 'border-radius', radius);
        add(out, 'overflow', 'hidden');
    }
    add(out, 'box-shadow', token(style.shadow));

    return out;
}

function overlayRules(rules, selector, style) {
    const overlay = style?.background?.overlay;
    const overlayColor = color(overlay?.color);
    if (!overlayColor) return;
    const opacity = Math.max(0, Math.min(1, Number(overlay.opacity) || 0));
    rules.push(`${selector}::before { content: ""; position: absolute; inset: 0; background: ${overlayColor}; opacity: ${opacity}; pointer-events: none; }`);
    rules.push(`${selector} > * { position: relative; z-index: 1; }`);
}

/** Columns block: a 12-column CSS grid; each child spans its configured width. */
function columnRules(rules, selector, spans) {
    if (!Array.isArray(spans)) return;
    spans.forEach((span, index) => {
        const n = Math.max(1, Math.min(12, Number(span) || 12));
        rules.push(`${selector} > :nth-child(${spans.length}n + ${index + 1}) { grid-column: span ${n}; }`);
    });
}

function add(out, property, value) {
    if (value !== null && value !== undefined && value !== '') out.push(`${property}: ${value};`);
}

function pushRule(rules, selector, declarations) {
    if (declarations.length) rules.push(`${selector} { ${declarations.join(' ')} }`);
}
