import { useId, useState } from 'react';
import { FieldList } from '../../builder/FieldBuilder.jsx';

/**
 * Fields of a content type made in the admin: the field builder of custom blocks, plus where
 * each field appears on an item's page. Writes plain form inputs (fields JSON, display[key]).
 */
export function ContentTypeFields({ fields: initialFields, display: initialDisplay, types, displays, sectionOnly, errors, hasItems }) {
    const id = useId();
    const [fields, setFields] = useState(Array.isArray(initialFields) ? initialFields : []);
    const [display, setDisplay] = useState(initialDisplay ?? {});

    const shownAs = (field) => {
        const chosen = display[field.key];
        if (sectionOnly.includes(field.type)) return chosen === 'hidden' ? 'hidden' : 'section';
        return displays[chosen] ? chosen : 'details';
    };

    return (
        <div>
            <input type="hidden" name="fields" value={JSON.stringify(fields)} />
            {hasItems && (
                <p className="small alert alert-info pa-alert">
                    <i className="bi bi-info-circle" aria-hidden="true" /> This type has items. Adding fields is safe. Removing a field hides its values (they are kept and come back if you add the field again with the same key); changing a key works the same way.
                </p>
            )}
            <FieldList list={fields} path="fields" depth={1} errors={errors ?? {}} types={types} disabled={false} onChange={setFields} />

            {fields.length > 0 && (
                <fieldset className="mt-4">
                    <legend className="h6">Where each field appears on the item's page</legend>
                    <div className="table-responsive">
                        <table className="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th scope="col">Field</th>
                                    <th scope="col">Shown</th>
                                </tr>
                            </thead>
                            <tbody>
                                {fields.map((field, index) => (
                                    <tr key={`${field.key}-${index}`}>
                                        <td>
                                            <label htmlFor={`${id}-display-${index}`}>{field.label || field.key}</label>
                                            <span className="small text-body-secondary"> · {types[field.type] ?? field.type}</span>
                                        </td>
                                        <td>
                                            <select
                                                id={`${id}-display-${index}`}
                                                name={`display[${field.key}]`}
                                                className="form-select form-select-sm"
                                                value={shownAs(field)}
                                                onChange={(e) => setDisplay({ ...display, [field.key]: e.target.value })}
                                            >
                                                {Object.entries(displays)
                                                    .filter(([value]) => value !== 'details' || !sectionOnly.includes(field.type))
                                                    .map(([value, label]) => (
                                                        <option key={value} value={value}>
                                                            {label}
                                                        </option>
                                                    ))}
                                            </select>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="form-text">The details box at the top of the page suits short values (a date, a place, a choice). Long text, images, files, videos and lists get their own section.</p>
                </fieldset>
            )}
        </div>
    );
}
