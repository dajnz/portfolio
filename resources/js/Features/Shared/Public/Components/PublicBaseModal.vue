<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { nextTick, onBeforeUnmount, ref, watch } from 'vue';

type ModalSize = 'sm' | 'md' | 'lg';

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        title?: string;
        size?: ModalSize;
        close_on_scrim?: boolean;
        close_on_escape?: boolean;
        test_id?: string;
    }>(),
    {
        title: undefined,
        size: 'md',
        close_on_scrim: true,
        close_on_escape: true,
        test_id: 'base-modal',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    closed: [];
}>();

const { t } = useI18n();

const panelEl = ref<HTMLElement | null>(null);
let triggerElement: Element | null = null;

const FOCUSABLE_SELECTOR = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])',
].join(', ');

function getFocusableElements(): HTMLElement[] {
    if (panelEl.value === null) {
        return [];
    }

    return Array.from(panelEl.value.querySelectorAll<HTMLElement>(FOCUSABLE_SELECTOR));
}

function focusFirstFocusable(): void {
    const [first] = getFocusableElements();

    if (first !== undefined) {
        first.focus();
    }
}

function onPanelKeydown(event: KeyboardEvent): void {
    if (event.key !== 'Tab') {
        return;
    }

    const focusables = getFocusableElements();

    if (focusables.length === 0) {
        event.preventDefault();
        return;
    }

    const first = focusables[0];
    const last = focusables[focusables.length - 1];
    const active = document.activeElement;

    if (event.shiftKey && (active === first || !panelEl.value?.contains(active))) {
        event.preventDefault();
        last.focus();
    } else if (!event.shiftKey && (active === last || !panelEl.value?.contains(active))) {
        event.preventDefault();
        first.focus();
    }
}

function onWindowKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && props.close_on_escape) {
        close();
    }
}

function onScrimClick(): void {
    if (props.close_on_scrim) {
        close();
    }
}

function close(): void {
    if (props.modelValue) {
        emit('update:modelValue', false);
    }
}

watch(
    () => props.modelValue,
    (open) => {
        if (open) {
            triggerElement = document.activeElement;

            if (props.close_on_escape) {
                window.addEventListener('keydown', onWindowKeydown);
            }

            nextTick(() => focusFirstFocusable());
        } else {
            window.removeEventListener('keydown', onWindowKeydown);

            if (triggerElement instanceof HTMLElement) {
                triggerElement.focus();
            }
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onWindowKeydown);
});
</script>

<template>
  <Teleport to="body">
    <Transition
      name="modal"
      @after-leave="emit('closed')"
    >
      <div
        v-if="props.modelValue"
        class="base-modal"
        :data-testid="props.test_id"
        @keydown="onPanelKeydown"
      >
        <div
          class="base-modal__backdrop"
          data-testid="base-modal-scrim"
          @click="onScrimClick"
        >
          <div
            ref="panelEl"
            class="base-modal__panel"
            :class="`base-modal__panel--${props.size}`"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="props.title ? `${props.test_id}-title` : undefined"
            data-testid="base-modal-panel"
            @click.stop
          >
            <div
              v-if="props.title || $slots.header"
              class="base-modal__header"
            >
              <slot name="header">
                <h2
                  :id="`${props.test_id}-title`"
                  class="base-modal__title"
                >
                  {{ props.title }}
                </h2>
              </slot>
              <button
                type="button"
                class="base-modal__close"
                :aria-label="t('common.close')"
                data-testid="base-modal-close"
                @click="close"
              >
                <Icon
                  class="base-modal__close-icon"
                  icon="heroicons:x-mark"
                  aria-hidden="true"
                />
              </button>
            </div>
            <div class="base-modal__body">
              <slot />
            </div>
            <div
              v-if="$slots.footer"
              class="base-modal__footer"
            >
              <slot name="footer" />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.base-modal__backdrop {
    position: fixed;
    inset: 0;
    z-index: var(--z-modal);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: var(--space-lg);
    background: var(--modal-backdrop);
    backdrop-filter: blur(4px);
}

.base-modal__panel {
    width: 100%;
    max-width: var(--modal-width);
    max-height: 85vh;
    overflow-y: auto;
    background: var(--color-surface);
    border: 1px solid var(--color-border);
    border-radius: var(--modal-radius);
    padding: var(--modal-padding);
    box-shadow: var(--shadow-xl);
}

.base-modal__panel--sm {
    max-width: 440px;
}

.base-modal__panel--lg {
    max-width: 720px;
}

.base-modal__header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-md);
    margin-bottom: var(--space-lg);
}

.base-modal__title {
    font-size: var(--font-size-xl);
    font-weight: var(--font-bold);
    color: var(--color-text-primary);
}

.base-modal__close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border: none;
    background: transparent;
    color: var(--color-text-secondary);
    cursor: pointer;
    border-radius: var(--radius-md);
    transition: background var(--transition-fast);
}

.base-modal__close:hover {
    background: var(--color-bg-secondary);
}

.base-modal__close-icon {
    width: 1.25em;
    height: 1.25em;
}

.base-modal__body {
    font-size: var(--font-size-base);
    color: var(--color-text-secondary);
    line-height: var(--line-height-normal);
}

.base-modal__footer {
    display: flex;
    justify-content: flex-end;
    gap: var(--space-md);
    margin-top: var(--space-xl);
    padding-top: var(--space-lg);
    border-top: 1px solid var(--color-border);
}

.modal-enter-active,
.modal-leave-active {
    transition: opacity var(--transition-normal) var(--ease-out);
}

.modal-enter-from,
.modal-leave-to {
    opacity: 0;
}
</style>
