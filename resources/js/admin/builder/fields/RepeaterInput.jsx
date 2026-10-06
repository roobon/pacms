import FieldInput from './FieldInput.jsx';

/**
 * Repeater rows with add, remove, duplicate and reorder (CMS-ARCHITECTURE.md §5.3).
 * Each row is a set of sub-fields; rows can be collapsed to keep long lists manageable.
 *
 * @param {{field: Object, value: Array<Object>, onChange: (rows: Array<Object>) => void, errors: Record<string, string[]>, path: string, disabled?: boolean}} props
 */
export default function RepeaterInput({ field, value, onChange, errors, path, disabled = false }) {
    const max = field.max_items ?? 50;
    const titleKey = field.fields.find((f) => ['title', 'question', 'label', 'name'].includes(f.key))?.key;

    const update = (index, row) => onChange(value.map((current, i) => (i === index ? row : current)));
    const remove = (index) => onChange(value.filter((_, i) => i !== index));
    const duplicate = (index) => onChange([...value.slice(0, index + 1), structuredClone(value[index]), ...value.slice(index + 1)]);
    const move = (index, delta) => {
        const target = index + delta;
        if (target < 0 || target >= value.length) return;
        const copy = [...value];
        [copy[index], copy[target]] = [copy[target], copy[index]];
        onChange(copy);
    };

    return (
        <div className="pa-repeater">
            {value.map((row, index) => (
                <details key={index} className="pa-repeater__row" open={value.length <= 3}>
                    <summary className="pa-repeater__summary">
                        <span className="text-truncate">
                            {index + 1}. {(titleKey && row[titleKey]) || 'Item'}
                        </span>
                        <span className="pa-repeater__actions">
                            <button type="button" className="btn btn-sm btn-icon-sm" onClick={() => move(index, -1)} disabled={disabled || index === 0} title="Move up">
                                <i className="bi bi-arrow-up" aria-hidden="true" />
                                <span className="visually-hidden">Move item {index + 1} up</span>
                            </button>
                            <button type="button" className="btn btn-sm btn-icon-sm" onClick={() => move(index, 1)} disabled={disabled || index === value.length - 1} title="Move down">
                                <i className="bi bi-arrow-down" aria-hidden="true" />
                                <span className="visually-hidden">Move item {index + 1} down</span>
                            </button>
                            <button type="button" className="btn btn-sm btn-icon-sm" onClick={() => duplicate(index)} disabled={disabled || value.length >= max} title="Duplicate">
                                <i className="bi bi-copy" aria-hidden="true" />
                                <span className="visually-hidden">Duplicate item {index + 1}</span>
                            </button>
                            <button type="button" className="btn btn-sm btn-icon-sm text-danger" onClick={() => remove(index)} disabled={disabled} title="Remove">
                                <i className="bi bi-trash" aria-hidden="true" />
                                <span className="visually-hidden">Remove item {index + 1}</span>
                            </button>
                        </span>
                    </summary>
                    <div className="pa-repeater__body">
                        {field.fields.map((sub) => (
                            <FieldInput
                                key={sub.key}
                                field={sub}
                                value={row[sub.key]}
                                path={`${path}.${index}.${sub.key}`}
                                errors={errors}
                                disabled={disabled}
                                onChange={(next) => update(index, next === undefined ? omit(row, sub.key) : { ...row, [sub.key]: next })}
                            />
                        ))}
                    </div>
                </details>
            ))}
            <button type="button" className="btn btn-sm btn-outline-primary" onClick={() => onChange([...value, {}])} disabled={disabled || value.length >= max}>
                <i className="bi bi-plus-lg" aria-hidden="true" /> Add item
            </button>
        </div>
    );
}

function omit(object, key) {
    const copy = { ...object };
    delete copy[key];
    return copy;
}
