<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useToast } from '@shared-cross/Composables/useToast';
import { computed } from 'vue';

type ToastPosition = 'top-right' | 'bottom-right' | 'top-left' | 'bottom-left';

const props = withDefaults(
    defineProps<{
        position?: ToastPosition;
        test_id?: string;
    }>(),
    {
        position: 'top-right',
        test_id: 'toast-container',
    },
);

const emit = defineEmits<{ dismiss: [id: string] }>();

const { t } = useI18n();
const { toasts, dismiss } = useToast();

const positionClass = computed(() => `toast-container--${props.position}`);

function onDismiss(id: string): void {
    dismiss(id);
    emit('dismiss', id);
}

function isAlert(variant: string): boolean {
    return variant === 'error';
}
</script>

<template>
  <div
    class="toast-container"
    :class="positionClass"
    :data-testid="props.test_id"
  >
    <TransitionGroup name="toast">
      <div
        v-for="(toast, index) in toasts"
        :key="toast.id"
        class="toast"
        :class="`toast--${toast.variant}`"
        :role="isAlert(toast.variant) ? 'alert' : 'status'"
        :aria-live="isAlert(toast.variant) ? undefined : 'polite'"
        :data-testid="`toast-${index}`"
      >
        <span
          class="toast__icon"
          aria-hidden="true"
        >
          <Icon
            v-if="toast.variant === 'success'"
            class="toast__icon-image"
            icon="heroicons:check"
          />
          <Icon
            v-else-if="toast.variant === 'error'"
            class="toast__icon-image"
            icon="heroicons:exclamation-triangle"
          />
          <Icon
            v-else-if="toast.variant === 'warning'"
            class="toast__icon-image"
            icon="heroicons:exclamation-circle"
          />
          <Icon
            v-else
            class="toast__icon-image"
            icon="heroicons:information-circle"
          />
        </span>
        <div class="toast__content">
          <p class="toast__message">
            {{ toast.message }}
          </p>
        </div>
        <button
          v-if="toast.dismissible"
          type="button"
          class="toast__dismiss"
          :aria-label="t('common.close')"
          :data-testid="`toast-${index}-dismiss`"
          @click="onDismiss(toast.id)"
        >
          <Icon
            class="toast__dismiss-icon"
            icon="heroicons:x-mark"
            aria-hidden="true"
          />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>

<style scoped>
.toast-container {
    position: fixed;
    z-index: var(--z-toast);
    display: flex;
    flex-direction: column;
    gap: var(--toast-gap);
    max-width: 400px;
    padding: var(--space-md);
}

.toast-container--top-right {
    top: 0;
    right: 0;
}

.toast-container--bottom-right {
    bottom: 0;
    right: 0;
}

.toast-container--top-left {
    top: 0;
    left: 0;
}

.toast-container--bottom-left {
    bottom: 0;
    left: 0;
}

.toast {
    display: flex;
    align-items: flex-start;
    gap: var(--space-md);
    padding: var(--toast-padding);
    border-radius: var(--toast-radius);
    border: 1px solid var(--color-border);
    background: var(--color-surface);
    box-shadow: var(--toast-shadow);
    font-size: var(--font-size-sm);
}

.toast__icon {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
}

.toast--success .toast__icon {
    background: var(--color-success);
}

.toast--error .toast__icon {
    background: var(--color-error);
}

.toast--warning .toast__icon {
    background: var(--color-warning);
}

.toast--info .toast__icon {
    background: var(--color-info);
}

.toast__icon-image {
    width: 1rem;
    height: 1rem;
}

.toast__content {
    flex: 1;
    line-height: var(--line-height-normal);
    color: var(--color-text-primary);
}

.toast__message {
    font-weight: var(--font-medium);
}

.toast__dismiss {
    flex-shrink: 0;
    width: 24px;
    height: 24px;
    border: none;
    background: transparent;
    color: var(--color-text-tertiary);
    cursor: pointer;
    border-radius: var(--radius-sm);
    display: flex;
    align-items: center;
    justify-content: center;
}

.toast__dismiss:hover {
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
}

.toast__dismiss-icon {
    width: 1em;
    height: 1em;
}

.toast-enter-active,
.toast-leave-active {
    transition: all var(--transition-normal) var(--ease-out);
}

.toast-enter-from,
.toast-leave-to {
    opacity: 0;
    transform: translateY(12px) scale(0.97);
}
</style>
