import { useId } from 'react';
import TokenSelect from '../fields/TokenSelect.jsx';
import { useBuilder } from '../store.js';
import { getIn, setIn } from '../tree.js';

const COLUMN_PRESETS = {
    '12': 'One column',
    '6,6': 'Two equal',
    '4,8': 'Narrow + wide',
    '8,4': 'Wide + narrow',
    '4,4,4': 'Three equal',
    '3,3,3,3': 'Four equal',
};

/**
 * Layout settings (CMS-ARCHITECTURE.md §8.1). Values are design tokens or validated
 * literals. On tablet/phone the panel edits that device's overrides; settings that only
 * make sense once per block (content width, columns, maximum width) stay desktop-only.
 *
 * @param {{node: Object, type: Object, onChange: (layout: Object) => void, errors: Record<string, string[]>, disabled?: boolean, device?: string}} props
 */
export default function LayoutPanel({ node, type, onChange, errors, disabled = false, device = 'desktop' }) {
    const id = useId();
    const layout = node.layout ?? {};
    const set = (path, value) => onChange(setIn(layout, path, value));
    const desktop = device === 'desktop';
    const hasContainer = desktop && ['section', 'hero'].includes(type.slug);
    // The same names (and real sizes) as a container's "Maximum width".
    const widths = Object.fromEntries(useBuilder((state) => state.definitions?.tokens?.container ?? []).map((token) => [token.token, token.value]));
    const px = (token) => (widths[token] ? ` (${widths[token]})` : '');

    return (
        <div>
            {hasContainer && (
                <Row id={`${id}-container`} label="Content width">
                    <select id={`${id}-container`} className="form-select form-select-sm" value={layout.container ?? 'boxed'} disabled={disabled} onChange={(e) => set('container', e.target.value)}>
                        <option value="narrow">Narrow{px('container.narrow')}</option>
                        <option value="boxed">Site width{px('container.xxl')}</option>
                        <option value="wide">Wide{px('container.wide')}</option>
                        <option value="fluid">Full width, with side margins</option>
                        <option value="full">Edge to edge (backgrounds and sliders)</option>
                    </select>
                </Row>
            )}

            {desktop && type.slug === 'columns' && (
                <>
                    <Row id={`${id}-cols`} label="Columns (desktop)">
                        <select
                            id={`${id}-cols`}
                            className="form-select form-select-sm"
                            value={(layout.columns?.desktop ?? []).join(',')}
                            disabled={disabled}
                            onChange={(e) => set('columns.desktop', e.target.value.split(',').map(Number))}
                        >
                            {!COLUMN_PRESETS[(layout.columns?.desktop ?? []).join(',')] && <option value={(layout.columns?.desktop ?? []).join(',')}>Custom</option>}
                            {Object.entries(COLUMN_PRESETS).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </Row>
                    <div className="form-check mb-3">
                        <input
                            id={`${id}-stack`}
                            type="checkbox"
                            className="form-check-input"
                            checked={Array.isArray(layout.columns?.mobile) && layout.columns.mobile.every((span) => span === 12)}
                            disabled={disabled}
                            onChange={(e) => set('columns.mobile', e.target.checked ? (layout.columns?.desktop ?? [12]).map(() => 12) : undefined)}
                        />
                        <label className="form-check-label small" htmlFor={`${id}-stack`}>
                            Stack columns on phones
                        </label>
                    </div>
                </>
            )}

            {['columns', 'button-group', 'container', 'hero', 'list'].includes(type.slug) && (
                <Row id={`${id}-gap`} label={type.slug === 'list' ? 'Space between items' : 'Gap'}>
                    <TokenSelect id={`${id}-gap`} group="space" value={layout.gap ?? null} onChange={(v) => set('gap', v)} disabled={disabled} />
                </Row>
            )}

            <div className="row g-2">
                {['top', 'bottom', 'left', 'right'].map((side) => (
                    <div className="col-6" key={side}>
                        <Row id={`${id}-p-${side}`} label={`Padding ${side}`} error={errors[`layout.padding.${side}`]}>
                            <TokenSelect id={`${id}-p-${side}`} group="space" value={getIn(layout, `padding.${side}`) ?? null} onChange={(v) => set(`padding.${side}`, v)} emptyLabel="Default" disabled={disabled} />
                        </Row>
                    </div>
                ))}
                <div className="col-6">
                    <Row id={`${id}-m-bottom`} label="Space below">
                        <TokenSelect id={`${id}-m-bottom`} group="space" value={getIn(layout, 'margin.bottom') ?? null} onChange={(v) => set('margin.bottom', v)} emptyLabel="Default" disabled={disabled} />
                    </Row>
                </div>
                <div className="col-6">
                    <Row id={`${id}-align`} label="Text alignment">
                        <select id={`${id}-align`} className="form-select form-select-sm" value={layout.text_align ?? ''} disabled={disabled} onChange={(e) => set('text_align', e.target.value || undefined)}>
                            <option value="">Default</option>
                            <option value="start">Left</option>
                            <option value="center">Centre</option>
                            <option value="end">Right</option>
                        </select>
                    </Row>
                </div>
            </div>

            {['button-group', 'hero'].includes(type.slug) && (
                <Row id={`${id}-justify`} label="Horizontal alignment">
                    <select id={`${id}-justify`} className="form-select form-select-sm" value={layout.justify ?? ''} disabled={disabled} onChange={(e) => set('justify', e.target.value || undefined)}>
                        <option value="">Default</option>
                        <option value="start">Left</option>
                        <option value="center">Centre</option>
                        <option value="end">Right</option>
                    </select>
                </Row>
            )}

            {['hero', 'section', 'container'].includes(type.slug) && (
                <Row id={`${id}-minh`} label="Minimum height (% of screen)" error={errors['layout.min_height']}>
                    <input
                        id={`${id}-minh`}
                        type="number"
                        min={0}
                        max={100}
                        className="form-control form-control-sm"
                        value={layout.min_height?.unit === 'vh' ? layout.min_height.value : ''}
                        placeholder="auto"
                        disabled={disabled}
                        onChange={(e) => set('min_height', e.target.value ? { value: Number(e.target.value), unit: 'vh' } : undefined)}
                    />
                </Row>
            )}

            {desktop && type.slug === 'container' && (
                <Row id={`${id}-maxw`} label="Maximum width">
                    <TokenSelect id={`${id}-maxw`} group="container" value={layout.max_width?.$token ? layout.max_width : null} onChange={(v) => set('max_width', v)} emptyLabel="Full width" disabled={disabled} />
                </Row>
            )}
        </div>
    );
}

function Row({ id, label, error, children }) {
    return (
        <div className="mb-3">
            <label className="form-label small" htmlFor={id}>
                {label}
            </label>
            {children}
            {error && <div className="invalid-feedback d-block">{error[0]}</div>}
        </div>
    );
}
