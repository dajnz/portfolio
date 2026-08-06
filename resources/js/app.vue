<script setup lang="ts">
import type { Page } from '@inertiajs/core';
import { onErrorCaptured, onUnmounted, ref } from 'vue';

import { useAnalytics } from './Features/Shared/CrossSurface/Composables/useAnalytics';
import en from './lang/en.json';
import ru from './lang/ru.json';

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

// useAnalytics is registered exactly once at the app level
// (analytics.md:45-48); its cleanup tears down the flush interval and
// the event listeners when the root component unmounts.
const analytics = useAnalytics();
onUnmounted(analytics.cleanup);

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
