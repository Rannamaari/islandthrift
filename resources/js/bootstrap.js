import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.querySelector('meta[name="csrf-token"]');

if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
}

let csrfRefreshRequest = null;

function updateCsrfToken(token) {
    if (! token) {
        throw new Error('The server did not return a valid security token.');
    }

    csrfToken?.setAttribute('content', token);
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = token;

    return token;
}

async function refreshCsrfToken() {
    if (! csrfRefreshRequest) {
        csrfRefreshRequest = window.axios
            .get('/pos/api/csrf-token', { headers: { 'Cache-Control': 'no-store' } })
            .then((response) => updateCsrfToken(response.data.token))
            .finally(() => {
                csrfRefreshRequest = null;
            });
    }

    return csrfRefreshRequest;
}

window.axios.interceptors.response.use(
    (response) => response,
    async (error) => {
        const request = error.config;

        if (
            error.response?.status !== 419
            || ! request
            || request._csrfRetried
            || ! request.url?.startsWith('/pos/')
        ) {
            return Promise.reject(error);
        }

        request._csrfRetried = true;

        try {
            const token = await refreshCsrfToken();
            request.headers = request.headers ?? {};
            request.headers['X-CSRF-TOKEN'] = token;

            return window.axios(request);
        } catch {
            return Promise.reject(new Error(
                'Your login session has expired. Refresh this page and sign in again. Your open shift is saved and can be closed after signing in.',
            ));
        }
    },
);
