import axios from 'axios';

/**
 * HTTP client for admin islands: same-origin session auth with the page's CSRF token.
 */
export const adminHttp = axios.create({
    withCredentials: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '',
    },
    timeout: 60000,
});

/**
 * Human-readable message from the PACMS JSON error envelope.
 *
 * @param {unknown} error
 */
export function errorMessage(error) {
    const data = /** @type {any} */ (error)?.response?.data;
    const firstFieldError = data?.errors ? Object.values(data.errors).flat()[0] : null;

    return String(firstFieldError ?? data?.message ?? 'Something went wrong. Please try again.');
}
