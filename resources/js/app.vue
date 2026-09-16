<script setup lang="ts">
import type { Page } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { useAnalytics } from '@shared-cross/Composables/useAnalytics';
import { useFocusManagement } from '@shared-cross/Composables/useFocusManagement';
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

// Error boundary per error-handling.md:212 — a render error inside an
// Inertia page is caught here and replaced with a minimal error state
// instead of an empty (crashed) viewport. Returning false stops the
// error from bubbling further.
onErrorCaptured(() => {
    hasError.value = true;

    return false;
});

// Global state hydration: the stores are re-hydrated from the shared
// props of every Inertia page (initial mount + each successful visit) —
// architecture.md:187-193. The initial page is passed via the prop;
// subsequent visits surface through the `success` event's page payload.
const themeStore = useThemeStore();
const localeStore = useLocaleStore();
const authStore = useAuthStore();
const toastStore = useToastStore();

function hydrateFromPage(page: Page, theme: unknown = page.props.theme): void {
    themeStore.hydrate(theme);
    localeStore.hydrate(page.props.locale);
    authStore.hydrate(page.props.auth);
}

// The effective theme at the initial mount. On a first visit the server
// has no `theme` cookie yet, so the shared prop cannot reflect the system
// preference — the blocking boot script in app.blade.php resolves it and
// sets the `dark` class before hydration (i18n-and-theming.md:179). The
// document state is therefore authoritative on the client; the server
// (no `document`) falls back to the prop. Inertia visits keep hydrating
// from the prop.
function resolveInitialTheme(page: Page): unknown {
    if (typeof document === 'undefined') {
        return page.props.theme;
    }

    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

// Session `notification` flashes (error-handling.md:103-115) are picked
// up once per page load; the toast queue is cleared on navigation start
// (architecture.md:193).
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

// useAnalytics is registered exactly once at the app level
// (analytics.md:45-48); its cleanup tears down the flush interval and
// the event listeners when the root component unmounts.
const analytics = useAnalytics();

// useFocusManagement (accessibility.md:60-72) moves focus to the new
// page's `<h1>` after every visit — layouts don't remount, so the
// listener must live at the root.
const focusManagement = useFocusManagement();

if (typeof window !== 'undefined') {
    showNotification(props.page);

    router.on('start', () => toastStore.dismissAll());
    router.on('success', (event) => {
        hydrateFromPage(event.detail.page);
        showNotification(event.detail.page);
    });
}

onUnmounted(() => {
    analytics.cleanup();
    focusManagement.cleanup();
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
</template>

<style scoped>
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
