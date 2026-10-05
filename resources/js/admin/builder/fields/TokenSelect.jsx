import { useBuilder } from '../store.js';

/**
 * Select a design token of one group (space, radius, shadow…). Value: {"$token": name}.
 *
 * @param {{id: string, group: string, value: Object|null, onChange: (value: Object|undefined) => void, emptyLabel?: string, disabled?: boolean}} props
 */
export default function TokenSelect({ id, group, value, onChange, emptyLabel = 'Default', disabled = false }) {
    const tokens = useBuilder((state) => state.definitions?.tokens?.[group] ?? []);

    return (
        <select id={id} className="form-select form-select-sm" value={value?.$token ?? ''} disabled={disabled} onChange={(e) => onChange(e.target.value ? { $token: e.target.value } : undefined)}>
            <option value="">{emptyLabel}</option>
            {tokens.map((token) => (
                <option key={token.token} value={token.token}>
                    {token.label} ({token.value})
                </option>
            ))}
        </select>
    );
}
