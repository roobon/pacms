/**
 * Design → Menus → a menu: the Menu Builder island (Phase 9).
 */
import { createRoot } from 'react-dom/client';
import { MenuBuilder } from './menu-builder/MenuBuilder.jsx';

document.querySelectorAll('[data-menu-builder]').forEach((element) => {
    let config;
    try {
        config = JSON.parse(element.dataset.config || '{}');
    } catch {
        return;
    }
    element.replaceChildren();
    createRoot(element).render(<MenuBuilder {...config} />);
});
