/**
 * Summary editor island: upgrades every <textarea data-summary-editor> to a small
 * formatting editor. The textarea stays in the form (hidden) and receives the HTML, so
 * the form posts exactly as before; without JavaScript it is a plain text box.
 */
import { createRoot } from 'react-dom/client';
import SummaryEditor from '../fields/SummaryEditor.jsx';

for (const textarea of document.querySelectorAll('textarea[data-summary-editor]')) {
    const label = document.querySelector(`label[for="${textarea.id}"]`);
    if (label && !label.id) label.id = `${textarea.id}-label`;

    const mount = document.createElement('div');
    textarea.insertAdjacentElement('afterend', mount);
    textarea.hidden = true;

    // The label now names the editor; clicking it focuses the editor.
    label?.addEventListener('click', (event) => {
        event.preventDefault();
        mount.querySelector('[contenteditable]')?.focus();
    });

    createRoot(mount).render(
        <SummaryEditor
            value={textarea.value}
            labelledBy={label?.id}
            describedBy={textarea.getAttribute('aria-describedby') ?? undefined}
            invalid={textarea.classList.contains('is-invalid')}
            disabled={textarea.disabled || Boolean(textarea.closest('fieldset[disabled]'))}
            onChange={(html) => {
                textarea.value = html;
                textarea.dispatchEvent(new Event('input', { bubbles: true }));
            }}
        />,
    );
}
