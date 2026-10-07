/**
 * Text editor island for server-rendered forms:
 * - <textarea data-summary-editor> → small editor (bold, italic, links) for summaries
 * - <textarea data-rich-editor>    → full editor (headings, lists, quotes, links) for
 *   article text, the same editor as the builder's Text block
 *
 * The textarea stays in the form (hidden) and receives the HTML, so the form posts exactly
 * as before; without JavaScript it is a plain text box. The server cleans the HTML again.
 */
import { createRoot } from 'react-dom/client';
import RichTextInput from '../builder/fields/RichTextInput.jsx';
import SummaryEditor from '../fields/SummaryEditor.jsx';

for (const textarea of document.querySelectorAll('textarea[data-summary-editor], textarea[data-rich-editor]')) {
    const label = document.querySelector(`label[for="${textarea.id}"]`);
    if (label && !label.id) label.id = `${textarea.id}-label`;

    const mount = document.createElement('div');
    textarea.insertAdjacentElement('afterend', mount);
    textarea.hidden = true;

    const disabled = textarea.disabled || Boolean(textarea.closest('fieldset[disabled]'));
    const describedBy = textarea.getAttribute('aria-describedby') ?? undefined;
    const onChange = (html) => {
        textarea.value = html;
        textarea.dispatchEvent(new Event('input', { bubbles: true }));
    };

    if (textarea.hasAttribute('data-rich-editor')) {
        // The editor element takes over the textarea's id, so the label still points at it.
        const id = textarea.id;
        textarea.id = `${id}-source`;
        createRoot(mount).render(<RichTextInput id={id} value={textarea.value} onChange={onChange} disabled={disabled} describedBy={describedBy} />);
        continue;
    }

    // The label now names the editor; clicking it focuses the editor.
    label?.addEventListener('click', (event) => {
        event.preventDefault();
        mount.querySelector('[contenteditable]')?.focus();
    });

    createRoot(mount).render(
        <SummaryEditor value={textarea.value} labelledBy={label?.id} describedBy={describedBy} invalid={textarea.classList.contains('is-invalid')} disabled={disabled} onChange={onChange} />,
    );
}
