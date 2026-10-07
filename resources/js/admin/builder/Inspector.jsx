import { useId, useState } from 'react';
import { adminHttp } from '../http.js';
import BindableField from './fields/BindableField.jsx';
import FieldInput from './fields/FieldInput.jsx';
import AdvancedPanel from './panels/AdvancedPanel.jsx';
import DisplayPanel from './panels/DisplayPanel.jsx';
import LayoutPanel from './panels/LayoutPanel.jsx';
import SourcePanel from './panels/SourcePanel.jsx';
import StylePanel from './panels/StylePanel.jsx';
import { useGlobals } from './hooks/useGlobals.js';
import { errorsFor, useBuilder } from './store.js';
import { bindingScope, createNode, findNode } from './tree.js';

const TABS = ['content', 'layout', 'style', 'advanced'];
const DEVICE_LABELS = { tablet: 'tablets (up to 991px)', mobile: 'phones (up to 767px)' };

/**
 * Settings of the selected block in four tabs: Content · Layout · Style · Advanced
 * (CMS-ARCHITECTURE.md §8.1). With the tablet or phone preview active, Layout and Style
 * edit that device's overrides (§9).
 */
export default function Inspector() {
    const selected = useBuilder((state) => state.selected);
    const nodes = useBuilder((state) => state.nodes);
    const types = useBuilder((state) => state.types);
    const allErrors = useBuilder((state) => state.errors);
    const readonly = useBuilder((state) => state.readonly);
    const device = useBuilder((state) => state.device);
    const context = useBuilder((state) => state.context);
    const fields = useBuilder((state) => state.fields);
    const update = useBuilder((state) => state.update);
    const rename = useBuilder((state) => state.rename);
    const [tab, setTab] = useState('content');
    const nameId = useId();

    const found = selected ? findNode(nodes, selected) : null;
    if (!found) {
        return (
            <div className="pa-inspector pa-inspector--empty">
                <i className="bi bi-hand-index" aria-hidden="true" />
                <p className="mb-0">Select a block in the structure or the preview to edit it.</p>
            </div>
        );
    }

    const { node } = found;
    const type = types[node.type];
    const errors = errorsFor(allErrors, node.uuid);
    const tabErrors = (name) => Object.keys(errors).some((key) => key.startsWith(name === 'content' ? 'content' : name) || (name === 'content' && (key.startsWith('source') || key.startsWith('display'))));
    const patch = (changes, mergeKey = null) => update(node.uuid, (current) => clean({ ...current, ...changes }), mergeKey);
    const dynamic = node.source?.mode === 'dynamic';
    const override = device !== 'desktop' && (tab === 'layout' || tab === 'style');
    const scope = context === 'structure' ? bindingScope(nodes, node.uuid, fields ?? []) : null;

    function setContent(key, value) {
        update(
            node.uuid,
            (current) => {
                const content = { ...(current.content ?? {}) };
                if (value === undefined || value === null || value === '') delete content[key];
                else content[key] = value;
                return { ...current, content };
            },
            `content.${key}`,
        );
    }

    function setLayout(layout) {
        if (device !== 'desktop') return setResponsive('layout', layout);
        update(
            node.uuid,
            (current) => {
                const next = clean({ ...current, layout });
                // Columns: keep the number of column blocks in step with the chosen layout.
                const spans = layout?.columns?.desktop;
                if (current.type === 'columns' && Array.isArray(spans) && spans.length > (current.children?.length ?? 0)) {
                    const extra = Array.from({ length: spans.length - (current.children?.length ?? 0) }, () => createNode('column', types));
                    next.children = [...(current.children ?? []), ...extra];
                }
                return next;
            },
            'layout',
        );
    }

    function setStyle(style) {
        if (device !== 'desktop') return setResponsive('style', style);
        patch({ style }, 'style');
    }

    function setResponsive(section, value) {
        update(
            node.uuid,
            (current) => {
                const responsive = { ...(current.responsive ?? {}) };
                const forDevice = { ...(responsive[device] ?? {}), [section]: value };
                if (!value || !Object.keys(value).length) delete forDevice[section];
                if (Object.keys(forDevice).length) responsive[device] = forDevice;
                else delete responsive[device];
                return clean({ ...current, responsive });
            },
            `responsive.${device}.${section}`,
        );
    }

    // In override mode the panels edit the device's values; empty means "same as desktop".
    const view = override ? { ...node, layout: node.responsive?.[device]?.layout ?? {}, style: node.responsive?.[device]?.style ?? {} } : node;
    const hasOverrides = Boolean(node.responsive?.[device]?.[tab]);

    return (
        <div className="pa-inspector">
            <div className="pa-inspector__header">
                <i className={`bi ${type?.icon ?? 'bi-square'}`} aria-hidden="true" />
                <div className="flex-grow-1 min-w-0">
                    <strong>{type?.label || node.type}</strong>
                    {type?.description && <p className="small text-body-secondary mb-1">{type.description}</p>}
                    <label className="visually-hidden" htmlFor={nameId}>
                        Name in the structure
                    </label>
                    <input id={nameId} className="form-control form-control-sm" placeholder="Name in the structure (optional)" value={node.name ?? ''} maxLength={120} disabled={readonly} onChange={(e) => rename(node.uuid, e.target.value)} />
                </div>
            </div>

            {errors[''] && (
                <div className="alert alert-danger pa-alert small" role="alert">
                    {errors[''][0]}
                </div>
            )}

            <div className="pa-tabs" role="tablist" aria-label="Block settings">
                {TABS.map((name) => (
                    <button
                        key={name}
                        type="button"
                        role="tab"
                        id={`inspector-tab-${name}`}
                        aria-selected={tab === name}
                        aria-controls="inspector-panel"
                        className={`pa-tabs__tab${tab === name ? ' is-active' : ''}`}
                        onClick={() => setTab(name)}
                    >
                        {name[0].toUpperCase() + name.slice(1)}
                        {tabErrors(name) && <span className="pa-tabs__dot" aria-label="has errors" />}
                    </button>
                ))}
            </div>

            <div className="pa-inspector__body" role="tabpanel" id="inspector-panel" aria-labelledby={`inspector-tab-${tab}`}>
                {override && (
                    <div className="pa-override-note small" role="note">
                        <i className="bi bi-phone" aria-hidden="true" /> Changes here apply only on {DEVICE_LABELS[device]}. Empty settings use the desktop values.
                        {hasOverrides && !readonly && (
                            <button type="button" className="btn btn-sm btn-link p-0 ms-1" onClick={() => setResponsive(tab, undefined)}>
                                Clear
                            </button>
                        )}
                    </div>
                )}

                {tab === 'content' && type && node.type === 'global-ref' && <GlobalRefSettings node={node} errors={errors} disabled={readonly} onPick={(id) => patch({ global_block_id: id })} />}

                {tab === 'content' && type && node.type !== 'global-ref' && (
                    <>
                        <SourcePanel type={type} source={node.source ?? {}} errors={errors} disabled={readonly} onChange={(source) => patch({ source })} />
                        {type.fields
                            .filter((field) => !(dynamic && field.key === 'items'))
                            .map((field) =>
                                scope ? (
                                    <BindableField
                                        key={`${node.uuid}-${field.key}`}
                                        field={structuralField(node.type, field, scope)}
                                        value={node.content?.[field.key]}
                                        scope={scope}
                                        errors={errors}
                                        disabled={readonly}
                                        bindable={!['repeat', 'when'].includes(node.type)}
                                        onChange={(value) => setContent(field.key, value)}
                                    />
                                ) : (
                                    <FieldInput
                                        key={`${node.uuid}-${field.key}`}
                                        field={field}
                                        value={node.content?.[field.key]}
                                        path={`content.${field.key}`}
                                        errors={errors}
                                        disabled={readonly}
                                        onChange={(value) => setContent(field.key, value)}
                                    />
                                ),
                            )}
                        <DisplayPanel type={type} display={node.display ?? {}} disabled={readonly} onChange={(display) => patch({ display })} />
                        {type.fields.length === 0 && !type.capabilities.display_modes.length && <p className="small text-body-secondary">This block has no content settings. Add blocks inside it, or use Layout and Style.</p>}
                    </>
                )}
                {tab === 'layout' && type && <LayoutPanel node={view} type={type} errors={errors} disabled={readonly} device={device} onChange={setLayout} />}
                {tab === 'style' && <StylePanel node={view} errors={errors} disabled={readonly} onChange={setStyle} />}
                {tab === 'advanced' && <AdvancedPanel node={node} errors={errors} disabled={readonly} onChange={patch} />}
            </div>
        </div>
    );
}

/** For repeat/when blocks in a custom type's layout, "field" is a choice of the type's fields. */
function structuralField(slug, field, scope) {
    if (field.key !== 'field' || !['repeat', 'when'].includes(slug)) return field;
    const options = Object.fromEntries(scope.options.filter((option) => slug === 'when' || option.type === 'repeater').map((option) => [option.path, option.label]));
    return { ...field, type: 'select', options };
}

/** Which global block a global-ref shows, with edit and detach. */
function GlobalRefSettings({ node, errors, disabled, onPick }) {
    const id = useId();
    const globals = useGlobals();
    const definitions = useBuilder((state) => state.definitions);
    const replaceWith = useBuilder((state) => state.replaceWith);
    const [busy, setBusy] = useState(false);
    const current = globals.find((global) => global.id === node.global_block_id);

    async function detach() {
        if (!window.confirm('Replace this global block with a local copy? Later changes to the global block will no longer appear here.')) return;
        setBusy(true);
        try {
            const { data } = await adminHttp.post(definitions.endpoints.detachGlobal.replace('__ID__', node.global_block_id));
            replaceWith(node.uuid, data.blocks);
        } finally {
            setBusy(false);
        }
    }

    return (
        <div>
            <div className="mb-3">
                <label className="form-label small fw-semibold" htmlFor={id}>
                    Global block
                </label>
                <select id={id} className={`form-select form-select-sm${errors[''] ? ' is-invalid' : ''}`} value={node.global_block_id ?? ''} disabled={disabled} onChange={(e) => onPick(Number(e.target.value) || undefined)}>
                    <option value="">— Choose —</option>
                    {globals.map((global) => (
                        <option key={global.id} value={global.id}>
                            {global.name}
                        </option>
                    ))}
                </select>
                <div className="form-text">The published version is shown. Changes to it appear on every page that uses it.</div>
            </div>
            <div className="d-flex flex-wrap gap-2">
                {current?.edit_url && (
                    <a className="btn btn-sm btn-outline-secondary" href={current.edit_url} target="_blank" rel="noopener">
                        <i className="bi bi-pencil" aria-hidden="true" /> Edit global block<span className="visually-hidden"> (opens in new tab)</span>
                    </a>
                )}
                {definitions.permissions.global_detach && node.global_block_id && !disabled && (
                    <button type="button" className="btn btn-sm btn-outline-danger" onClick={detach} disabled={busy}>
                        <i className="bi bi-scissors" aria-hidden="true" /> Detach
                    </button>
                )}
            </div>
        </div>
    );
}

/** Drop empty sections so stored JSON stays small. */
function clean(node) {
    const copy = { ...node };
    for (const key of ['source', 'display', 'layout', 'style', 'responsive', 'advanced', 'name', 'hidden', 'global_block_id']) {
        const value = copy[key];
        if (value === undefined || value === null || value === false || value === '' || (typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length === 0)) {
            delete copy[key];
        }
    }
    return copy;
}
