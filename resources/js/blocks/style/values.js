/**
 * Converts validated block values into CSS values (CMS-ARCHITECTURE.md §8.2).
 * The server only accepts tokens or validated literals; these helpers re-check the shape
 * anyway, so a tampered payload can never inject arbitrary CSS.
 */

const TOKEN_NAME = /^[a-z][a-z0-9-]*(\.[a-z0-9-]+)+$/;
const HEX = /^#([0-9a-f]{6}|[0-9a-f]{8})$/i;
const UNITS = new Set(['px', 'rem', 'em', '%', 'vh', 'vw']);

/** {"$token": "space.4"} → var(--pa-space-4) */
export function token(value) {
    const name = value?.$token;
    if (typeof name !== 'string' || !TOKEN_NAME.test(name)) return null;
    return `var(--pa-${name.replace(/\./g, '-')})`;
}

/** {"value": 12, "unit": "px"} → 12px */
export function length(value) {
    if (!value || typeof value.value !== 'number' || !Number.isFinite(value.value) || !UNITS.has(value.unit)) return null;
    return `${value.value}${value.unit}`;
}

/** token or length */
export function spacing(value) {
    return token(value) ?? length(value);
}

/** colour token or hex */
export function color(value) {
    if (typeof value === 'string') return HEX.test(value) ? value : null;
    return token(value);
}

/** Only same-site or https URLs from resolved media, quoted safely. */
export function url(value) {
    if (typeof value !== 'string') return null;
    if (!(value.startsWith('/') || value.startsWith('https://') || value.startsWith('http://'))) return null;
    return `url("${value.replace(/["\\\n\r]/g, '')}")`;
}

const ENUMS = {
    align: { start: 'flex-start', center: 'center', end: 'flex-end', stretch: 'stretch' },
    justify: { start: 'flex-start', center: 'center', end: 'flex-end', between: 'space-between', around: 'space-around', evenly: 'space-evenly' },
    textAlign: { start: 'start', center: 'center', end: 'end' },
    transform: { none: 'none', uppercase: 'uppercase', lowercase: 'lowercase', capitalize: 'capitalize' },
    bgPosition: { center: 'center', top: 'center top', bottom: 'center bottom', left: 'left center', right: 'right center' },
    bgSize: { cover: 'cover', contain: 'contain', auto: 'auto' },
    borderStyle: { solid: 'solid', dashed: 'dashed', dotted: 'dotted' },
};

export function enumValue(kind, value) {
    return ENUMS[kind]?.[value] ?? null;
}
