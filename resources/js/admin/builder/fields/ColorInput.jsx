import { useBuilder } from '../store.js';

/**
 * Colour: a theme colour token (preferred, follows branding) or a custom hex value.
 *
 * @param {{id: string, value: Object|string|null, onChange: (value: Object|string|undefined) => void, disabled?: boolean}} props
 */
export default function ColorInput({ id, value, onChange, disabled = false }) {
    const colors = useBuilder((state) => state.definitions?.tokens?.color ?? []);
    const isToken = value && typeof value === 'object';
    const mode = value == null ? '' : isToken ? value.$token : 'custom';

    return (
        <div className="d-flex gap-2 align-items-center">
            <select
                id={id}
                className="form-select form-select-sm"
                value={mode}
                disabled={disabled}
                onChange={(e) => {
                    const next = e.target.value;
                    if (next === '') onChange(undefined);
                    else if (next === 'custom') onChange(typeof value === 'string' ? value : '#0A6B66');
                    else onChange({ $token: next });
                }}
            >
                <option value="">Default</option>
                {colors.map((token) => (
                    <option key={token.token} value={token.token}>
                        {token.label}
                    </option>
                ))}
                <option value="custom">Custom colour…</option>
            </select>
            {mode === 'custom' && (
                <input type="color" className="form-control form-control-color form-control-sm" value={typeof value === 'string' ? value.slice(0, 7) : '#0A6B66'} disabled={disabled} onChange={(e) => onChange(e.target.value.toUpperCase())} aria-label="Custom colour" />
            )}
            {isToken && <span className="pa-swatch" style={{ background: colors.find((c) => c.token === value.$token)?.value }} aria-hidden="true" />}
        </div>
    );
}
