<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { onMounted, ref } from 'vue';

import PublicBaseButton from './PublicBaseButton.vue';

const props = withDefaults(
    defineProps<{
        message?: string;
        accept_label?: string;
        test_id?: string;
    }>(),
    {
        message: undefined,
        accept_label: undefined,
        test_id: 'cookies-notice',
    },
);

const emit = defineEmits<{ accepted: [] }>();

const { t } = useI18n();

const STORAGE_KEY = 'cookies_accepted';

const isVisible = ref(false);

function onAccept(): void {
    localStorage.setItem(STORAGE_KEY, '1');
    isVisible.value = false;
    emit('accepted');
}

onMounted(() => {
    if (localStorage.getItem(STORAGE_KEY) !== '1') {
        isVisible.value = true;
    }
});
</script>

<template>
  <Transition name="cookies-notice">
    <div
      v-if="isVisible"
      class="cookies-notice"
      :data-testid="props.test_id"
    >
      <p class="cookies-notice__text">
        {{ props.message ?? t('landing.cookies_notice') }}
      </p>
      <PublicBaseButton
        variant="primary"
        :test_id="`${props.test_id}-accept`"
        @click="onAccept"
      >
        {{ props.accept_label ?? t('landing.cookies_accept') }}
      </PublicBaseButton>
    </div>
  </Transition>
</template>

<style scoped>
.cookies-notice {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: var(--z-sticky);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-lg);
    padding: var(--space-md) var(--space-xl);
    background: var(--color-surface);
    border-top: 1px solid var(--color-border);
    font-size: var(--font-size-sm);
    color: var(--color-text-secondary);
    box-shadow: var(--shadow-lg);
}

.cookies-notice__text {
    max-width: 600px;
    line-height: var(--line-height-normal);
    margin: 0;
}

.cookies-notice-enter-active,
.cookies-notice-leave-active {
    transition: all var(--transition-normal) var(--ease-out);
}

.cookies-notice-enter-from,
.cookies-notice-leave-to {
    opacity: 0;
    transform: translateY(100%);
}
</style>
