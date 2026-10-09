import { DndContext, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, arrayMove, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useEffect, useId, useRef, useState } from 'react';
import { adminHttp, errorMessage } from '../../http.js';
import { build, flatten, indent, move, moveDown, moveUp, outdent, remove, toPayload } from './tree.js';

let newKey = 0;

/**
 * Design → Menus → a menu: the Menu Builder (CMS-ARCHITECTURE.md §13.2). Items are edited as
 * an indented list: drag a row (or use the arrow buttons) to reorder; → places an item under
 * the one above it, ← moves it back out. Saved as a whole with "Save menu".
 */
export function MenuBuilder({ menu, endpoints, types, visibility, categories, maxDepth }) {
    const [rows, setRows] = useState(null);
    const [lockVersion, setLockVersion] = useState(0);
    const [open, setOpen] = useState(null);
    const [dirty, setDirty] = useState(false);
    const [status, setStatus] = useState({ kind: null, text: '' });
    const [errors, setErrors] = useState({});
    const [saving, setSaving] = useState(false);
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    useEffect(() => {
        adminHttp
            .get(endpoints.tree)
            .then(({ data }) => {
                setRows(flatten(data.data.items));
                setLockVersion(data.data.lock_version);
            })
            .catch((error) => setStatus({ kind: 'danger', text: errorMessage(error) }));
    }, [endpoints.tree]);

    // Leaving with unsaved changes asks first.
    useEffect(() => {
        const warn = (event) => {
            if (dirty) event.preventDefault();
        };
        window.addEventListener('beforeunload', warn);
        return () => window.removeEventListener('beforeunload', warn);
    }, [dirty]);

    if (rows === null) return <p className="text-body-secondary">{status.text || 'Loading the menu…'}</p>;

    const change = (next) => {
        setRows(next);
        setDirty(true);
        setStatus({ kind: null, text: '' });
    };
    const update = (index, values) => change(rows.map((row, i) => (i === index ? { ...row, item: { ...row.item, ...values } } : row)));
    const add = (type) => {
        const key = `new-${++newKey}`;
        change([...rows, { key, depth: 0, item: { type, own_label: '', visibility: 'everyone', new_tab: false } }]);
        setOpen(key);
    };

    async function save() {
        setSaving(true);
        setErrors({});
        try {
            const { data } = await adminHttp.put(endpoints.tree, { items: build(rows).map(toPayload), lock_version: lockVersion });
            setRows(flatten(data.data.items));
            setLockVersion(data.data.lock_version);
            setDirty(false);
            setOpen(null);
            setStatus({ kind: 'success', text: data.message ?? 'Menu saved.' });
        } catch (error) {
            // Errors name rows in save order ("items.n3.url"), which is the list order.
            const byRow = {};
            for (const [field, messages] of Object.entries(error?.response?.data?.errors ?? {})) {
                const match = /^items\.n(\d+)(?:\.(\w+))?/.exec(field);
                if (match) (byRow[rows[Number(match[1])]?.key] ??= []).push(...messages);
            }
            setErrors(byRow);
            if (Object.keys(byRow).length) setOpen(Object.keys(byRow)[0]);
            setStatus({ kind: 'danger', text: Object.keys(byRow).length ? 'Some items need attention (marked below).' : errorMessage(error) });
        } finally {
            setSaving(false);
        }
    }

    return (
        <section className="card pa-card" aria-labelledby="menu-items-heading">
            <div className="card-header d-flex flex-wrap gap-2 align-items-center">
                <h2 id="menu-items-heading" className="h6 mb-0 me-auto">
                    Items
                </h2>
                <AddItem types={types} onAdd={add} />
                <button type="button" className="btn btn-primary btn-sm" onClick={save} disabled={saving || !dirty}>
                    {saving ? 'Saving…' : 'Save menu'}
                </button>
            </div>
            <div className="card-body">
                <div aria-live="polite">{status.text && <div className={`alert alert-${status.kind} pa-alert py-2`}>{status.text}</div>}</div>
                {rows.length === 0 ? (
                    <p className="text-body-secondary mb-0">No items yet. Use “Add item” to add pages, content, categories or web addresses.</p>
                ) : (
                    <DndContext
                        sensors={sensors}
                        collisionDetection={closestCenter}
                        onDragEnd={({ active, over }) => {
                            if (!over || active.id === over.id) return;
                            const from = rows.findIndex((row) => row.key === active.id);
                            const to = rows.findIndex((row) => row.key === over.id);
                            // Position among the other rows, as if the dragged row were already removed.
                            const order = arrayMove(rows, from, to);
                            change(move(rows, from, order.findIndex((row) => row.key === active.id), maxDepth));
                        }}
                    >
                        <SortableContext items={rows.map((row) => row.key)} strategy={verticalListSortingStrategy}>
                            <ol className="pa-menu-builder list-unstyled mb-0" aria-label={`Items of ${menu.name}`}>
                                {rows.map((row, index) => (
                                    <Row
                                        key={row.key}
                                        row={row}
                                        open={open === row.key}
                                        errors={errors[row.key]}
                                        onToggle={() => setOpen(open === row.key ? null : row.key)}
                                        onChange={(values) => update(index, values)}
                                        actions={{
                                            up: () => change(moveUp(rows, index, maxDepth)),
                                            down: () => change(moveDown(rows, index, maxDepth)),
                                            indent: () => change(indent(rows, index, maxDepth)),
                                            outdent: () => change(outdent(rows, index, maxDepth)),
                                            remove: () => change(remove(rows, index)),
                                        }}
                                        types={types}
                                        visibility={visibility}
                                        categories={categories}
                                        targetsUrl={endpoints.targets}
                                    />
                                ))}
                            </ol>
                        </SortableContext>
                    </DndContext>
                )}
                {dirty && <p className="small text-body-secondary mt-3 mb-0">Unsaved changes. The website changes when you save.</p>}
            </div>
        </section>
    );
}

function AddItem({ types, onAdd }) {
    const id = useId();
    return (
        <div className="d-flex gap-1 align-items-center">
            <label htmlFor={id} className="visually-hidden">
                Type of item to add
            </label>
            <select
                id={id}
                className="form-select form-select-sm w-auto"
                value=""
                onChange={(event) => {
                    if (event.target.value) onAdd(event.target.value);
                }}
            >
                <option value="">Add item…</option>
                {Object.entries(types).map(([value, label]) => (
                    <option key={value} value={value}>
                        {label}
                    </option>
                ))}
            </select>
        </div>
    );
}

function Row({ row, open, errors, onToggle, onChange, actions, types, visibility, categories, targetsUrl }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({ id: row.key });
    const item = row.item;
    const name = item.own_label || item.label || '';
    const title = name || item.target?.title || (item.type === 'group' ? 'Heading' : 'New item');
    const panelId = `menu-item-${row.key}`;

    return (
        <li
            ref={setNodeRef}
            style={{ transform: CSS.Transform.toString(transform), transition, marginInlineStart: `${row.depth * 1.75}rem` }}
            className={`pa-menu-builder__row${isDragging ? ' is-dragging' : ''}${errors ? ' has-error' : ''}`}
        >
            <div className="pa-menu-builder__bar">
                <button type="button" className="btn btn-icon btn-sm pa-menu-builder__handle" aria-label={`Drag ${title}`} {...attributes} {...listeners}>
                    <i className="bi bi-grip-vertical" aria-hidden="true" />
                </button>
                <button type="button" className="pa-menu-builder__title" aria-expanded={open} aria-controls={panelId} onClick={onToggle}>
                    {item.icon && <i className={`bi ${item.icon} me-1`} aria-hidden="true" />}
                    <strong>{title}</strong>
                    <span className="small text-body-secondary ms-2">
                        {types[item.type]}
                        {item.url || item.target?.path ? ` · ${item.url || item.target.path}` : ''}
                    </span>
                    {item.public === false && item.type !== 'group' && <span className="pa-badge pa-badge--warning ms-2">Not published: hidden on the website</span>}
                    {item.visibility && item.visibility !== 'everyone' && <span className="pa-badge pa-badge--neutral ms-2">{visibility[item.visibility]}</span>}
                </button>
                <div className="pa-menu-builder__actions" role="group" aria-label={`Move ${title}`}>
                    <button type="button" className="btn btn-icon btn-sm" onClick={actions.up} aria-label={`Move ${title} up`}>
                        <i className="bi bi-arrow-up" aria-hidden="true" />
                    </button>
                    <button type="button" className="btn btn-icon btn-sm" onClick={actions.down} aria-label={`Move ${title} down`}>
                        <i className="bi bi-arrow-down" aria-hidden="true" />
                    </button>
                    <button type="button" className="btn btn-icon btn-sm" onClick={actions.outdent} aria-label={`Move ${title} out one level`}>
                        <i className="bi bi-arrow-left" aria-hidden="true" />
                    </button>
                    <button type="button" className="btn btn-icon btn-sm" onClick={actions.indent} aria-label={`Place ${title} under the item above`}>
                        <i className="bi bi-arrow-right" aria-hidden="true" />
                    </button>
                    <button type="button" className="btn btn-icon btn-sm text-danger" onClick={actions.remove} aria-label={`Remove ${title} and its sub-items`}>
                        <i className="bi bi-trash" aria-hidden="true" />
                    </button>
                </div>
            </div>
            {errors && (
                <ul className="small text-danger mb-1 ps-4" role="alert">
                    {errors.map((message) => (
                        <li key={message}>{message}</li>
                    ))}
                </ul>
            )}
            {open && (
                <div id={panelId} className="pa-menu-builder__panel">
                    <ItemForm item={item} onChange={onChange} types={types} visibility={visibility} categories={categories} targetsUrl={targetsUrl} />
                </div>
            )}
        </li>
    );
}

function ItemForm({ item, onChange, types, visibility, categories, targetsUrl }) {
    const id = useId();
    const linked = ['page', 'content'].includes(item.type);
    const needsLabel = ['external_url', 'custom_url', 'group'].includes(item.type);

    return (
        <div className="row g-3">
            <div className="col-md-6">
                <label className="form-label" htmlFor={`${id}-type`}>
                    Links to
                </label>
                <select id={`${id}-type`} className="form-select" value={item.type} onChange={(event) => onChange({ type: event.target.value, target: null, url: null })}>
                    {Object.entries(types).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="col-md-6">
                <label className="form-label" htmlFor={`${id}-label`}>
                    Label{needsLabel ? ' (required)' : ''}
                </label>
                <input
                    id={`${id}-label`}
                    className="form-control"
                    value={item.own_label ?? ''}
                    maxLength={191}
                    placeholder={item.target?.title ?? ''}
                    onChange={(event) => onChange({ own_label: event.target.value })}
                    aria-describedby={`${id}-label-help`}
                />
                {!needsLabel && (
                    <div id={`${id}-label-help`} className="form-text">
                        Leave empty to use the title of what it links to.
                    </div>
                )}
            </div>

            {linked && (
                <div className="col-12">
                    <TargetPicker item={item} targetsUrl={targetsUrl} onChange={onChange} />
                </div>
            )}
            {item.type === 'term' && (
                <div className="col-12">
                    <label className="form-label" htmlFor={`${id}-term`}>
                        Category
                    </label>
                    <select id={`${id}-term`} className="form-select" value={item.target?.id ?? ''} onChange={(event) => onChange({ target: event.target.value ? { entity: 'terms', id: Number(event.target.value), title: categories.find((c) => c.id === Number(event.target.value))?.name } : null })}>
                        <option value="">Choose a category…</option>
                        {categories.map((category) => (
                            <option key={category.id} value={category.id}>
                                {category.group}: {category.name}
                            </option>
                        ))}
                    </select>
                    <div className="form-text">Links to the listing page filtered by this category.</div>
                </div>
            )}
            {['external_url', 'custom_url'].includes(item.type) && (
                <div className="col-12">
                    <label className="form-label" htmlFor={`${id}-url`}>
                        {item.type === 'external_url' ? 'Web address' : 'Address on this site'}
                    </label>
                    <input
                        id={`${id}-url`}
                        className="form-control"
                        value={item.url ?? ''}
                        maxLength={2048}
                        placeholder={item.type === 'external_url' ? 'https://… or mailto:…' : '/donate or #contact'}
                        onChange={(event) => onChange({ url: event.target.value })}
                    />
                </div>
            )}

            <div className="col-md-4">
                <label className="form-label" htmlFor={`${id}-visibility`}>
                    Shown to
                </label>
                <select id={`${id}-visibility`} className="form-select" value={item.visibility ?? 'everyone'} onChange={(event) => onChange({ visibility: event.target.value })}>
                    {Object.entries(visibility).map(([value, label]) => (
                        <option key={value} value={value}>
                            {label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="col-md-4">
                <label className="form-label" htmlFor={`${id}-icon`}>
                    Icon (optional)
                </label>
                <input id={`${id}-icon`} className="form-control" value={item.icon ?? ''} placeholder="bi-house" maxLength={64} onChange={(event) => onChange({ icon: event.target.value })} />
            </div>
            <div className="col-md-4">
                <label className="form-label" htmlFor={`${id}-class`}>
                    CSS class (optional)
                </label>
                <input id={`${id}-class`} className="form-control" value={item.class ?? ''} maxLength={191} onChange={(event) => onChange({ class: event.target.value })} />
            </div>
            {item.type !== 'group' && (
                <div className="col-12">
                    <div className="form-check">
                        <input id={`${id}-tab`} type="checkbox" className="form-check-input" checked={Boolean(item.new_tab)} onChange={(event) => onChange({ new_tab: event.target.checked })} />
                        <label className="form-check-label" htmlFor={`${id}-tab`}>
                            Open in a new tab
                        </label>
                    </div>
                </div>
            )}
        </div>
    );
}

/** Search pages and content items (the builder's link targets). */
function TargetPicker({ item, targetsUrl, onChange }) {
    const id = useId();
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const timer = useRef(0);

    useEffect(() => {
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => {
            adminHttp
                .get(targetsUrl, { params: { q: query } })
                .then(({ data }) => setResults(data.data.filter((target) => (item.type === 'page' ? target.entity === 'pages' : target.entity !== 'pages'))))
                .catch(() => setResults([]));
        }, 250);
        return () => window.clearTimeout(timer.current);
    }, [query, targetsUrl, item.type]);

    return (
        <div>
            <p className="form-label mb-1">{item.type === 'page' ? 'Page' : 'Item'}</p>
            {item.target ? (
                <p className="mb-2">
                    <strong>{item.target.title}</strong> <span className="small text-body-secondary">{item.target.path}</span>{' '}
                    <button type="button" className="btn btn-link btn-sm p-0 ms-2" onClick={() => onChange({ target: null })}>
                        Change
                    </button>
                </p>
            ) : (
                <>
                    <label htmlFor={`${id}-q`} className="visually-hidden">
                        Search
                    </label>
                    <input id={`${id}-q`} type="search" className="form-control mb-2" placeholder="Search by title…" value={query} onChange={(event) => setQuery(event.target.value)} />
                    <ul className="list-unstyled pa-menu-builder__results mb-0">
                        {results.map((target) => (
                            <li key={`${target.entity}-${target.id}`}>
                                <button type="button" className="pa-add-menu__row" onClick={() => onChange({ target })}>
                                    <strong>{target.title}</strong>
                                    <span className="small text-body-secondary">
                                        {target.entity !== 'pages' ? `${target.entity} · ` : ''}
                                        {target.path}
                                    </span>
                                </button>
                            </li>
                        ))}
                        {results.length === 0 && <li className="small text-body-secondary">Nothing found.</li>}
                    </ul>
                </>
            )}
        </div>
    );
}
