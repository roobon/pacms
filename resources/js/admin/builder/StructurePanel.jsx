import { DndContext, DragOverlay, KeyboardSensor, PointerSensor, closestCenter, useSensor, useSensors } from '@dnd-kit/core';
import { SortableContext, sortableKeyboardCoordinates, useSortable, verticalListSortingStrategy } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { useCallback, useMemo, useRef, useState } from 'react';
import ActionDialog from './ActionDialog.jsx';
import Palette from './Palette.jsx';
import Popover from './Popover.jsx';
import { errorsFor, useBuilder } from './store.js';
import { canInsert, findNode, flatten, labelOf, projectDrop, removeNode } from './tree.js';
import { adminHttp } from '../http.js';

/** Horizontal drag distance for one nesting level. */
const INDENT = 18;

/**
 * The block tree: select, add, drag & drop (mouse, touch and keyboard), move, indent,
 * duplicate, copy/paste, hide, save as template, convert to global block, delete.
 */
export default function StructurePanel() {
    const nodes = useBuilder((state) => state.nodes);
    const readonly = useBuilder((state) => state.readonly);
    const canUndo = useBuilder((state) => state.past.length > 0);
    const canRedo = useBuilder((state) => state.future.length > 0);
    const context = useBuilder((state) => state.context);
    const { undo, redo, paste } = useBuilder.getState();

    return (
        <nav className="pa-structure" aria-label="Block structure">
            <div className="pa-structure__header">
                <h2 className="h6 mb-0">Structure</h2>
                {!readonly && <Palette parentUuid={null} label="Add block" />}
            </div>
            {!readonly && (
                <div className="pa-structure__toolbar" role="toolbar" aria-label="Edit">
                    <IconButton icon="bi-arrow-counterclockwise" label="Undo (Ctrl+Z)" disabled={!canUndo} onClick={undo} />
                    <IconButton icon="bi-arrow-clockwise" label="Redo (Ctrl+Shift+Z)" disabled={!canRedo} onClick={redo} />
                    <IconButton icon="bi-clipboard" label="Paste (Ctrl+V)" onClick={paste} />
                </div>
            )}
            {nodes.length === 0 ? (
                <p className="small text-body-secondary p-3 mb-0">
                    {context === 'structure' ? 'Build the block’s layout here, then link block settings to your fields.' : 'Nothing here yet. Add a block or insert a template.'}
                </p>
            ) : (
                <SortableTree />
            )}
        </nav>
    );
}

function SortableTree() {
    const nodes = useBuilder((state) => state.nodes);
    const types = useBuilder((state) => state.types);
    const readonly = useBuilder((state) => state.readonly);
    const [collapsed, setCollapsed] = useState(() => new Set());
    const [activeId, setActiveId] = useState(null);
    const [overId, setOverId] = useState(null);
    const [offsetLeft, setOffsetLeft] = useState(0);

    const rows = useMemo(() => flatten(nodes, collapsed), [nodes, collapsed]);
    const visibleRows = useMemo(() => {
        if (!activeId) return rows;
        const active = findNode(nodes, activeId)?.node;
        const hidden = new Set(flatten(active?.children ?? []).map((row) => row.uuid));
        return rows.filter((row) => !hidden.has(row.uuid));
    }, [rows, activeId, nodes]);

    const projection = activeId && overId ? projectDrop(visibleRows, activeId, visibleRows.findIndex((row) => row.uuid === overId), Math.round(offsetLeft / INDENT)) : null;
    const activeNode = activeId ? findNode(nodes, activeId)?.node : null;
    const projectionValid = projection && activeNode ? canInsert(removeNode(nodes, activeId), activeNode.type, projection.parentUuid, types) : false;

    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 6 } }), useSensor(KeyboardSensor, { coordinateGetter: sortableKeyboardCoordinates }));

    function reset() {
        setActiveId(null);
        setOverId(null);
        setOffsetLeft(0);
    }

    function toggle(uuid) {
        setCollapsed((current) => {
            const next = new Set(current);
            if (next.has(uuid)) next.delete(uuid);
            else next.add(uuid);
            return next;
        });
    }

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragStart={({ active }) => {
                setActiveId(active.id);
                setOverId(active.id);
            }}
            onDragMove={({ delta }) => setOffsetLeft(delta.x)}
            onDragOver={({ over }) => setOverId(over?.id ?? null)}
            onDragEnd={() => {
                if (projection && projectionValid) useBuilder.getState().moveTo(activeId, projection.parentUuid, projection.index);
                else if (projection) useBuilder.getState().announce('That block cannot be placed there.');
                reset();
            }}
            onDragCancel={reset}
            accessibility={{ announcements: announcements(nodes, types) }}
        >
            <SortableContext items={visibleRows.map((row) => row.uuid)} strategy={verticalListSortingStrategy}>
                <ul className="pa-tree" role="tree" aria-label="Blocks">
                    {visibleRows.map((row) => (
                        <TreeRow
                            key={row.uuid}
                            row={row}
                            depth={row.uuid === activeId && projection ? projection.depth : row.depth}
                            invalid={row.uuid === activeId && projection && !projectionValid}
                            collapsed={collapsed.has(row.uuid)}
                            onToggle={() => toggle(row.uuid)}
                            readonly={readonly}
                        />
                    ))}
                </ul>
            </SortableContext>
            <DragOverlay dropAnimation={null}>{activeNode ? <div className="pa-tree__ghost">{labelOf(activeNode, types)}</div> : null}</DragOverlay>
        </DndContext>
    );
}

function TreeRow({ row, depth, invalid, collapsed, onToggle, readonly }) {
    const { node } = row;
    const types = useBuilder((state) => state.types);
    const selected = useBuilder((state) => state.selected === node.uuid);
    const errors = useBuilder((state) => state.errors);
    const { select, move, duplicate, remove, toggleHidden, indent, outdent } = useBuilder.getState();
    const { attributes, listeners, setNodeRef, setActivatorNodeRef, transform, transition, isDragging } = useSortable({ id: node.uuid, disabled: readonly });

    const type = types[node.type];
    const label = labelOf(node, types);
    const hasErrors = Object.keys(errorsFor(errors, node.uuid)).length > 0;
    const acceptsChildren = Array.isArray(type?.capabilities.allowed_children);
    const hasChildren = (node.children?.length ?? 0) > 0;

    function onKeyDown(event) {
        if (readonly || !event.altKey) return;
        const actions = { ArrowUp: () => move(node.uuid, -1), ArrowDown: () => move(node.uuid, 1), ArrowRight: () => indent(node.uuid), ArrowLeft: () => outdent(node.uuid) };
        if (actions[event.key]) {
            event.preventDefault();
            actions[event.key]();
        }
    }

    return (
        <li
            ref={setNodeRef}
            role="treeitem"
            aria-selected={selected}
            aria-level={depth + 1}
            aria-expanded={hasChildren ? !collapsed : undefined}
            className={`pa-tree__item${isDragging ? ' is-dragging' : ''}`}
            style={{ transform: CSS.Translate.toString(transform), transition }}
        >
            <div className={`pa-tree__row${selected ? ' is-selected' : ''}${node.hidden ? ' is-hidden' : ''}${invalid ? ' is-invalid' : ''}`} style={{ paddingLeft: `${0.25 + depth * (INDENT / 16)}rem` }}>
                {!readonly && (
                    <button type="button" ref={setActivatorNodeRef} className="pa-tree__handle" aria-label={`Drag ${label}`} {...attributes} {...listeners}>
                        <i className="bi bi-grip-vertical" aria-hidden="true" />
                    </button>
                )}
                {hasChildren ? (
                    <button type="button" className="pa-tree__twisty" onClick={onToggle} aria-label={collapsed ? `Expand ${label}` : `Collapse ${label}`}>
                        <i className={`bi ${collapsed ? 'bi-chevron-right' : 'bi-chevron-down'}`} aria-hidden="true" />
                    </button>
                ) : (
                    <span className="pa-tree__twisty" aria-hidden="true" />
                )}
                <button type="button" className="pa-tree__label" onClick={() => select(node.uuid)} onKeyDown={onKeyDown}>
                    <i className={`bi ${type?.icon ?? 'bi-square'}`} aria-hidden="true" />
                    <span className="text-truncate">{label}</span>
                    {node.type === 'global-ref' && <span className="pa-badge pa-badge--info">Global</span>}
                    {type?.custom && <span className="pa-badge pa-badge--neutral">Custom</span>}
                    {node.hidden && <i className="bi bi-eye-slash" aria-label="hidden" />}
                    {hasErrors && <i className="bi bi-exclamation-circle-fill text-danger" aria-label="has errors" />}
                </button>
                {!readonly && (
                    <span className="pa-tree__actions">
                        {acceptsChildren && <Palette parentUuid={node.uuid} label={`Add inside ${type?.label ?? ''}`} icon="bi-plus-square" compact />}
                        <IconButton icon="bi-arrow-up" label={`Move ${label} up (Alt+↑)`} disabled={row.index === 0} onClick={() => move(node.uuid, -1)} />
                        <IconButton icon="bi-arrow-down" label={`Move ${label} down (Alt+↓)`} onClick={() => move(node.uuid, 1)} />
                        <IconButton icon={node.hidden ? 'bi-eye' : 'bi-eye-slash'} label={node.hidden ? `Show ${label}` : `Hide ${label}`} onClick={() => toggleHidden(node.uuid)} />
                        <IconButton icon="bi-copy" label={`Duplicate ${label} (Ctrl+D)`} onClick={() => duplicate(node.uuid)} />
                        <MoreMenu node={node} label={label} />
                        <IconButton icon="bi-trash" label={`Delete ${label}`} danger onClick={() => window.confirm(`Delete “${label}” and everything inside it?`) && remove(node.uuid)} />
                    </span>
                )}
            </div>
        </li>
    );
}

/** Copy, paste, save as template, convert to global block. */
function MoreMenu({ node, label }) {
    const [open, setOpen] = useState(false);
    const [dialog, setDialog] = useState(null);
    const button = useRef(null);
    const closeMenu = useCallback(() => setOpen(false), []);
    const context = useBuilder((state) => state.context);
    const definitions = useBuilder((state) => state.definitions);
    const { copy, paste, replaceWith, select, announce } = useBuilder.getState();
    const permissions = definitions?.permissions ?? {};
    const endpoints = definitions?.endpoints ?? {};

    async function saveTemplate({ name, scope, category }) {
        await adminHttp.post(endpoints.storeTemplate, { name, scope, category, blocks: [node] });
        announce(`Saved “${name}” as a template`);
    }

    async function convertToGlobal({ name }) {
        const { data } = await adminHttp.post(endpoints.storeGlobal, { name, blocks: [node] });
        replaceWith(node.uuid, [{ type: 'global-ref', global_block_id: data.data.id, uuid: node.uuid }]);
        announce(`“${name}” is now a global block`);
    }

    const items = [
        { label: 'Copy', icon: 'bi-files', onClick: () => copy(node.uuid) },
        {
            label: 'Paste after',
            icon: 'bi-clipboard',
            onClick: () => {
                select(node.uuid);
                paste();
            },
        },
        permissions.templates && context !== 'structure' && { label: 'Save as template…', icon: 'bi-layout-wtf', onClick: () => setDialog('template') },
        permissions.global_blocks && context === 'page' && node.type !== 'global-ref' && { label: 'Convert to global block…', icon: 'bi-globe2', onClick: () => setDialog('global') },
    ].filter(Boolean);

    return (
        <span className="pa-add-menu">
            <button ref={button} type="button" className="btn btn-icon-sm" aria-haspopup="menu" aria-expanded={open} title={`More actions for ${label}`} onClick={() => setOpen(!open)}>
                <i className="bi bi-three-dots" aria-hidden="true" />
                <span className="visually-hidden">More actions for {label}</span>
            </button>
            {open && (
                <Popover anchor={button} onClose={closeMenu} align="end" role="menu" label={`More actions for ${label}`} className="pa-menu">
                    {items.map((item) => (
                        <li key={item.label} role="none">
                            <button
                                type="button"
                                role="menuitem"
                                className="pa-menu__item"
                                onClick={() => {
                                    setOpen(false);
                                    item.onClick();
                                }}
                            >
                                <i className={`bi ${item.icon}`} aria-hidden="true" /> {item.label}
                            </button>
                        </li>
                    ))}
                </Popover>
            )}
            {dialog === 'template' && (
                <ActionDialog
                    title="Save as template"
                    submitLabel="Save template"
                    fields={[
                        { key: 'name', label: 'Template name', required: true, initial: label },
                        { key: 'scope', label: 'Type', type: 'select', options: { block: 'Single block', section: 'Section', page: 'Whole page' }, initial: ['section', 'hero', 'container'].includes(node.type) ? 'section' : 'block' },
                        { key: 'category', label: 'Category (optional)' },
                    ]}
                    onSubmit={saveTemplate}
                    onClose={() => setDialog(null)}
                />
            )}
            {dialog === 'global' && (
                <ActionDialog
                    title="Convert to global block"
                    description="The block moves into a new global block and is placed here by reference. Edit it once under Design → Global blocks to change it everywhere."
                    submitLabel="Convert"
                    fields={[{ key: 'name', label: 'Global block name', required: true, initial: label }]}
                    onSubmit={convertToGlobal}
                    onClose={() => setDialog(null)}
                />
            )}
        </span>
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


/** Screen-reader announcements for drag & drop. */
function announcements(nodes, types) {
    const name = (id) => {
        const node = findNode(nodes, id)?.node;
        return node ? labelOf(node, types) : 'block';
    };
    return {
        onDragStart: ({ active }) => `Picked up ${name(active.id)}. Use the arrow keys to move, space to drop, escape to cancel.`,
        onDragOver: ({ active, over }) => (over ? `${name(active.id)} is over ${name(over.id)}.` : `${name(active.id)} is no longer over a block.`),
        onDragEnd: ({ active }) => `${name(active.id)} dropped.`,
        onDragCancel: ({ active }) => `Moving ${name(active.id)} was cancelled.`,
    };
}
