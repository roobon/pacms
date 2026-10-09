/**
 * Media Picker island (CMS-ARCHITECTURE.md §14): mounts on every [data-media-picker]
 * element rendered by <x-admin.media-picker>. It keeps the hidden form input in sync
 * and offers a searchable image (document or video) grid plus upload in an accessible <dialog>.
 */
import { createRoot } from 'react-dom/client';
import { MediaPickerField } from './media-picker/MediaPickerField.jsx';

document.querySelectorAll('[data-media-picker]').forEach((element) => {
    const input = element.querySelector('input[type="hidden"]');
    const config = {
        name: element.dataset.name,
        label: element.dataset.label,
        endpoint: element.dataset.endpoint,
        uploadEndpoint: element.dataset.uploadEndpoint,
        canUpload: element.dataset.canUpload === '1',
        disabled: element.dataset.disabled === '1',
        kind: ['document', 'video'].includes(element.dataset.kind) ? element.dataset.kind : 'image',
        // "any": private files may be chosen too (they are uploaded as private).
        visibility: element.dataset.visibility === 'any' ? 'any' : undefined,
        initial: input?.value
            ? { id: Number(input.value), thumbnail: element.dataset.preview || null, alt: element.dataset.alt || '', name: element.dataset.filename || '' }
            : null,
    };

    // The island renders its own hidden input; replace the server-rendered fallback.
    element.replaceChildren();
    createRoot(element).render(<MediaPickerField {...config} />);
});
