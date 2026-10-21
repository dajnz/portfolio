import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { useJsonApi } from '@shared-cross/Composables/useJsonApi';

describe('useJsonApi', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
        vi.stubGlobal('fetch', vi.fn());
        document.cookie = 'XSRF-TOKEN=abc123%2Bdef';
    });

    function jsonResponse(payload: unknown): Response {
        return {
            ok: true,
            json: async () => payload,
        } as Response;
    }

    it('posts JSON with the XSRF cookie header and content type', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { id: 1 } }));
        vi.mocked(fetch).mockImplementation(fetchMock);

        const { post } = useJsonApi();
        await post<{ data: { id: number } }>('/admin/profile/totp-enable', {
            json: { code: '123456' },
        });

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/profile/totp-enable',
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                headers: expect.objectContaining({
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': 'abc123+def',
                }),
                body: JSON.stringify({ code: '123456' }),
            }),
        );
    });

    it('posts multipart data without a content type (the boundary is auto-set)', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { path: 'about/x.jpg' } }));
        vi.mocked(fetch).mockImplementation(fetchMock);

        const body = new FormData();
        body.append('file', 'file-content');
        body.append('entity_type', 'about');

        const { post } = useJsonApi();
        await post<{ data: { path: string } }>('/api/upload', { formData: body });

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(init.headers).not.toHaveProperty('Content-Type');
        expect(init.body).toBe(body);
    });

    it('patches without a body and returns the parsed JSON', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: { is_read: true } }));
        vi.mocked(fetch).mockImplementation(fetchMock);

        const { patch } = useJsonApi();
        const result = await patch<{ data: { is_read: boolean } }>('/admin/feedback/7/read');

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/feedback/7/read',
            expect.objectContaining({
                method: 'PATCH',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'abc123+def' }),
                body: undefined,
            }),
        );
        expect(result.data.is_read).toBe(true);
    });

    it('gets with an abort signal and no XSRF header', async () => {
        const fetchMock = vi.fn().mockResolvedValue(jsonResponse({ data: [{ id: 1 }] }));
        vi.mocked(fetch).mockImplementation(fetchMock);
        const signal = new AbortController().signal;

        const { get } = useJsonApi();
        await get<{ data: Array<{ id: number }> }>('/api/skills/search?q=vue', { signal });

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(init.signal).toBe(signal);
        expect(init.headers).toEqual({ Accept: 'application/json' });
    });

    it('deletes and resolves undefined for an empty 204 response', async () => {
        const fetchMock = vi
            .fn()
            .mockResolvedValue({
                ok: true,
                status: 204,
                json: async () => {
                    throw new SyntaxError('Unexpected end of JSON input');
                },
            } as unknown as Response);
        vi.mocked(fetch).mockImplementation(fetchMock);

        const { del } = useJsonApi();
        await expect(del('/admin/backups/encryption-keys/4')).resolves.toBeUndefined();

        expect(fetchMock).toHaveBeenCalledWith(
            '/admin/backups/encryption-keys/4',
            expect.objectContaining({
                method: 'DELETE',
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'abc123+def' }),
            }),
        );
    });

    it('throws on non-OK responses', async () => {
        vi.mocked(fetch).mockResolvedValue({ ok: false, status: 500 } as Response);

        const { post } = useJsonApi();
        await expect(post('/admin/contacts')).rejects.toThrow('Request failed with status 500');
    });

    it('retries GET requests twice on 5xx with an exponential backoff', async () => {
        vi.useFakeTimers();
        const fetchMock = vi
            .fn()
            .mockResolvedValueOnce({ ok: false, status: 500 } as Response)
            .mockResolvedValueOnce({ ok: false, status: 503 } as Response)
            .mockResolvedValueOnce(jsonResponse({ data: [] }));
        vi.mocked(fetch).mockImplementation(fetchMock);

        const { get } = useJsonApi();
        const pending = get<{ data: unknown[] }>('/admin/analytics/series');

        await vi.runAllTimersAsync();
        await expect(pending).resolves.toEqual({ data: [] });

        expect(fetchMock).toHaveBeenCalledTimes(3);
        vi.useRealTimers();
    });
});
