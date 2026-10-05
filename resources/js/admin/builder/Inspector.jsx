import { useState } from 'react';
import FieldInput from './fields/FieldInput.jsx';
import AdvancedPanel from './panels/AdvancedPanel.jsx';
import DisplayPanel from './panels/DisplayPanel.jsx';
import LayoutPanel from './panels/LayoutPanel.jsx';
import SourcePanel from './panels/SourcePanel.jsx';
import StylePanel from './panels/StylePanel.jsx';
import { errorsFor, useBuilder } from './store.js';
import { createNode, findNode } from './tree.js';

const TABS = ['content', 'layout', 'style', 'advanced'];

/**
 * Settings of the selected block in four tabs: Content · Layout · Style · Advanced
 * (CMS-ARCHITECTURE.md §8.1).
 */
export default function Inspector() {
    const selected = useBuilder((state) => state.selected);
    const nodes = useBuilder((state) => state.nodes);
    const types = useBuilder((state) => state.types);
    const allErrors = useBuilder((state) => state.errors);
    const readonly = useBuilder((state) => state.readonly);
    const update = useBuilder((state) => state.update);
    const [tab, setTab] = useState('content');

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
    const patch = (changes) => update(node.uuid, (current) => clean({ ...current, ...changes }));
    const dynamic = node.source?.mode === 'dynamic';

    function setContent(key, value) {
        update(node.uuid, (current) => {
            const content = { ...(current.content ?? {}) };
            if (value === undefined || value === null || value === '') delete content[key];
            else content[key] = value;
            return { ...current, content };
        });
    }

    function setLayout(layout) {
        update(node.uuid, (current) => {
            const next = clean({ ...current, layout });
            // Columns: keep the number of column blocks in step with the chosen layout.
            const spans = layout?.columns?.desktop;
            if (current.type === 'columns' && Array.isArray(spans) && spans.length > (current.children?.length ?? 0)) {
                const extra = Array.from({ length: spans.length - (current.children?.length ?? 0) }, () => createNode('column', types));
                next.children = [...(current.children ?? []), ...extra];
            }
            return next;
        });
    }

    return (
        <div className="pa-inspector">
            <div className="pa-inspector__header">
                <i className={`bi ${type?.icon ?? 'bi-square'}`} aria-hidden="true" />
                <div>
                    <strong>{node.name || type?.label || node.type}</strong>
                    {type?.description && <p className="small text-body-secondary mb-0">{type.description}</p>}
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
                {tab === 'content' && type && (
                    <>
                        <SourcePanel type={type} source={node.source ?? {}} errors={errors} disabled={readonly} onChange={(source) => patch({ source })} />
                        {type.fields
                            .filter((field) => !(dynamic && field.key === 'items'))
                            .map((field) => (
                                <FieldInput
                                    key={`${node.uuid}-${field.key}`}
                                    field={field}
                                    value={node.content?.[field.key]}
                                    path={`content.${field.key}`}
                                    errors={errors}
                                    disabled={readonly}
                                    onChange={(value) => setContent(field.key, value)}
                                />
                            ))}
                        <DisplayPanel type={type} display={node.display ?? {}} disabled={readonly} onChange={(display) => patch({ display })} />
                        {type.fields.length === 0 && !type.capabilities.display_modes.length && <p className="small text-body-secondary">This block has no content settings. Add blocks inside it, or use Layout and Style.</p>}
                    </>
                )}
                {tab === 'layout' && type && <LayoutPanel node={node} type={type} errors={errors} disabled={readonly} onChange={setLayout} />}
                {tab === 'style' && <StylePanel node={node} errors={errors} disabled={readonly} onChange={(style) => patch({ style })} />}
                {tab === 'advanced' && <AdvancedPanel node={node} errors={errors} disabled={readonly} onChange={patch} />}
            </div>
        </div>
    );
}

/** Drop empty sections so stored JSON stays small. */
function clean(node) {
    const copy = { ...node };
    for (const key of ['source', 'display', 'layout', 'style', 'responsive', 'advanced', 'name', 'hidden']) {
        const value = copy[key];
        if (value === undefined || value === null || value === false || value === '' || (typeof value === 'object' && !Array.isArray(value) && Object.keys(value).length === 0)) {
            delete copy[key];
        }
    }
    return copy;
}
