import { type VueWrapper, mount } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { ADMIN_ESCAPE_EVENT, useAdminShortcuts } from '@shared-cross/Composables/useAdminShortcuts';

const mountedWrappers: Array<VueWrapper> = [];

function mountHost(template = '<div />') {
    const wrapper = mount({ template, setup: () => useAdminShortcuts() }, {
        attachTo: document.body,
    });
    mountedWrappers.push(wrapper);

    return wrapper;
}

function keydown(key: string, init: KeyboardEventInit = {}): KeyboardEvent {
    const event = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true, ...init });

    window.dispatchEvent(event);

    return event;
}

describe('useAdminShortcuts', () => {
    beforeEach(() => {
        document.body.innerHTML = '';
        vi.restoreAllMocks();
    });

    afterEach(() => {
        for (const wrapper of mountedWrappers) {
            wrapper.unmount();
        }
        mountedWrappers.length = 0;
        document.body.innerHTML = '';
    });

    it('submits the focused form on Ctrl+Enter', () => {
        const requestSubmit = vi
            .spyOn(HTMLFormElement.prototype, 'requestSubmit')
            .mockImplementation(() => {});

        mountHost('<form><input data-testid="shortcut-input" /></form>');
        const input = document.querySelector<HTMLInputElement>('[data-testid="shortcut-input"]')!;
        input.focus();

        keydown('Enter', { ctrlKey: true });

        expect(requestSubmit).toHaveBeenCalledOnce();
    });

    it('does not submit when the focused element is outside a form', () => {
        const requestSubmit = vi
            .spyOn(HTMLFormElement.prototype, 'requestSubmit')
            .mockImplementation(() => {});

        mountHost('<div data-testid="shortcut-host" tabindex="-1" />');
        const host = document.querySelector<HTMLElement>('[data-testid="shortcut-host"]')!;
        host.focus();

        keydown('Enter', { ctrlKey: true });

        expect(requestSubmit).not.toHaveBeenCalled();
    });

    it('dispatches the escape event when no dialog is open', () => {
        mountHost();
        const listener = vi.fn();
        window.addEventListener(ADMIN_ESCAPE_EVENT, listener);

        keydown('Escape');

        expect(listener).toHaveBeenCalledOnce();
        window.removeEventListener(ADMIN_ESCAPE_EVENT, listener);
    });

    it('skips the escape event while a dialog is open', () => {
        const dialog = document.createElement('div');
        dialog.setAttribute('role', 'dialog');
        document.body.appendChild(dialog);

        mountHost();
        const listener = vi.fn();
        window.addEventListener(ADMIN_ESCAPE_EVENT, listener);

        keydown('Escape');

        expect(listener).not.toHaveBeenCalled();
        window.removeEventListener(ADMIN_ESCAPE_EVENT, listener);
    });

    it('focuses the search input when "/" is pressed outside a form control', () => {
        mountHost('<div><input data-shortcut-search /></div>');
        const search = document.querySelector<HTMLInputElement>('[data-shortcut-search]')!;
        expect(document.activeElement).not.toBe(search);

        const event = keydown('/');

        expect(event.defaultPrevented).toBe(true);
        expect(document.activeElement).toBe(search);
    });

    it('lets "/" type normally inside a text input', () => {
        mountHost('<div><input data-shortcut-search /><input data-testid="text-input" /></div>');
        const input = document.querySelector<HTMLInputElement>('[data-testid="text-input"]')!;
        const search = document.querySelector<HTMLInputElement>('[data-shortcut-search]')!;
        input.focus();

        const event = new KeyboardEvent('keydown', { key: '/', bubbles: true, cancelable: true });
        input.dispatchEvent(event);

        expect(event.defaultPrevented).toBe(false);
        expect(document.activeElement).toBe(input);
        expect(document.activeElement).not.toBe(search);
    });

    it('removes the keydown listener when the host unmounts', () => {
        const wrapper = mountHost();
        wrapper.unmount();

        const listener = vi.fn();
        window.addEventListener(ADMIN_ESCAPE_EVENT, listener);

        keydown('Escape');

        expect(listener).not.toHaveBeenCalled();
        window.removeEventListener(ADMIN_ESCAPE_EVENT, listener);
    });
});
