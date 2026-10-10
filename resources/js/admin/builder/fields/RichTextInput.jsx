import { useEditor, useEditorState, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { useEffect, useId, useRef, useState } from 'react';
import { MediaPickerDialog } from '../../islands/media-picker/MediaPickerDialog.jsx';
import { adminHttp } from '../../http.js';
import { BOXES, COLOURS, Callout, ClassAlign, FIGURE_SIZES, Figure, Highlight, TextColour, wordCount } from './rte/extensions.js';

/** Endpoints for the image and link pickers, from the admin layout. */
function adminEndpoints() {
    try {
        return JSON.parse(document.getElementById('pacms-admin-endpoints')?.textContent || '{}');
    } catch {
        return {};
    }
}

/**
 * Rich-text editor (TipTap) for article text, Text blocks and rich-text fields. Output is
 * limited to what the server keeps (CMS-BLOCK-SCHEMA.md §8.3): theme colours and boxes are
 * classes, images come from the Media Library, no inline styles. The server cleans again.
 *
 * @param {{id: string, value: string, onChange: (html: string) => void, disabled?: boolean, describedBy?: string}} props
 */
export default function RichTextInput({ id, value, onChange, disabled = false, describedBy }) {
    const [fullscreen, setFullscreen] = useState(false);
    const [picker, setPicker] = useState(false);
    const [linkPanel, setLinkPanel] = useState(false);
    const endpoints = useRef(adminEndpoints()).current;

    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3, 4] }, codeBlock: false, link: false }),
            Link.configure({ openOnClick: false, autolink: true, protocols: ['http', 'https', 'mailto', 'tel'], HTMLAttributes: { rel: 'noopener noreferrer' } }),
            Subscript,
            Superscript,
            Table.configure({ resizable: false }),
            TableRow,
            TableHeader,
            TableCell,
            ClassAlign,
            TextColour,
            Highlight,
            Callout,
            Figure,
        ],
        content: value || '',
        editable: !disabled,
        editorProps: {
            attributes: {
                id,
                class: 'form-control pa-rte__content pa-rich-text',
                role: 'textbox',
                'aria-multiline': 'true',
                ...(describedBy ? { 'aria-describedby': describedBy } : {}),
            },
        },
        onUpdate: ({ editor: instance }) => onChange(instance.isEmpty ? '' : instance.getHTML()),
    });

    // Toolbar state follows the selection.
    const state = useEditorState({
        editor,
        selector: ({ editor: e }) =>
            e
                ? {
                      block: e.isActive('heading', { level: 2 }) ? 'h2' : e.isActive('heading', { level: 3 }) ? 'h3' : e.isActive('heading', { level: 4 }) ? 'h4' : 'p',
                      align: e.getAttributes('paragraph').align ?? e.getAttributes('heading').align ?? 'start',
                      colour: e.getAttributes('textColour').colour ?? '',
                      highlight: e.isActive('themeHighlight') ? (e.getAttributes('themeHighlight').colour ?? 'warning') : '',
                      callout: e.isActive('callout') ? e.getAttributes('callout').tone : '',
                      table: e.isActive('table'),
                      figure: e.isActive('figure') ? e.getAttributes('figure') : null,
                      words: wordCount(e.getText()),
                      canUndo: e.can().undo(),
                      canRedo: e.can().redo(),
                  }
                : null,
    });

    // Esc leaves full-screen writing.
    useEffect(() => {
        if (!fullscreen) return undefined;
        const onKey = (event) => event.key === 'Escape' && setFullscreen(false);
        document.addEventListener('keydown', onKey);
        document.body.classList.add('pa-rte-open');
        return () => {
            document.removeEventListener('keydown', onKey);
            document.body.classList.remove('pa-rte-open');
        };
    }, [fullscreen]);

    if (!editor || !state) return null;

    const run = (fn) => () => fn(editor.chain().focus()).run();
    const button = (label, icon, action, active = false, extra = {}) => (
        <button type="button" className={`btn btn-sm ${active ? 'btn-secondary' : 'btn-outline-secondary'}`} onClick={action} aria-pressed={active} disabled={disabled || extra.disabled} title={label}>
            <i className={`bi ${icon}`} aria-hidden="true" />
            <span className="visually-hidden">{label}</span>
        </button>
    );
    const select = (label, value, options, onSelect) => (
        <select className="form-select form-select-sm pa-rte__select" aria-label={label} title={label} value={value} disabled={disabled} onChange={(event) => onSelect(event.target.value)}>
            {Object.entries(options).map(([key, text]) => (
                <option key={key} value={key}>
                    {text}
                </option>
            ))}
        </select>
    );

    return (
        <div className={`pa-rte${fullscreen ? ' pa-rte--fullscreen' : ''}`}>
            <div className="pa-rte__toolbar" role="toolbar" aria-label="Text formatting">
                <div className="pa-rte__group">
                    {button('Undo', 'bi-arrow-counterclockwise', run((c) => c.undo()), false, { disabled: !state.canUndo })}
                    {button('Redo', 'bi-arrow-clockwise', run((c) => c.redo()), false, { disabled: !state.canRedo })}
                </div>
                <div className="pa-rte__group">
                    {select('Text style', state.block, { p: 'Paragraph', h2: 'Heading 2', h3: 'Heading 3', h4: 'Heading 4' }, (block) =>
                        block === 'p' ? editor.chain().focus().setParagraph().run() : editor.chain().focus().setHeading({ level: Number(block.slice(1)) }).run(),
                    )}
                </div>
                <div className="pa-rte__group">
                    {button('Bold', 'bi-type-bold', run((c) => c.toggleBold()), editor.isActive('bold'))}
                    {button('Italic', 'bi-type-italic', run((c) => c.toggleItalic()), editor.isActive('italic'))}
                    {button('Underline', 'bi-type-underline', run((c) => c.toggleUnderline()), editor.isActive('underline'))}
                    {button('Strikethrough', 'bi-type-strikethrough', run((c) => c.toggleStrike()), editor.isActive('strike'))}
                    {button('Superscript', 'bi-superscript', run((c) => c.toggleSuperscript()), editor.isActive('superscript'))}
                    {button('Subscript', 'bi-subscript', run((c) => c.toggleSubscript()), editor.isActive('subscript'))}
                    {button('Inline code', 'bi-code', run((c) => c.toggleCode()), editor.isActive('code'))}
                </div>
                <div className="pa-rte__group">
                    {select('Text colour', state.colour, { '': 'Text colour', ...COLOURS }, (colour) => editor.chain().focus().setTextColour(colour || null).run())}
                    {select('Highlight', state.highlight, { '': 'Highlight', ...COLOURS }, (colour) => editor.chain().focus().setHighlight(colour || null).run())}
                </div>
                <div className="pa-rte__group">
                    {button('Align left', 'bi-text-left', run((c) => c.setAlign('start')), state.align === 'start')}
                    {button('Centre', 'bi-text-center', run((c) => c.setAlign('center')), state.align === 'center')}
                    {button('Align right', 'bi-text-right', run((c) => c.setAlign('end')), state.align === 'end')}
                </div>
                <div className="pa-rte__group">
                    {button('Bulleted list', 'bi-list-ul', run((c) => c.toggleBulletList()), editor.isActive('bulletList'))}
                    {button('Numbered list', 'bi-list-ol', run((c) => c.toggleOrderedList()), editor.isActive('orderedList'))}
                    {button('Quote', 'bi-quote', run((c) => c.toggleBlockquote()), editor.isActive('blockquote'))}
                    {button('Horizontal line', 'bi-hr', run((c) => c.setHorizontalRule()))}
                    {select('Highlight box', state.callout, { '': state.callout ? 'Remove box' : 'Box…', ...BOXES }, (tone) => editor.chain().focus().setCallout(tone || null).run())}
                </div>
                <div className="pa-rte__group">
                    {button('Link', 'bi-link-45deg', () => setLinkPanel(!linkPanel), editor.isActive('link') || linkPanel)}
                    {endpoints.media && button('Image from the Media Library', 'bi-image', () => setPicker(true))}
                    {button('Table', 'bi-table', run((c) => c.insertTable({ rows: 3, cols: 3, withHeaderRow: true })), state.table, { disabled: state.table })}
                </div>
                <div className="pa-rte__group">
                    {button('Clear formatting', 'bi-eraser', run((c) => c.unsetAllMarks().clearNodes()))}
                    {button(fullscreen ? 'Leave full screen (Esc)' : 'Full screen', fullscreen ? 'bi-fullscreen-exit' : 'bi-arrows-fullscreen', () => setFullscreen(!fullscreen), fullscreen)}
                </div>
            </div>

            {state.table && (
                <div className="pa-rte__context" role="toolbar" aria-label="Table">
                    <span className="small fw-semibold me-1">Table</span>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={run((c) => c.addRowAfter())}>+ Row</button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={run((c) => c.addColumnAfter())}>+ Column</button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={run((c) => c.deleteRow())}>− Row</button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={run((c) => c.deleteColumn())}>− Column</button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={run((c) => c.toggleHeaderRow())}>Header row</button>
                    <button type="button" className="btn btn-sm btn-outline-secondary" onClick={run((c) => c.mergeOrSplit())}>Merge / split cells</button>
                    <button type="button" className="btn btn-sm btn-outline-danger" onClick={run((c) => c.deleteTable())}>Delete table</button>
                </div>
            )}

            {state.figure && <FigurePanel figure={state.figure} onChange={(attributes) => editor.chain().focus().updateAttributes('figure', attributes).run()} onRemove={run((c) => c.deleteSelection())} />}

            {linkPanel && <LinkPanel editor={editor} targetsUrl={endpoints.linkTargets} onClose={() => setLinkPanel(false)} />}

            <EditorContent editor={editor} />
            <div className="pa-rte__status small text-body-secondary" aria-live="polite">
                {state.words} {state.words === 1 ? 'word' : 'words'}
            </div>

            {picker && (
                <MediaPickerDialog
                    title="Insert an image"
                    kind="image"
                    endpoint={endpoints.media}
                    uploadEndpoint={endpoints.mediaUpload}
                    canUpload={Boolean(endpoints.canUpload)}
                    onClose={() => setPicker(false)}
                    onSelect={(media) => {
                        editor
                            .chain()
                            .focus()
                            .insertFigure({ src: media.large ?? media.url, alt: media.is_decorative ? '' : (media.alt ?? ''), width: media.width, height: media.height, mediaId: media.id, size: 'full', caption: '' })
                            .run();
                        setPicker(false);
                    }}
                />
            )}
        </div>
    );
}

/** Size, alt text and caption of the selected image. */
function FigurePanel({ figure, onChange, onRemove }) {
    const id = useId();
    return (
        <div className="pa-rte__context" role="group" aria-label="Image">
            <span className="small fw-semibold me-1">Image</span>
            <label className="visually-hidden" htmlFor={`${id}-size`}>
                Size and position
            </label>
            <select id={`${id}-size`} className="form-select form-select-sm pa-rte__select" value={figure.size ?? 'full'} onChange={(event) => onChange({ size: event.target.value })}>
                {Object.entries(FIGURE_SIZES).map(([key, text]) => (
                    <option key={key} value={key}>
                        {text}
                    </option>
                ))}
            </select>
            <label className="small" htmlFor={`${id}-alt`}>
                Alt text
            </label>
            <input id={`${id}-alt`} className="form-control form-control-sm pa-rte__input" value={figure.alt ?? ''} maxLength={255} placeholder="Describe the image" onChange={(event) => onChange({ alt: event.target.value })} />
            <label className="small" htmlFor={`${id}-caption`}>
                Caption
            </label>
            <input id={`${id}-caption`} className="form-control form-control-sm pa-rte__input" value={figure.caption ?? ''} maxLength={500} placeholder="Optional" onChange={(event) => onChange({ caption: event.target.value })} />
            <button type="button" className="btn btn-sm btn-outline-danger" onClick={onRemove}>
                Remove image
            </button>
        </div>
    );
}

/** Link to a page or item (search), or a web address; optionally in a new tab. */
function LinkPanel({ editor, targetsUrl, onClose }) {
    const id = useId();
    const current = editor.getAttributes('link');
    const [href, setHref] = useState(current.href ?? '');
    const [newTab, setNewTab] = useState(current.target === '_blank');
    const [results, setResults] = useState([]);
    const timer = useRef(0);

    // Typing words (not an address) searches pages and content.
    useEffect(() => {
        window.clearTimeout(timer.current);
        if (!targetsUrl || href === '' || /^(https?:|mailto:|tel:|\/|#)/i.test(href)) return undefined;
        timer.current = window.setTimeout(() => {
            adminHttp
                .get(targetsUrl, { params: { q: href } })
                .then(({ data }) => setResults(data.data.slice(0, 8)))
                .catch(() => setResults([]));
        }, 250);
        return () => window.clearTimeout(timer.current);
    }, [href, targetsUrl]);

    const apply = (address = href) => {
        const chain = editor.chain().focus().extendMarkRange('link');
        if (address === '') chain.unsetLink().run();
        else chain.setLink({ href: address, target: newTab ? '_blank' : null }).run();
        onClose();
    };

    return (
        <div className="pa-rte__context" role="group" aria-label="Link">
            <label className="small fw-semibold" htmlFor={`${id}-href`}>
                Link
            </label>
            <input
                id={`${id}-href`}
                className="form-control form-control-sm pa-rte__input pa-rte__input--wide"
                value={href}
                placeholder="https://…, /page, mailto:…, or search a page by title"
                autoFocus
                onChange={(event) => setHref(event.target.value)}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        apply();
                    }
                    if (event.key === 'Escape') onClose();
                }}
            />
            <div className="form-check form-check-inline mb-0">
                <input id={`${id}-tab`} type="checkbox" className="form-check-input" checked={newTab} onChange={(event) => setNewTab(event.target.checked)} />
                <label className="form-check-label small" htmlFor={`${id}-tab`}>
                    New tab
                </label>
            </div>
            <button type="button" className="btn btn-sm btn-primary" onClick={() => apply()}>
                Apply
            </button>
            {current.href && (
                <button type="button" className="btn btn-sm btn-outline-danger" onClick={() => apply('')}>
                    Remove link
                </button>
            )}
            {results.length > 0 && (
                <ul className="list-unstyled pa-rte__results w-100 mb-0">
                    {results.map((target) => (
                        <li key={`${target.entity}-${target.id}`}>
                            <button type="button" className="pa-add-menu__row" onClick={() => apply(target.path)}>
                                <strong>{target.title}</strong> <span className="small text-body-secondary">{target.path}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
