export interface JsonRequestPayload {
    /** JSON body — sets `Content-Type: application/json`. */
    json?: unknown;
    /** Multipart body — no `Content-Type` (the boundary is auto-set). */
    formData?: FormData;
}

export interface JsonRequestOptions {
    signal?: AbortSignal;
}

export interface JsonApi {
    get: <T>(url: string, options?: JsonRequestOptions) => Promise<T>;
    post: <T>(url: string, payload?: JsonRequestPayload) => Promise<T>;
    patch: <T>(url: string, payload?: JsonRequestPayload) => Promise<T>;
    del: <T>(url: string, payload?: JsonRequestPayload) => Promise<T>;
}

// Non-critical read retries: GET requests are retried twice on a 5xx
// with an exponential backoff — mutations and uploads are never retried
// automatically.
const GET_RETRY_DELAYS_MS = [1000, 2000];

function wait(ms: number): Promise<void> {
    return new Promise((resolve) => {
        setTimeout(resolve, ms);
    });
}

/**
 * Shared JSON API client: a fetch wrapper for the admin's JSON endpoints —
 * the XSRF-TOKEN cookie header satisfies the CSRF middleware (mirroring
 * the scaffold's axios defaults) and every request asks for JSON. Throws
 * on non-OK responses; callers keep their own error handling (toasts,
 * AbortError rethrows).
 */
export function useJsonApi(): JsonApi {
    async function request<T>(
        method: 'GET' | 'POST' | 'PATCH' | 'DELETE',
        url: string,
        payload?: JsonRequestPayload,
        options?: JsonRequestOptions,
    ): Promise<T> {
        const headers: Record<string, string> = { Accept: 'application/json' };

        if (payload?.json !== undefined) {
            headers['Content-Type'] = 'application/json';
        }

        if (method !== 'GET') {
            const xsrfCookie = document.cookie
                .split('; ')
                .find((cookie) => cookie.startsWith('XSRF-TOKEN='));

            if (xsrfCookie !== undefined) {
                headers['X-XSRF-TOKEN'] = decodeURIComponent(
                    xsrfCookie.slice('XSRF-TOKEN='.length),
                );
            }
        }

        const init: RequestInit = {
            method,
            credentials: 'same-origin',
            headers,
            body:
                payload?.formData ??
                (payload?.json !== undefined ? JSON.stringify(payload.json) : undefined),
            signal: options?.signal,
        };

        let response = await fetch(url, init);

        if (method === 'GET') {
            for (const delay of GET_RETRY_DELAYS_MS) {
                if (!(response.status >= 500)) {
                    break;
                }

                await wait(delay);
                response = await fetch(url, init);
            }
        }

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
        }

        // 204 No Content (e.g. the encryption-key delete) — there is no
        // body to parse; `response.json()` would reject on the empty
        // payload.
        if (response.status === 204 || response.status === 205) {
            return undefined as T;
        }

        return (await response.json()) as T;
    }

    return {
        get: <T>(url: string, options?: JsonRequestOptions) =>
            request<T>('GET', url, undefined, options),
        post: <T>(url: string, payload?: JsonRequestPayload) => request<T>('POST', url, payload),
        patch: <T>(url: string, payload?: JsonRequestPayload) => request<T>('PATCH', url, payload),
        del: <T>(url: string, payload?: JsonRequestPayload) => request<T>('DELETE', url, payload),
    };
}
