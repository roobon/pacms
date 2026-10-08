import { useBuilder } from '../store.js';

/**
 * HTML block markup. Only roles with the "HTML blocks" permission can change it; others see
 * it read-only (the server refuses changes from them as well). Cleaned on save.
 *
 * @param {{id: string, value: string, max?: number, invalid: string, disabled?: boolean, describedBy?: string, onChange: (value: string) => void}} props
 */
export default function HtmlInput({ id, value, max, invalid, disabled = false, describedBy, onChange }) {
    const mayEdit = useBuilder((state) => Boolean(state.definitions?.permissions?.custom_html));

    return (
        <>
            <textarea
                id={id}
                rows={12}
                spellCheck={false}
                className={`form-control form-control-sm font-monospace${invalid}`}
                value={value}
                maxLength={max}
                disabled={disabled}
                readOnly={!mayEdit}
                aria-describedby={describedBy}
                onChange={(e) => onChange(e.target.value)}
            />
            {!mayEdit && <p className="small text-body-secondary mt-1 mb-0">Only roles with the “HTML blocks” permission can change this block. You can still move or delete it.</p>}
        </>
    );
}
