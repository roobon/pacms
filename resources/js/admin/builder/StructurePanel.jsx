import { useState } from 'react';
import { errorsFor, useBuilder } from './store.js';
import { canPlace } from './tree.js';

/**
 * The page's block tree: select, add (inside / after), move up/down, duplicate, delete.
 * Drag & drop and copy/paste arrive in Phase 5.
 */
export default function StructurePanel() {
    const nodes = useBuilder((state) => state.nodes);
    const readonly = useBuilder((state) => state.readonly);

    return (
        <nav className="pa-structure" aria-label="Page structure">
            <div className="pa-structure__header">
                <h2 className="h6 mb-0">Structure</h2>
                {!readonly && <AddBlockMenu parentUuid={null} label="Add block" />}
            </div>
            {nodes.length === 0 ? (
                <p className="small text-body-secondary p-3 mb-0">This page has no blocks yet. Start with a Hero or a Section.</p>
            ) : (
                <ul className="pa-tree" role="tree" aria-label="Blocks">
                    {nodes.map((node, index) => (
                        <TreeItem key={node.uuid} node={node} depth={0} index={index} count={nodes.length} />
                    ))}
                </ul>
            )}
        </nav>
    );
}

function TreeItem({ node, depth, index, count }) {
    const types = useBuilder((state) => state.types);
    const selected = useBuilder((state) => state.selected === node.uuid);
    const errors = useBuilder((state) => state.errors);
    const readonly = useBuilder((state) => state.readonly);
    const { select, move, duplicate, remove } = useBuilder.getState();
    const type = types[node.type];
    const hasErrors = Object.keys(errorsFor(errors, node.uuid)).length > 0;
    const acceptsChildren = Array.isArray(type?.capabilities.allowed_children);
    const label = node.name || summary(node) || type?.label || node.type;

    return (
        <li role="treeitem" aria-selected={selected} aria-expanded={acceptsChildren ? true : undefined} className="pa-tree__item">
            <div className={`pa-tree__row${selected ? ' is-selected' : ''}${node.hidden ? ' is-hidden' : ''}`} style={{ paddingLeft: `${0.5 + depth * 1}rem` }}>
                <button type="button" className="pa-tree__label" onClick={() => select(node.uuid)}>
                    <i className={`bi ${type?.icon ?? 'bi-square'}`} aria-hidden="true" />
                    <span className="text-truncate">{label}</span>
                    {node.hidden && <i className="bi bi-eye-slash" aria-label="hidden" />}
                    {hasErrors && <i className="bi bi-exclamation-circle-fill text-danger" aria-label="has errors" />}
                </button>
                {!readonly && (
                    <span className="pa-tree__actions">
                        {acceptsChildren && <AddBlockMenu parentUuid={node.uuid} label={`Add inside ${type?.label ?? ''}`} icon="bi-plus-square" compact />}
                        <IconButton icon="bi-arrow-up" label={`Move ${label} up`} disabled={index === 0} onClick={() => move(node.uuid, -1)} />
                        <IconButton icon="bi-arrow-down" label={`Move ${label} down`} disabled={index === count - 1} onClick={() => move(node.uuid, 1)} />
                        <IconButton icon="bi-copy" label={`Duplicate ${label}`} onClick={() => duplicate(node.uuid)} />
                        <IconButton icon="bi-trash" label={`Delete ${label}`} danger onClick={() => window.confirm(`Delete “${label}” and everything inside it?`) && remove(node.uuid)} />
                    </span>
                )}
            </div>
            {node.children?.length > 0 && (
                <ul role="group" className="pa-tree">
                    {node.children.map((child, i) => (
                        <TreeItem key={child.uuid} node={child} depth={depth + 1} index={i} count={node.children.length} />
                    ))}
                </ul>
            )}
        </li>
    );
}

function IconButton({ icon, label, onClick, disabled = false, danger = false }) {
    return (
        <button type="button" className={`btn btn-icon-sm${danger ? ' text-danger' : ''}`} onClick={onClick} disabled={disabled} title={label}>
            <i className={`bi ${icon}`} aria-hidden="true" />
            <span className="visually-hidden">{label}</span>
        </button>
    );
}

/**
 * Palette of block types that may be placed in `parentUuid` (null = page level).
 */
export function AddBlockMenu({ parentUuid, label, icon = 'bi-plus-lg', compact = false }) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const types = useBuilder((state) => state.types);
    const nodes = useBuilder((state) => state.nodes);
    const add = useBuilder((state) => state.add);

    const parentNode = parentUuid ? findIn(nodes, parentUuid) : null;
    const parentType = parentNode ? types[parentNode.type] : null;
    const options = Object.values(types).filter((type) => canPlace(type, parentType) && `${type.label} ${type.category}`.toLowerCase().includes(query.toLowerCase()));
    const groups = options.reduce((acc, type) => ({ ...acc, [type.category]: [...(acc[type.category] ?? []), type] }), {});

    return (
        <span className="pa-add-menu">
            <button type="button" className={compact ? 'btn btn-icon-sm' : 'btn btn-sm btn-primary'} aria-expanded={open} onClick={() => setOpen(!open)} title={label}>
                <i className={`bi ${icon}`} aria-hidden="true" />
                {compact ? <span className="visually-hidden">{label}</span> : <span> {label}</span>}
            </button>
            {open && (
                <div className="pa-add-menu__panel" role="dialog" aria-label={label} onKeyDown={(e) => e.key === 'Escape' && setOpen(false)}>
                    <input type="search" className="form-control form-control-sm mb-2" placeholder="Search blocks" value={query} onChange={(e) => setQuery(e.target.value)} autoFocus aria-label="Search blocks" />
                    {options.length === 0 && <p className="small text-body-secondary mb-0">No blocks can be placed here.</p>}
                    {Object.entries(groups).map(([category, list]) => (
                        <div key={category} className="mb-2">
                            <p className="pa-add-menu__group">{category}</p>
                            <div className="pa-add-menu__grid">
                                {list.map((type) => (
                                    <button
                                        key={type.slug}
                                        type="button"
                                        className="pa-add-menu__item"
                                        onClick={() => {
                                            add(type.slug, parentUuid);
                                            setOpen(false);
                                            setQuery('');
                                        }}
                                    >
                                        <i className={`bi ${type.icon}`} aria-hidden="true" />
                                        <span>{type.label}</span>
                                    </button>
                                ))}
                            </div>
                        </div>
                    ))}
                    <button type="button" className="btn btn-sm btn-link" onClick={() => setOpen(false)}>
                        Close
                    </button>
                </div>
            )}
        </span>
    );
}

function findIn(nodes, uuid) {
    for (const node of nodes) {
        if (node.uuid === uuid) return node;
        const child = findIn(node.children ?? [], uuid);
        if (child) return child;
    }
    return null;
}

function summary(node) {
    const text = node.content?.text ?? node.content?.title ?? node.content?.heading ?? node.content?.label;
    return typeof text === 'string' && text ? text.slice(0, 40) : null;
}
