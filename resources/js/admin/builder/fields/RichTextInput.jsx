import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';

/**
 * Rich-text editor (TipTap). Output is limited to the formats the server keeps
 * (CMS-BLOCK-SCHEMA.md §8.3); the server sanitises again on save.
 *
 * @param {{id: string, value: string, onChange: (html: string) => void, disabled?: boolean, describedBy?: string}} props
 */
export default function RichTextInput({ id, value, onChange, disabled = false, describedBy }) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({ heading: { levels: [2, 3, 4] }, codeBlock: false, link: false }),
            Link.configure({ openOnClick: false, autolink: true, protocols: ['http', 'https', 'mailto', 'tel'], HTMLAttributes: { rel: 'noopener noreferrer' } }),
        ],
        content: value || '',
        editable: !disabled,
        editorProps: {
            attributes: {
                id,
                class: 'form-control pa-rte__content',
                role: 'textbox',
                'aria-multiline': 'true',
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
            <div className="pa-rte__toolbar" role="toolbar" aria-label="Text formatting">
                {button('Bold', 'bi-type-bold', () => editor.chain().focus().toggleBold().run(), editor.isActive('bold'))}
                {button('Italic', 'bi-type-italic', () => editor.chain().focus().toggleItalic().run(), editor.isActive('italic'))}
                {button('Heading 2', 'bi-type-h2', () => editor.chain().focus().toggleHeading({ level: 2 }).run(), editor.isActive('heading', { level: 2 }))}
                {button('Heading 3', 'bi-type-h3', () => editor.chain().focus().toggleHeading({ level: 3 }).run(), editor.isActive('heading', { level: 3 }))}
                {button('Bulleted list', 'bi-list-ul', () => editor.chain().focus().toggleBulletList().run(), editor.isActive('bulletList'))}
                {button('Numbered list', 'bi-list-ol', () => editor.chain().focus().toggleOrderedList().run(), editor.isActive('orderedList'))}
                {button('Quote', 'bi-quote', () => editor.chain().focus().toggleBlockquote().run(), editor.isActive('blockquote'))}
                {button('Link', 'bi-link-45deg', setLink, editor.isActive('link'))}
            </div>
            <EditorContent editor={editor} />
        </div>
    );
}
