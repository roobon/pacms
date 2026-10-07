import Link from '@tiptap/extension-link';
import { EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';

/**
 * Small editor for page and news summaries: paragraphs, bold, italic and links only
 * (the server keeps the same set and uses the plain text for search and sharing).
 *
 * @param {{value: string, onChange: (html: string) => void, labelledBy?: string, describedBy?: string, invalid?: boolean, disabled?: boolean}} props
 */
export default function SummaryEditor({ value, onChange, labelledBy, describedBy, invalid = false, disabled = false }) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: false,
                bulletList: false,
                orderedList: false,
                listItem: false,
                listKeymap: false,
                blockquote: false,
                code: false,
                codeBlock: false,
                horizontalRule: false,
                strike: false,
                underline: false,
                link: false,
            }),
            Link.configure({ openOnClick: false, autolink: true, protocols: ['http', 'https', 'mailto', 'tel'], HTMLAttributes: { rel: 'noopener noreferrer' } }),
        ],
        content: value || '',
        editable: !disabled,
        editorProps: {
            attributes: {
                class: `form-control pa-rte__content pa-rte__content--short${invalid ? ' is-invalid' : ''}`,
                role: 'textbox',
                'aria-multiline': 'true',
                ...(labelledBy ? { 'aria-labelledby': labelledBy } : {}),
                ...(describedBy ? { 'aria-describedby': describedBy } : {}),
            },
        },
        onUpdate: ({ editor: instance }) => onChange(instance.isEmpty ? '' : instance.getHTML()),
    });

    if (!editor) return null;

    function setLink() {
        const previous = editor.getAttributes('link').href ?? '';
        const href = window.prompt('Link address (https://…, /page, mailto:…)', previous);
        if (href === null) return;
        if (href === '') editor.chain().focus().unsetLink().run();
        else editor.chain().focus().extendMarkRange('link').setLink({ href }).run();
    }

    const button = (label, icon, action, active) => (
        <button type="button" className={`btn btn-sm ${active ? 'btn-secondary' : 'btn-outline-secondary'}`} onClick={action} aria-pressed={active} disabled={disabled} title={label}>
            <i className={`bi ${icon}`} aria-hidden="true" />
            <span className="visually-hidden">{label}</span>
        </button>
    );

    return (
        <div className="pa-rte">
            <div className="pa-rte__toolbar" role="toolbar" aria-label="Summary formatting">
                {button('Bold (Ctrl+B)', 'bi-type-bold', () => editor.chain().focus().toggleBold().run(), editor.isActive('bold'))}
                {button('Italic (Ctrl+I)', 'bi-type-italic', () => editor.chain().focus().toggleItalic().run(), editor.isActive('italic'))}
                {button('Link', 'bi-link-45deg', setLink, editor.isActive('link'))}
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
