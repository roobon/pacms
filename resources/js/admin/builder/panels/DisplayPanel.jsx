import { useId } from 'react';
import TokenSelect from '../fields/TokenSelect.jsx';
import { useBuilder } from '../store.js';

/**
 * Display mode + its options (CMS-ARCHITECTURE.md §7).
 *
 * @param {{type: Object, display: Object, onChange: (display: Object) => void, disabled?: boolean}} props
 */
export default function DisplayPanel({ type, display, onChange, disabled = false }) {
    const id = useId();
    const modes = useBuilder((state) => state.definitions?.display_modes ?? {});
    const allowed = type.capabilities.display_modes;
    if (!allowed.length) return null;

    const mode = display?.mode ?? allowed[0];
    const options = modes[mode]?.options ?? [];
    const set = (patch) => onChange({ ...display, mode, ...patch });

    return (
        <fieldset className="pa-panel">
            <legend className="pa-panel__title">Display</legend>
            {allowed.length > 1 && (
                <div className="mb-2">
                    <label className="form-label small" htmlFor={`${id}-mode`}>
                        Show as
                    </label>
                    <select id={`${id}-mode`} className="form-select form-select-sm" value={mode} disabled={disabled} onChange={(e) => set({ mode: e.target.value })}>
                        {allowed.map((key) => (
                            <option key={key} value={key}>
                                {modes[key]?.label ?? key}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            {options.includes('columns') && (
                <div className="row g-2 mb-2">
                    {['desktop', 'tablet', 'mobile'].map((device) => (
                        <div className="col-4" key={device}>
                            <label className="form-label small text-capitalize" htmlFor={`${id}-${device}`}>
                                {device}
                            </label>
                            <input
                                id={`${id}-${device}`}
                                type="number"
                                min={1}
                                max={6}
                                className="form-control form-control-sm"
                                value={display?.columns?.[device] ?? ''}
                                placeholder="auto"
                                disabled={disabled}
                                onChange={(e) => set({ columns: { ...(display?.columns ?? {}), [device]: e.target.value ? Number(e.target.value) : undefined } })}
                            />
                        </div>
                    ))}
                    <div className="form-text mt-0">Columns per device.</div>
                </div>
            )}

            {options.includes('card_style') && (
                <div className="mb-2">
                    <label className="form-label small" htmlFor={`${id}-card`}>
                        Card style
                    </label>
                    <select id={`${id}-card`} className="form-select form-select-sm" value={display?.card_style ?? 'elevated'} disabled={disabled} onChange={(e) => set({ card_style: e.target.value })}>
                        <option value="elevated">Shadow</option>
                        <option value="outline">Outline</option>
                        <option value="flat">Flat</option>
                    </select>
                </div>
            )}

            {options.includes('image_ratio') && (
                <div className="mb-2">
                    <label className="form-label small" htmlFor={`${id}-ratio`}>
                        Image shape
                    </label>
                    <select id={`${id}-ratio`} className="form-select form-select-sm" value={display?.image_ratio ?? '16:9'} disabled={disabled} onChange={(e) => set({ image_ratio: e.target.value })}>
                        {['16:9', '4:3', '3:2', '1:1', '21:9'].map((ratio) => (
                            <option key={ratio} value={ratio}>
                                {ratio}
                            </option>
                        ))}
                    </select>
                </div>
            )}

            {options.includes('gap') && (
                <div className="mb-2">
                    <label className="form-label small" htmlFor={`${id}-gap`}>
                        Spacing between items
                    </label>
                    <TokenSelect id={`${id}-gap`} group="space" value={display?.gap ?? null} onChange={(gap) => set({ gap })} disabled={disabled} />
                </div>
            )}

            {['first_open', 'allow_multiple_open', 'show_image'].filter((option) => options.includes(option)).map((option) => (
                <div className="form-check" key={option}>
                    <input id={`${id}-${option}`} type="checkbox" className="form-check-input" checked={display?.[option] ?? option === 'show_image'} disabled={disabled} onChange={(e) => set({ [option]: e.target.checked })} />
                    <label className="form-check-label small" htmlFor={`${id}-${option}`}>
                        {{ first_open: 'First item open', allow_multiple_open: 'Allow several open at once', show_image: 'Show images' }[option]}
                    </label>
                </div>
            ))}
        </fieldset>
    );
}
