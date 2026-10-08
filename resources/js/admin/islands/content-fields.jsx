/**
 * Content module form fields (Phase 8B): repeaters (e.g. a program's objectives and
 * activities) on [data-repeater-field], and document lists (attachments) on
 * [data-documents-field]. Both keep plain form inputs, so one "Save" stores everything;
 * without JavaScript the server-rendered inputs are submitted unchanged.
 */
import { createRoot } from 'react-dom/client';
import { DocumentsField } from './content-fields/DocumentsField.jsx';
import { RepeaterField } from './content-fields/RepeaterField.jsx';

function config(element) {
    try {
        return JSON.parse(element.dataset.config || '{}');
    } catch {
        return null;
    }
}

document.querySelectorAll('[data-repeater-field]').forEach((element) => {
    const props = config(element);
    if (!props) return;
    element.replaceChildren();
    createRoot(element).render(<RepeaterField {...props} />);
});

document.querySelectorAll('[data-documents-field]').forEach((element) => {
    const props = config(element);
    if (!props) return;
    element.replaceChildren();
    createRoot(element).render(<DocumentsField {...props} />);
});
