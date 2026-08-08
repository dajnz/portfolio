<!-- AdminBaseToast (admin-section-components.md:85-91) — same contract as PublicBaseToastContainer, Tailwind-styled. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useToast } from '@shared-cross/Composables/useToast';
import type { ToastVariant } from '@shared-cross/Stores/toastStore';
import { computed } from 'vue';

type ToastPosition = 'top-right' | 'bottom-right' | 'top-left' | 'bottom-left';

const props = withDefaults(
    defineProps<{
        position?: ToastPosition;
        test_id?: string;
    }>(),
    {
        // Bottom-right (5.2 rework, user request): the top-right position
        // overlapped the admin header's language/theme controls.
        position: 'bottom-right',
        test_id: 'toast-container',
    },
);

const emit = defineEmits<{ dismiss: [id: string] }>();

const { t } = useI18n();
const { toasts, dismiss } = useToast();

const POSITION_CLASSES: Record<ToastPosition, string> = {
    'top-right': 'top-4 right-4',
    'bottom-right': 'bottom-4 right-4',
    'top-left': 'top-4 left-4',
    'bottom-left': 'bottom-4 left-4',
};

const VARIANT_CLASSES: Record<ToastVariant, string> = {
    success: 'bg-success-500',
    error: 'bg-danger-500',
    warning: 'bg-warning-500',
    info: 'bg-primary-600',
};

const positionClass = computed(() => POSITION_CLASSES[props.position]);

function onDismiss(id: string): void {
    dismiss(id);
    emit('dismiss', id);
}

function isAlert(variant: ToastVariant): boolean {
    return variant === 'error';
}
</script>

<template>
  <div
    class="fixed z-toast flex flex-col gap-3 max-w-sm"
    :class="positionClass"
    :data-testid="props.test_id"
  >
    <TransitionGroup
      enter-active-class="transition-all duration-300 ease-out"
      leave-active-class="transition-all duration-300 ease-out"
      enter-from-class="opacity-0 translate-y-3 scale-95"
      leave-to-class="opacity-0 translate-y-3 scale-95"
    >
      <div
        v-for="(toast, index) in toasts"
        :key="toast.id"
        class="flex items-start gap-4 px-4 py-3 bg-white dark:bg-surface-dark-secondary border border-slate-200 dark:border-slate-700 rounded-xl shadow-dropdown"
        :role="isAlert(toast.variant) ? 'alert' : 'status'"
        :aria-live="isAlert(toast.variant) ? undefined : 'polite'"
        :data-testid="`toast-${index}`"
      >
        <span
          class="flex-shrink-0 w-6 h-6 rounded-full flex items-center justify-center text-white"
          :class="VARIANT_CLASSES[toast.variant]"
          aria-hidden="true"
        >
          <Icon
            v-if="toast.variant === 'success'"
            class="w-4 h-4"
            icon="heroicons:check"
          />
          <Icon
            v-else-if="toast.variant === 'error'"
            class="w-4 h-4"
            icon="heroicons:exclamation-triangle"
          />
          <Icon
            v-else-if="toast.variant === 'warning'"
            class="w-4 h-4"
            icon="heroicons:exclamation-circle"
          />
          <Icon
            v-else
            class="w-4 h-4"
            icon="heroicons:information-circle"
          />
        </span>
        <div class="flex-1 self-center text-sm font-medium text-slate-900 dark:text-slate-100">
          {{ toast.message }}
        </div>
        <button
          v-if="toast.dismissible"
          type="button"
          class="flex-shrink-0 w-6 h-6 text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 rounded transition-colors"
          :aria-label="t('common.close')"
          :data-testid="`toast-${index}-dismiss`"
          @click="onDismiss(toast.id)"
        >
          <Icon
            class="w-4 h-4"
            icon="heroicons:x-mark"
            aria-hidden="true"
          />
        </button>
      </div>
    </TransitionGroup>
  </div>
</template>
