/**
 * Fields of a content type made in the admin (Design → Content types, Phase 8D): the field
 * builder of custom blocks, plus where each field appears on an item's page (details box,
 * its own section, or hidden). Writes plain form inputs, so one "Save" stores everything.
 */
import { createRoot } from 'react-dom/client';
import { ContentTypeFields } from './content-type-fields/ContentTypeFields.jsx';

document.querySelectorAll('[data-content-type-fields]').forEach((element) => {
    let config;
    try {
        config = JSON.parse(element.dataset.config || '{}');
    } catch {
        return;
    }
    element.replaceChildren();
    createRoot(element).render(<ContentTypeFields {...config} />);
});
