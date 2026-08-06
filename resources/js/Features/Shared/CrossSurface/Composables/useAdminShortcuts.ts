import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Window event name dispatched by the Escape shortcut for inline
 * "cancel editing" handlers (accessibility.md:50, B19). Components with
 * inline editing (e.g. the Skills page rows) listen for it to abort an
 * in-progress edit; open modals are left alone — they close themselves.
 */
export const ADMIN_ESCAPE_EVENT = 'admin:shortcut-escape';

const FOCUSABLE_FORM = 'input, textarea, select, [contenteditable="true"]';
const SEARCH_SELECTOR = '[data-shortcut-search], input[type="search"], [role="search"] input';

function isTypingTarget(target: EventTarget | null): boolean {
    return target instanceof HTMLElement && target.matches(FOCUSABLE_FORM);
}

function hasOpenDialog(): boolean {
    return document.querySelector('[role="dialog"]') !== null;
}

function submitActiveForm(): void {
    const target = document.activeElement;

    if (!(target instanceof HTMLElement)) {
        return;
    }

    const form = target.closest('form');

    if (form !== null && typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    }
}

function focusSearchInput(): void {
    const search = document.querySelector<HTMLElement>(SEARCH_SELECTOR);

    search?.focus();
}

/**
 * Admin keyboard shortcuts (accessibility.md:48-53, B19):
 *
 * - `Ctrl+Enter` — submit the form containing the focused element.
 * - `Escape` — close the open modal (handled by the modal itself) or
 *   cancel inline editing (dispatches `ADMIN_ESCAPE_EVENT`).
 * - `/` (list pages only) — focus the page search input. Guarded so the
 *   key still types normally inside inputs/textareas.
 *
 * Registered once at the admin layout root (all admin pages except
 * Login — no shortcuts there). Inertia layouts do not remount on
 * client-side navigation, so a single listener per layout instance is
 * sufficient.
 */
export function useAdminShortcuts(): void {
    function onKeydown(event: KeyboardEvent): void {
        if (event.key === 'Enter' && (event.ctrlKey || event.metaKey)) {
            event.preventDefault();
            submitActiveForm();

            return;
        }

        if (event.key === 'Escape' && !hasOpenDialog()) {
            window.dispatchEvent(new Event(ADMIN_ESCAPE_EVENT));

            return;
        }

        if (event.key === '/' && !isTypingTarget(event.target)) {
            event.preventDefault();
            focusSearchInput();
        }
    }

    onMounted(() => {
        window.addEventListener('keydown', onKeydown);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('keydown', onKeydown);
    });
}
