<script setup lang="ts">
import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { useAnalytics } from '@shared-cross/Composables/useAnalytics';
import { useRouteAnnouncement } from '@shared-cross/Composables/useRouteAnnouncement';
import { useAuthStore } from '@shared-cross/Stores/authStore';
import { useLocaleStore } from '@shared-cross/Stores/localeStore';
import { useThemeStore } from '@shared-cross/Stores/themeStore';
import { type ToastVariant, useToastStore } from '@shared-cross/Stores/toastStore';
import { onErrorCaptured, onUnmounted, ref } from 'vue';
import en from '@/lang/en.json';
import ru from '@/lang/ru.json';

const catalogs = { en, ru };

// The initial page is passed as a prop from `app.ts`/`ssr.ts` — `usePage()`
// cannot be used here because it reads a singleton populated by the inner
// Inertia `App` component's setup, which runs after the root's setup.
const props = defineProps<{ page: Page }>();
const locale = props.page.props.locale;
const messages = catalogs[locale === 'ru' ? 'ru' : 'en'];

const hasError = ref(false);

// Error boundary — a render error inside an Inertia page is caught here
// and replaced with a minimal error state instead of an empty (crashed)
// viewport. Returning false stops the error from bubbling further.
onErrorCaptured(() => {
    hasError.value = true;

    return false;
});

// Global state hydration: the stores are re-hydrated from the shared
// props of every Inertia page (initial mount + each successful visit).
// The initial page is passed via the prop; subsequent visits surface
// through the `success` event's page payload.
const themeStore = useThemeStore();
const localeStore = useLocaleStore();
const authStore = useAuthStore();
const toastStore = useToastStore();

function hydrateFromPage(page: Page, theme: unknown = page.props.theme): void {
    themeStore.hydrate(theme);
    localeStore.hydrate(page.props.locale);
    authStore.hydrate(page.props.auth);

    // `<html lang>` is server-rendered once by the Blade layout and is
    // not part of the Inertia body swap, so it must be synced here on
    // hydration and every visit.
    if (typeof document !== 'undefined') {
        document.documentElement.lang = localeStore.locale;
    }
}

// The effective theme at the initial mount. On a first visit the server
// has no `theme` cookie yet, so the shared prop cannot reflect the system
// preference — the blocking boot script in app.blade.php resolves it and
// sets the `dark` class before hydration. The document state is therefore
// authoritative on the client; the server (no `document`) falls back to
// the prop. Inertia visits keep hydrating from the prop.
function resolveInitialTheme(page: Page): unknown {
    if (typeof document === 'undefined') {
        return page.props.theme;
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

// Session `notification` flashes are picked up once per page load; the
// toast queue is cleared on navigation start.
interface NotificationProps {
    type?: ToastVariant;
    message?: string;
}

function showNotification(page: Page): void {
    const notification = page.props.notification as NotificationProps | null | undefined;
    if (notification?.message) {
        toastStore.show(notification.message, {
            variant: notification.type,
        });
    }
}

hydrateFromPage(props.page, resolveInitialTheme(props.page));

// useAnalytics is registered exactly once at the app level; its cleanup
// tears down the flush interval and the event listeners when the root
// component unmounts.
const analytics = useAnalytics();

// useRouteAnnouncement announces the new page's `<h1>` through the live
// region in the template; focus stays at the document top so the skip
// link remains the first tab stop. Layouts don't remount, so the
// listener must live at the root.
const routeAnnouncement = useRouteAnnouncement();

if (typeof window !== 'undefined') {
    showNotification(props.page);

    router.on('start', (event) => {
        // Prefetches fire `start` too; only a real navigation should
        // dismiss the toast queue.
        if (event.detail.visit.prefetch) {
            return;
        }

        toastStore.dismissAll();
    });
    router.on('success', (event) => {
        hydrateFromPage(event.detail.page);
        showNotification(event.detail.page);
    });
}

onUnmounted(() => {
    analytics.cleanup();
    routeAnnouncement.cleanup();
});

function reload(): void {
    window.location.reload();
}
</script>

<template>
  <slot v-if="!hasError" />
  <main
    v-else
    class="app-error"
    role="alert"
  >
    <h1 class="app-error__title">
      {{ messages.errors.server_error }}
    </h1>
    <button
      class="app-error__reload"
      type="button"
      @click="reload"
    >
      {{ messages.common.retry }}
    </button>
  </main>
  <p
    class="app-announcement"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    data-testid="route-announcement"
  >
    {{ routeAnnouncement.announcement }}
  </p>
</template>

<style scoped>
.app-announcement {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip-path: inset(50%);
    white-space: nowrap;
    border: 0;
}

.app-error {
    align-items: center;
    display: flex;
    flex-direction: column;
    gap: 1rem;
    justify-content: center;
    min-height: 100vh;
    padding: 2rem;
}

.app-error__title {
    font-size: 1.25rem;
    margin: 0;
}

.app-error__reload {
    background: #2563eb;
    border: 0;
    border-radius: 0.375rem;
    color: #fff;
    cursor: pointer;
    font: inherit;
    padding: 0.5rem 1rem;
}

.app-error__reload:hover {
    background: #1d4ed8;
}
</style>
