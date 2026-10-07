/**
 * Admin enhancements for server-rendered pages. No React here — interactive
 * islands (builder, media picker …) get their own entry points in later phases.
 */
import 'bootstrap-icons/font/bootstrap-icons.css';
import { Dropdown, Offcanvas, Collapse } from 'bootstrap';

// Bootstrap's data-API needs the plugins registered on the page.
void Dropdown;
void Offcanvas;
void Collapse;

// Confirm destructive actions: <form data-confirm="Delete X?">
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
        event.preventDefault();
    }
});

// Keep a colour picker and its hex text field in sync (design tokens).
document.querySelectorAll('input[type="color"][data-sync]').forEach((picker) => {
    const text = document.querySelector(picker.dataset.sync);
    if (!text) return;
    picker.addEventListener('input', () => {
        text.value = picker.value.toUpperCase();
    });
    text.addEventListener('input', () => {
        if (/^#[0-9a-f]{6}$/i.test(text.value)) picker.value = text.value;
    });
});

// New pages: fill the URL slug from the title until the editor types their own slug.
document.querySelectorAll('[data-slug-source]').forEach((source) => {
    const target = document.querySelector(source.dataset.slugSource);
    if (!(target instanceof HTMLInputElement) || !target.hasAttribute('data-slug-auto')) return;
    let touched = target.value !== '';
    target.addEventListener('input', () => {
        touched = target.value !== '';
    });
    source.addEventListener('input', () => {
        if (!touched) target.value = slugify(source.value);
    });
});

/** Same rules as the server: lowercase ASCII letters, digits and single hyphens. */
export function slugify(value) {
    return value
        .normalize('NFKD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
        .slice(0, 191);
}

// Drag & drop onto the Media Library upload box fills its file input.
document.querySelectorAll('[data-dropzone]').forEach((zone) => {
    const input = zone.querySelector('input[type="file"]');
    ['dragenter', 'dragover'].forEach((type) =>
        zone.addEventListener(type, (event) => {
            event.preventDefault();
            zone.classList.add('is-dragover');
        }),
    );
    ['dragleave', 'drop'].forEach((type) => zone.addEventListener(type, () => zone.classList.remove('is-dragover')));
    zone.addEventListener('drop', (event) => {
        event.preventDefault();
        if (input && event.dataTransfer?.files.length) {
            input.files = event.dataTransfer.files;
        }
    });
});

// Move focus to the validation summary so screen-reader users hear the errors.
document.getElementById('error-summary')?.focus();

// Copy buttons: <button data-copy-target="#textarea">
document.querySelectorAll('[data-copy-target]').forEach((button) => {
    button.addEventListener('click', async () => {
        const target = document.querySelector(button.dataset.copyTarget);
        if (!target) return;
        const label = button.innerHTML;
        try {
            await navigator.clipboard.writeText(target.value ?? target.textContent);
            button.textContent = 'Copied';
        } catch {
            target.select?.();
            button.textContent = 'Press Ctrl+C to copy';
        }
        setTimeout(() => {
            button.innerHTML = label;
        }, 2000);
    });
});

// Bulk selection: "select all" checkbox, live count, and a confirmation before deleting.
document.querySelectorAll('[data-bulk]').forEach((form) => {
    const items = () => document.querySelectorAll('[data-bulk-item]');
    const all = form.querySelector('[data-select-all]');
    const count = form.querySelector('[data-bulk-count]');
    const refresh = () => {
        const checked = [...items()].filter((item) => item.checked).length;
        if (count) count.textContent = `${checked} selected`;
        if (all) all.checked = checked > 0 && checked === items().length;
    };

    all?.addEventListener('change', () => {
        items().forEach((item) => {
            item.checked = all.checked;
        });
        refresh();
    });
    items().forEach((item) => item.addEventListener('change', refresh));

    form.addEventListener('submit', (event) => {
        if (![...items()].some((item) => item.checked)) {
            event.preventDefault();
            window.alert('Select at least one file.');
        } else if (form.elements.action?.value === 'delete' && !window.confirm(form.dataset.confirmDelete)) {
            event.preventDefault();
        }
    });
});
