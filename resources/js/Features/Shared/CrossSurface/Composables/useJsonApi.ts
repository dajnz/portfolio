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

/**
 * Shared JSON API client (4.2 rework, user-approved): a fetch wrapper for
 * the admin's JSON endpoints — the XSRF-TOKEN cookie header satisfies the
 * CSRF middleware (mirroring the scaffold's axios defaults) and every
 * request asks for JSON. Throws on non-OK responses; callers keep their
 * own error handling (toasts, AbortError rethrows). Unifies the
 * duplicated fetch helpers: ProfilePage's `jsonPost`, AboutEditPage's
 * `fetchWithXsrf`, FeedbackDetailPage's `jsonFetch` and
 * `useAutoMarkRead`'s `jsonPatch`, plus the search pickers' GETs.
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

        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            headers,
            body:
                payload?.formData ??
                (payload?.json !== undefined ? JSON.stringify(payload.json) : undefined),
            signal: options?.signal,
        });

        if (!response.ok) {
            throw new Error(`Request failed with status ${response.status}`);
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
