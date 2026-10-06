import { useId } from 'react';
import ColorInput from '../fields/ColorInput.jsx';
import ImageInput from '../fields/ImageInput.jsx';
import TokenSelect from '../fields/TokenSelect.jsx';
import { setIn } from '../tree.js';

/**
 * Style settings (CMS-ARCHITECTURE.md §8.1): background, text colour, corners, shadow,
 * entrance animation. Gradients, borders and typography scale arrive in Phase 5.
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
                        onChange={(e) => onChange(setIn(style, 'background', e.target.value === 'none' ? undefined : { type: e.target.value, ...(background.overlay ? { overlay: background.overlay } : {}) }))}
                    >
                        <option value="none">None</option>
                        <option value="color">Colour</option>
                        <option value="image">Image</option>
                    </select>
                </div>
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

            <div className="mb-3">
                <label className="form-label small" htmlFor={`${id}-text`}>
                    Text colour
                </label>
                <ColorInput id={`${id}-text`} value={style.typography?.color ?? null} onChange={(v) => set('typography.color', v)} disabled={disabled} />
            </div>

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
