/**
 * Page Builder island: mounts on #page-builder inside the page edit form.
 * Initial data comes from <script type="application/json" id="page-builder-data">.
 */
import { createRoot } from 'react-dom/client';
import Builder from '../builder/Builder.jsx';
import { useBuilder } from '../builder/store.js';
import { adminHttp } from '../http.js';

const mount = document.getElementById('page-builder');
const dataElement = document.getElementById('page-builder-data');
const input = document.getElementById('blocks-input');

if (mount && dataElement && input) {
    const data = JSON.parse(dataElement.textContent || '{}');

    adminHttp
        .get(data.endpoints.definitions)
        .then(({ data: definitions }) => {
            useBuilder.getState().init({
                definitions: { ...definitions, endpoints: data.endpoints },
                nodes: Array.isArray(data.blocks) ? data.blocks : [],
                errors: data.errors ?? {},
                readonly: data.readonly,
            });
            createRoot(mount).render(<Builder input={input} />);
        })
        .catch(() => {
            mount.innerHTML = '<div class="alert alert-danger">The block builder could not be loaded. Reload the page to try again.</div>';
        });
}
