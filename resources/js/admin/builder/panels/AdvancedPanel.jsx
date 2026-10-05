import { useId } from 'react';
import { useBuilder } from '../store.js';
import { setIn } from '../tree.js';

/**
 * Advanced settings (CMS-ARCHITECTURE.md §8.1): admin label, anchor, CSS classes,
 * per-device visibility, hidden flag, and custom attributes (permission-gated).
 *
 * @param {{node: Object, onChange: (patch: Object) => void, errors: Record<string, string[]>, disabled?: boolean}} props
 */
export default function AdvancedPanel({ node, onChange, errors, disabled = false }) {
    const id = useId();
    const mayUseAttributes = useBuilder((state) => state.definitions?.permissions?.custom_attributes);
    const advanced = node.advanced ?? {};
    const hideOn = advanced.visibility?.hide_on ?? [];
    const setAdvanced = (path, value) => onChange({ advanced: setIn(advanced, path, value) });

    return (
        <div>
            <div className="mb-3">
                <label className="form-label small" htmlFor={`${id}-name`}>
                    Block name (only shown in the editor)
                </label>
                <input id={`${id}-name`} className="form-control form-control-sm" maxLength={120} value={node.name ?? ''} disabled={disabled} onChange={(e) => onChange({ name: e.target.value || undefined })} />
            </div>

            <div className="mb-3">
                <label className="form-label small" htmlFor={`${id}-anchor`}>
                    Anchor (for links to this section)
                </label>
                <div className="input-group input-group-sm">
                    <span className="input-group-text">#</span>
                    <input id={`${id}-anchor`} className={`form-control${errors['advanced.anchor'] ? ' is-invalid' : ''}`} value={advanced.anchor ?? ''} disabled={disabled} onChange={(e) => setAdvanced('anchor', e.target.value.toLowerCase())} />
                </div>
                {errors['advanced.anchor'] && <div className="invalid-feedback d-block">{errors['advanced.anchor'][0]}</div>}
            </div>

            <div className="mb-3">
                <label className="form-label small" htmlFor={`${id}-classes`}>
                    CSS classes
                </label>
                <input
                    id={`${id}-classes`}
                    className={`form-control form-control-sm${errors['advanced.classes'] ? ' is-invalid' : ''}`}
                    value={(advanced.classes ?? []).join(' ')}
                    disabled={disabled}
                    onChange={(e) => setAdvanced('classes', e.target.value.trim() ? e.target.value.split(/\s+/) : undefined)}
                />
                {errors['advanced.classes'] && <div className="invalid-feedback d-block">{errors['advanced.classes'][0]}</div>}
            </div>

            <fieldset className="mb-3">
                <legend className="form-label small">Hide on</legend>
                {['desktop', 'tablet', 'mobile'].map((device) => (
                    <div className="form-check form-check-inline" key={device}>
                        <input
                            id={`${id}-hide-${device}`}
                            type="checkbox"
                            className="form-check-input"
                            checked={hideOn.includes(device)}
                            disabled={disabled}
                            onChange={(e) => {
                                const next = e.target.checked ? [...hideOn, device] : hideOn.filter((d) => d !== device);
                                setAdvanced('visibility', next.length ? { hide_on: next } : undefined);
                            }}
                        />
                        <label className="form-check-label small text-capitalize" htmlFor={`${id}-hide-${device}`}>
                            {device}
                        </label>
                    </div>
                ))}
                {errors['advanced.visibility.hide_on'] && <div className="invalid-feedback d-block">{errors['advanced.visibility.hide_on'][0]}</div>}
            </fieldset>

            <div className="form-check mb-3">
                <input id={`${id}-hidden`} type="checkbox" className="form-check-input" checked={Boolean(node.hidden)} disabled={disabled} onChange={(e) => onChange({ hidden: e.target.checked || undefined })} />
                <label className="form-check-label small" htmlFor={`${id}-hidden`}>
                    Hide this block (keep it, but don’t show it on the website)
                </label>
            </div>

            {mayUseAttributes && (
                <div className="mb-3">
                    <label className="form-label small" htmlFor={`${id}-attrs`}>
                        Custom attributes (one per line: data-name=value)
                    </label>
                    <textarea
                        id={`${id}-attrs`}
                        rows={3}
                        className={`form-control form-control-sm font-monospace${Object.keys(errors).some((k) => k.startsWith('advanced.attributes')) ? ' is-invalid' : ''}`}
                        defaultValue={Object.entries(advanced.attributes ?? {}).map(([k, v]) => `${k}=${v}`).join('\n')}
                        disabled={disabled}
                        onBlur={(e) => {
                            const attributes = Object.fromEntries(
                                e.target.value
                                    .split('\n')
                                    .map((line) => line.split('='))
                                    .filter(([key]) => key?.trim())
                                    .map(([key, ...rest]) => [key.trim(), rest.join('=').trim()]),
                            );
                            setAdvanced('attributes', Object.keys(attributes).length ? attributes : undefined);
                        }}
                    />
                    <div className="form-text">Only data-*, aria-*, role, title and lang are allowed.</div>
                </div>
            )}
        </div>
    );
}
