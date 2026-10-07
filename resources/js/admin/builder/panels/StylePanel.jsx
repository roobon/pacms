import { useId } from 'react';
import ColorInput from '../fields/ColorInput.jsx';
import ImageInput from '../fields/ImageInput.jsx';
import TokenSelect from '../fields/TokenSelect.jsx';
import { setIn } from '../tree.js';

/**
 * Style settings (CMS-ARCHITECTURE.md §8.1): background (colour, gradient, image), text,
 * border, corners, shadow, entrance animation. Only tokens and validated values.
 *
 * @param {{node: Object, onChange: (style: Object) => void, errors: Record<string, string[]>, disabled?: boolean}} props
 */
export default function StylePanel({ node, onChange, errors, disabled = false }) {
    const id = useId();
    const style = node.style ?? {};
    const background = style.background ?? { type: 'none' };
    const set = (path, value) => onChange(setIn(style, path, value));

    return (
        <div>
            <fieldset className="pa-panel">
                <legend className="pa-panel__title">Background</legend>
                <div className="mb-2">
                    <label className="form-label small" htmlFor={`${id}-bg`}>
                        Type
                    </label>
                    <select
                        id={`${id}-bg`}
                        className="form-select form-select-sm"
                        value={background.type}
                        disabled={disabled}
                        onChange={(e) =>
                            onChange(
                                setIn(
                                    style,
                                    'background',
                                    e.target.value === 'none'
                                        ? undefined
                                        : { type: e.target.value, ...(e.target.value === 'gradient' ? { gradient: DEFAULT_GRADIENT } : {}), ...(background.overlay ? { overlay: background.overlay } : {}) },
                                ),
                            )
                        }
                    >
                        <option value="none">None</option>
                        <option value="color">Colour</option>
                        <option value="gradient">Gradient</option>
                        <option value="image">Image</option>
                    </select>
                </div>
                {background.type === 'gradient' && <GradientEditor id={id} gradient={background.gradient} disabled={disabled} error={errors['style.background.gradient']} onChange={(g) => set('background.gradient', g)} />}
                {background.type === 'color' && (
                    <div className="mb-2">
                        <label className="form-label small" htmlFor={`${id}-bgc`}>
                            Colour
                        </label>
                        <ColorInput id={`${id}-bgc`} value={background.color ?? null} onChange={(v) => set('background.color', v)} disabled={disabled} />
                    </div>
                )}
                {background.type === 'image' && (
                    <>
                        <ImageInput value={background.image ?? null} onChange={(v) => set('background.image', v ?? undefined)} label="Background image" disabled={disabled} />
                        {errors['style.background.image'] && <div className="invalid-feedback d-block">{errors['style.background.image'][0]}</div>}
                        <div className="row g-2 mt-1">
                            <div className="col-7">
                                <label className="form-label small" htmlFor={`${id}-ov`}>
                                    Darken / tint
                                </label>
                                <ColorInput id={`${id}-ov`} value={background.overlay?.color ?? null} onChange={(v) => set('background.overlay', v ? { color: v, opacity: background.overlay?.opacity ?? 0.5 } : undefined)} disabled={disabled} />
                            </div>
                            <div className="col-5">
                                <label className="form-label small" htmlFor={`${id}-op`}>
                                    Strength
                                </label>
                                <input id={`${id}-op`} type="range" min={0} max={0.9} step={0.05} className="form-range" value={background.overlay?.opacity ?? 0.5} disabled={disabled || !background.overlay} onChange={(e) => set('background.overlay.opacity', Number(e.target.value))} />
                            </div>
                        </div>
                        <p className="form-text">Text on photos needs a tint for readable contrast.</p>
                    </>
                )}
            </fieldset>

            <fieldset className="pa-panel">
                <legend className="pa-panel__title">Text</legend>
                <div className="mb-2">
                    <label className="form-label small" htmlFor={`${id}-text`}>
                        Colour
                    </label>
                    <ColorInput id={`${id}-text`} value={style.typography?.color ?? null} onChange={(v) => set('typography.color', v)} disabled={disabled} />
                </div>
                <div className="row g-2">
                    <div className="col-6 mb-2">
                        <label className="form-label small" htmlFor={`${id}-font`}>
                            Font
                        </label>
                        <TokenSelect id={`${id}-font`} group="font" value={style.typography?.font ?? null} onChange={(v) => set('typography.font', v)} emptyLabel="Default" disabled={disabled} />
                    </div>
                    <div className="col-6 mb-2">
                        <label className="form-label small" htmlFor={`${id}-size`}>
                            Size
                        </label>
                        <TokenSelect id={`${id}-size`} group="font_size" value={style.typography?.size ?? null} onChange={(v) => set('typography.size', v)} emptyLabel="Default" disabled={disabled} />
                    </div>
                    <div className="col-6 mb-2">
                        <label className="form-label small" htmlFor={`${id}-weight`}>
                            Weight
                        </label>
                        <select id={`${id}-weight`} className="form-select form-select-sm" value={style.typography?.weight ?? ''} disabled={disabled} onChange={(e) => set('typography.weight', e.target.value ? Number(e.target.value) : undefined)}>
                            <option value="">Default</option>
                            {[[300, 'Light'], [400, 'Regular'], [500, 'Medium'], [600, 'Semibold'], [700, 'Bold'], [800, 'Extra bold']].map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>
                    <div className="col-6 mb-2">
                        <label className="form-label small" htmlFor={`${id}-transform`}>
                            Letters
                        </label>
                        <select id={`${id}-transform`} className="form-select form-select-sm" value={style.typography?.transform ?? ''} disabled={disabled} onChange={(e) => set('typography.transform', e.target.value || undefined)}>
                            <option value="">As typed</option>
                            <option value="uppercase">UPPERCASE</option>
                            <option value="lowercase">lowercase</option>
                            <option value="capitalize">Capitalised</option>
                        </select>
                    </div>
                    <div className="col-6 mb-2">
                        <label className="form-label small" htmlFor={`${id}-lh`}>
                            Line height
                        </label>
                        <select id={`${id}-lh`} className="form-select form-select-sm" value={style.typography?.line_height ?? ''} disabled={disabled} onChange={(e) => set('typography.line_height', e.target.value ? Number(e.target.value) : undefined)}>
                            <option value="">Default</option>
                            {[[1, 'Tight (1)'], [1.2, 'Snug (1.2)'], [1.4, 'Compact (1.4)'], [1.6, 'Normal (1.6)'], [1.8, 'Relaxed (1.8)'], [2, 'Loose (2)']].map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>
            </fieldset>

            <fieldset className="pa-panel">
                <legend className="pa-panel__title">Border</legend>
                <div className="row g-2">
                    <div className="col-4 mb-2">
                        <label className="form-label small" htmlFor={`${id}-bw`}>
                            Width (px)
                        </label>
                        <input
                            id={`${id}-bw`}
                            type="number"
                            min={0}
                            max={20}
                            className="form-control form-control-sm"
                            value={style.border?.width?.value ?? ''}
                            placeholder="0"
                            disabled={disabled}
                            onChange={(e) => set('border', e.target.value && Number(e.target.value) > 0 ? { ...(style.border ?? {}), width: { value: Number(e.target.value), unit: 'px' } } : undefined)}
                        />
                    </div>
                    <div className="col-8 mb-2">
                        <label className="form-label small" htmlFor={`${id}-bs`}>
                            Line
                        </label>
                        <select id={`${id}-bs`} className="form-select form-select-sm" value={style.border?.style ?? 'solid'} disabled={disabled || !style.border} onChange={(e) => set('border.style', e.target.value)}>
                            <option value="solid">Solid</option>
                            <option value="dashed">Dashed</option>
                            <option value="dotted">Dotted</option>
                        </select>
                    </div>
                </div>
                {style.border && (
                    <>
                        <div className="mb-2">
                            <label className="form-label small" htmlFor={`${id}-bc`}>
                                Colour
                            </label>
                            <ColorInput id={`${id}-bc`} value={style.border.color ?? null} onChange={(v) => set('border.color', v)} disabled={disabled} />
                        </div>
                        <div className="mb-2" role="group" aria-label="Border sides">
                            {['top', 'right', 'bottom', 'left'].map((side) => {
                                const sides = style.border.sides ?? ['top', 'right', 'bottom', 'left'];
                                return (
                                    <div className="form-check form-check-inline" key={side}>
                                        <input
                                            id={`${id}-side-${side}`}
                                            type="checkbox"
                                            className="form-check-input"
                                            checked={sides.includes(side)}
                                            disabled={disabled || (sides.length === 1 && sides.includes(side))}
                                            onChange={(e) => set('border.sides', e.target.checked ? [...sides, side] : sides.filter((s) => s !== side))}
                                        />
                                        <label className="form-check-label small" htmlFor={`${id}-side-${side}`}>
                                            {side[0].toUpperCase() + side.slice(1)}
                                        </label>
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}
                {errors['style.border.width'] && <div className="invalid-feedback d-block">{errors['style.border.width'][0]}</div>}
            </fieldset>

            <div className="row g-2">
                <div className="col-6 mb-3">
                    <label className="form-label small" htmlFor={`${id}-radius`}>
                        Corners
                    </label>
                    <TokenSelect id={`${id}-radius`} group="radius" value={style.radius ?? null} onChange={(v) => set('radius', v)} emptyLabel="Square" disabled={disabled} />
                </div>
                <div className="col-6 mb-3">
                    <label className="form-label small" htmlFor={`${id}-shadow`}>
                        Shadow
                    </label>
                    <TokenSelect id={`${id}-shadow`} group="shadow" value={style.shadow ?? null} onChange={(v) => set('shadow', v)} emptyLabel="None" disabled={disabled} />
                </div>
            </div>

            <div className="mb-3">
                <label className="form-label small" htmlFor={`${id}-anim`}>
                    Entrance animation
                </label>
                <select id={`${id}-anim`} className="form-select form-select-sm" value={style.animation?.type ?? 'none'} disabled={disabled} onChange={(e) => set('animation', e.target.value === 'none' ? undefined : { type: e.target.value })}>
                    <option value="none">None</option>
                    <option value="fade">Fade in</option>
                    <option value="fade-up">Fade up</option>
                    <option value="zoom">Zoom in</option>
                </select>
                <div className="form-text">Skipped automatically for visitors who prefer reduced motion.</div>
            </div>
        </div>
    );
}

const DEFAULT_GRADIENT = { angle: 135, stops: [{ color: { $token: 'color.primary' }, at: 0 }, { color: { $token: 'color.secondary' }, at: 100 }] };

/** Linear gradient: angle and 2–4 colour stops. */
function GradientEditor({ id, gradient, onChange, disabled, error }) {
    const value = gradient ?? DEFAULT_GRADIENT;
    const stops = value.stops ?? DEFAULT_GRADIENT.stops;
    const setStop = (index, changes) => onChange({ ...value, stops: stops.map((stop, i) => (i === index ? { ...stop, ...changes } : stop)) });

    return (
        <div className="mb-2">
            {!gradient && !disabled && (
                <button type="button" className="btn btn-sm btn-outline-primary mb-2" onClick={() => onChange(DEFAULT_GRADIENT)}>
                    Start with theme colours
                </button>
            )}
            <label className="form-label small" htmlFor={`${id}-angle`}>
                Direction ({value.angle ?? 135}°)
            </label>
            <input id={`${id}-angle`} type="range" min={0} max={360} step={15} className="form-range" value={value.angle ?? 135} disabled={disabled} onChange={(e) => onChange({ ...value, stops, angle: Number(e.target.value) })} />
            {stops.map((stop, index) => (
                <div className="row g-2 align-items-end mb-1" key={index}>
                    <div className="col-7">
                        <label className="form-label small" htmlFor={`${id}-stop-${index}`}>
                            Colour {index + 1}
                        </label>
                        <ColorInput id={`${id}-stop-${index}`} value={stop.color ?? null} onChange={(v) => setStop(index, { color: v })} disabled={disabled} />
                    </div>
                    <div className="col-3">
                        <label className="form-label small" htmlFor={`${id}-at-${index}`}>
                            At %
                        </label>
                        <input id={`${id}-at-${index}`} type="number" min={0} max={100} className="form-control form-control-sm" value={stop.at ?? 0} disabled={disabled} onChange={(e) => setStop(index, { at: Number(e.target.value) })} />
                    </div>
                    <div className="col-2">
                        {stops.length > 2 && (
                            <button type="button" className="btn btn-sm btn-icon-sm text-danger" disabled={disabled} onClick={() => onChange({ ...value, stops: stops.filter((_, i) => i !== index) })} title={`Remove colour ${index + 1}`}>
                                <i className="bi bi-x-lg" aria-hidden="true" />
                                <span className="visually-hidden">Remove colour {index + 1}</span>
                            </button>
                        )}
                    </div>
                </div>
            ))}
            {stops.length < 4 && (
                <button type="button" className="btn btn-sm btn-link p-0" disabled={disabled} onClick={() => onChange({ ...value, stops: [...stops, { color: { $token: 'color.accent' }, at: 100 }] })}>
                    <i className="bi bi-plus" aria-hidden="true" /> Add colour
                </button>
            )}
            {error && <div className="invalid-feedback d-block">{error[0]}</div>}
        </div>
    );
}
