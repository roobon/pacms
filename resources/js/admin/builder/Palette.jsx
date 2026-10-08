import { useCallback, useEffect, useRef, useState } from 'react';
import { adminHttp } from '../http.js';
import { cache } from './hooks/useGlobals.js';
import Popover from './Popover.jsx';
import { useBuilder } from './store.js';
import { allowedInContext, canInsert, createNode } from './tree.js';

/**
 * Block palette (CMS-ARCHITECTURE.md §23.4): block types, templates and global blocks that
 * may be placed inside `parentUuid` (null = top level), with search.
 */
export default function Palette({ parentUuid, label, icon = 'bi-plus-lg', compact = false }) {
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState('blocks');
    const [query, setQuery] = useState('');
    const button = useRef(null);
    const context = useBuilder((state) => state.context);
    const tabs = [['blocks', 'Blocks'], context !== 'structure' && ['templates', 'Templates'], ['page', 'template'].includes(context) && ['globals', 'Global']].filter(Boolean);

    const close = useCallback(() => {
        setOpen(false);
        setQuery('');
    }, []);

    return (
        <span className="pa-add-menu">
            <button ref={button} type="button" className={compact ? 'btn btn-icon-sm' : 'btn btn-sm btn-primary'} aria-expanded={open} onClick={() => setOpen(!open)} title={label}>
                <i className={`bi ${icon}`} aria-hidden="true" />
                {compact ? <span className="visually-hidden">{label}</span> : <span> {label}</span>}
            </button>
            {open && (
                <Popover anchor={button} onClose={close} label={label} className="pa-add-menu__panel">
                    {tabs.length > 1 && (
                        <div className="pa-tabs pa-tabs--compact mb-2" role="tablist">
                            {tabs.map(([key, name]) => (
                                <button key={key} type="button" role="tab" aria-selected={tab === key} className={`pa-tabs__tab${tab === key ? ' is-active' : ''}`} onClick={() => setTab(key)}>
                                    {name}
                                </button>
                            ))}
                        </div>
                    )}
                    <input type="search" className="form-control form-control-sm mb-2" placeholder="Search" value={query} onChange={(e) => setQuery(e.target.value)} autoFocus aria-label="Search" />
                    {tab === 'blocks' && <BlockTypes parentUuid={parentUuid} query={query} onDone={close} />}
                    {tab === 'templates' && <Templates parentUuid={parentUuid} query={query} onDone={close} />}
                    {tab === 'globals' && <Globals parentUuid={parentUuid} query={query} onDone={close} />}
                    <button type="button" className="btn btn-sm btn-link" onClick={close}>
                        Close
                    </button>
                </Popover>
            )}
        </span>
    );
}

function BlockTypes({ parentUuid, query, onDone }) {
    const types = useBuilder((state) => state.types);
    const nodes = useBuilder((state) => state.nodes);
    const context = useBuilder((state) => state.context);
    const add = useBuilder((state) => state.add);
    const mayUseHtml = useBuilder((state) => Boolean(state.definitions?.permissions?.custom_html));

    const options = Object.values(types).filter(
        (type) =>
            type.slug !== 'global-ref' &&
            (type.slug !== 'html' || mayUseHtml) &&
            type.insertable !== false &&
            allowedInContext(type, context) &&
            canInsert(nodes, type.slug, parentUuid, types) &&
            `${type.label} ${type.category}`.toLowerCase().includes(query.toLowerCase()),
    );
    const groups = options.reduce((acc, type) => ({ ...acc, [type.category]: [...(acc[type.category] ?? []), type] }), {});

    if (options.length === 0) return <p className="small text-body-secondary mb-0">No blocks can be placed here.</p>;

    return Object.entries(groups).map(([category, list]) => (
        <div key={category} className="mb-2">
            <p className="pa-add-menu__group">{category}</p>
            <div className="pa-add-menu__grid">
                {list.map((type) => (
                    <button
                        key={type.slug}
                        type="button"
                        className="pa-add-menu__item"
                        title={type.description || undefined}
                        onClick={() => {
                            add(type.slug, parentUuid);
                            onDone();
                        }}
                    >
                        <i className={`bi ${type.icon}`} aria-hidden="true" />
                        <span>{type.label}</span>
                    </button>
                ))}
            </div>
        </div>
    ));
}

function Templates({ parentUuid, query, onDone }) {
    const endpoints = useBuilder((state) => state.definitions.endpoints);
    const insertNodes = useBuilder((state) => state.insertNodes);
    const [list, setList] = useState(cache.templates);
    const [message, setMessage] = useState(null);

    useEffect(() => {
        if (list) return;
        adminHttp.get(endpoints.templates).then(({ data }) => {
            cache.templates = data.data;
            setList(data.data);
        });
    }, [list, endpoints.templates]);

    async function insert(template) {
        const { data } = await adminHttp.get(endpoints.template.replace('__ID__', template.id));
        if (insertNodes(data.blocks, parentUuid)) onDone();
        else setMessage(`“${template.name}” cannot be placed here. Try the top level or another container.`);
    }

    if (!list) return <p className="small text-body-secondary">Loading templates…</p>;
    const filtered = list.filter((t) => `${t.name} ${t.category ?? ''}`.toLowerCase().includes(query.toLowerCase()));
    if (!filtered.length) return <p className="small text-body-secondary mb-0">No templates yet. Select a block and choose “Save as template”.</p>;

    return (
        <>
            {message && (
                <p className="small text-danger" role="alert">
                    {message}
                </p>
            )}
            <ul className="list-unstyled mb-2">
                {filtered.map((template) => (
                    <li key={template.id}>
                        <button type="button" className="pa-add-menu__row" onClick={() => insert(template)}>
                            <strong>{template.name}</strong>
                            <span className="small text-body-secondary">
                                {[template.category, template.scope].filter(Boolean).join(' · ')}
                                {template.description ? ` — ${template.description}` : ''}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
        </>
    );
}

function Globals({ parentUuid, query, onDone }) {
    const endpoints = useBuilder((state) => state.definitions.endpoints);
    const types = useBuilder((state) => state.types);
    const insertNodes = useBuilder((state) => state.insertNodes);
    const [list, setList] = useState(cache.globals);
    const [message, setMessage] = useState(null);

    useEffect(() => {
        if (list) return;
        adminHttp.get(endpoints.globals).then(({ data }) => {
            cache.globals = data.data;
            setList(data.data);
        });
    }, [list, endpoints.globals]);

    function insert(global) {
        const node = { ...createNode('global-ref', types), global_block_id: global.id };
        if (insertNodes([node], parentUuid)) onDone();
        else setMessage('A global block cannot be placed here.');
    }

    if (!list) return <p className="small text-body-secondary">Loading global blocks…</p>;
    const filtered = list.filter((g) => g.name.toLowerCase().includes(query.toLowerCase()));
    if (!filtered.length) return <p className="small text-body-secondary mb-0">No published global blocks yet.</p>;

    return (
        <>
            {message && (
                <p className="small text-danger" role="alert">
                    {message}
                </p>
            )}
            <ul className="list-unstyled mb-2">
                {filtered.map((global) => (
                    <li key={global.id}>
                        <button type="button" className="pa-add-menu__row" onClick={() => insert(global)}>
                            <strong>
                                <i className="bi bi-globe2" aria-hidden="true" /> {global.name}
                            </strong>
                            {global.description && <span className="small text-body-secondary">{global.description}</span>}
                        </button>
                    </li>
                ))}
            </ul>
        </>
    );
}

