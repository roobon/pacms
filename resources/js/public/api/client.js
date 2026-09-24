import axios from 'axios';

/**
 * Same-origin HTTP clients. Sanctum cookie authentication: the XSRF-TOKEN cookie
 * is sent back as the X-XSRF-TOKEN header on state-changing requests.
 */
const common = {
    withCredentials: true,
    withXSRFToken: true,
    headers: {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
    },
    timeout: 15000,
};

/** Public API v1 (read endpoints + registered-user endpoints). */
export const api = axios.create({ ...common, baseURL: '/api/v1' });

/** Fortify authentication endpoints (/auth/login, /auth/register …). */
export const authHttp = axios.create({ ...common, baseURL: '/auth' });

/** Must be called before the first state-changing request of a session. */
export function ensureCsrfCookie() {
    return axios.get('/sanctum/csrf-cookie', common);
}

/**
 * Extracts field errors from the PACMS JSON error envelope.
 *
 * @param {unknown} error
 * @returns {{message: string, fields: Record<string, string>}}
 */
export function toFormErrors(error) {
    const data = /** @type {any} */ (error)?.response?.data;
    const fields = {};

    for (const [field, messages] of Object.entries(data?.errors ?? {})) {
        fields[field] = Array.isArray(messages) ? messages[0] : String(messages);
    }

    return {
        message: data?.message ?? 'Something went wrong. Please try again.',
        fields,
    };
}
