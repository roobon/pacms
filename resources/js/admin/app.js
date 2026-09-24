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

// Move focus to the validation summary so screen-reader users hear the errors.
document.getElementById('error-summary')?.focus();
