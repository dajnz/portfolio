<!-- ErrorPage (error-handling.md:171-177, inertia/shared.yaml#InertiaErrorProps) — Inertia error page rendered by the backend for `X-Inertia` requests with `{status, message, request_id}`. Renders within the current surface layout (admin when an admin is authenticated, public otherwise). -->
<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import PublicPageLayout from '@shared-public/PublicPageLayout.vue';
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        status: number;
        message: string;
        request_id: string;
        test_id?: string;
    }>(),
    {
        test_id: 'error-page',
    },
);

const { t } = useI18n();

// The error response replaces the whole Inertia page, so the layout cannot
// come from the usual page composition — pick the surface by auth state.
const auth = usePage<{ auth?: { user?: unknown } }>().props?.auth;
const isAdmin = computed(() => auth?.user !== null && auth?.user !== undefined);
</script>

<template>
  <AdminPageLayout
    v-if="isAdmin"
    :data-testid="`${props.test_id}-layout-admin`"
  >
    <div
      class="error-page"
      :data-testid="props.test_id"
    >
      <span
        class="error-page__code"
        :data-testid="`${props.test_id}-code`"
      >
        {{ props.status }}
      </span>
      <h1
        class="error-page__message"
        :data-testid="`${props.test_id}-message`"
      >
        {{ props.message }}
      </h1>
      <p
        class="error-page__reference"
        :data-testid="`${props.test_id}-request-id`"
      >
        {{ t('errors.reference_id', { request_id: props.request_id }) }}
      </p>
      <a
        class="error-page__home"
        href="/"
        :data-testid="`${props.test_id}-home`"
      >
        {{ t('errors.back_home') }}
      </a>
    </div>
  </AdminPageLayout>
  <PublicPageLayout
    v-else
    :data-testid="`${props.test_id}-layout-public`"
  >
    <div
      class="error-page"
      :data-testid="props.test_id"
    >
      <span
        class="error-page__code"
        :data-testid="`${props.test_id}-code`"
      >
        {{ props.status }}
      </span>
      <h1
        class="error-page__message"
        :data-testid="`${props.test_id}-message`"
      >
        {{ props.message }}
      </h1>
      <p
        class="error-page__reference"
        :data-testid="`${props.test_id}-request-id`"
      >
        {{ t('errors.reference_id', { request_id: props.request_id }) }}
      </p>
      <a
        class="error-page__home"
        href="/"
        :data-testid="`${props.test_id}-home`"
      >
        {{ t('errors.back_home') }}
      </a>
    </div>
  </PublicPageLayout>
</template>

<style scoped>
.error-page {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: var(--space-md);
    min-height: 60vh;
    text-align: center;
    padding: var(--space-xl) var(--space-lg);
}

.error-page__code {
    font-size: clamp(3rem, 10vw, 5rem);
    font-weight: var(--font-bold);
    font-family: var(--font-display);
    color: var(--color-accent);
    line-height: 1;
}

.error-page__message {
    margin: 0;
    font-size: var(--font-size-lg);
    font-weight: var(--font-medium);
    color: var(--color-text-primary);
}

.error-page__reference {
    margin: 0;
    font-size: var(--font-size-sm);
    color: var(--color-text-tertiary);
}

.error-page__home {
    margin-top: var(--space-sm);
    padding: var(--space-sm) var(--space-lg);
    background: var(--color-accent);
    color: var(--color-text-inverse);
    border-radius: var(--radius-md);
    font-weight: var(--font-semibold);
    text-decoration: none;
    transition: opacity var(--transition-fast);
}

.error-page__home:hover {
    opacity: 0.9;
}
</style>
