<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { Comment, nextTick, onBeforeUnmount, ref, useSlots, watch } from 'vue';

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
const $slots = useSlots();

// The default slot may EXIST yet render nothing (a consumer's
// `v-if`-guarded body — the ConfirmationDialog's message block). The
// body wrapper must not render for an empty slot (a title-only delete
// dialog must not show a stray empty box).
function hasDefaultSlotContent(): boolean {
    return ($slots.default?.() ?? []).some((vnode) => vnode.type !== Comment);
}

const SIZE_CLASSES: Record<ModalSize, string> = {
    sm: 'max-w-sm',
    md: 'max-w-md',
    lg: 'max-w-lg',
};

const panelEl = ref<HTMLElement | null>(null);
const panelSettled = ref(false);
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

function onBeforeEnter(): void {
    panelSettled.value = false;
}

function onAfterEnter(): void {
    panelSettled.value = true;
}

function onBeforeLeave(): void {
    panelSettled.value = false;
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
      enter-active-class="transition-opacity duration-200"
      leave-active-class="transition-opacity duration-150"
      enter-from-class="opacity-0"
      leave-to-class="opacity-0"
      @before-enter="onBeforeEnter"
      @after-enter="onAfterEnter"
      @before-leave="onBeforeLeave"
      @after-leave="emit('closed')"
    >
      <div
        v-if="props.modelValue"
        :data-testid="props.test_id"
        @keydown="onPanelKeydown"
      >
        <div
          class="fixed inset-0 z-modal bg-black/50 flex items-center justify-center p-4 sm:p-6"
          :data-testid="`${props.test_id}-scrim`"
          @click="onScrimClick"
        >
          <div
            ref="panelEl"
            class="w-full bg-white dark:bg-surface-dark-secondary rounded-xl shadow-modal border border-slate-200 dark:border-slate-700 overflow-hidden transition-all duration-200"
            :class="[SIZE_CLASSES[props.size], { 'scale-95': !panelSettled }]"
            role="dialog"
            aria-modal="true"
            :aria-labelledby="props.title ? `${props.test_id}-title` : undefined"
            :data-testid="`${props.test_id}-panel`"
            @click.stop
          >
            <div
              v-if="props.title || $slots.header"
              class="flex items-center justify-between px-6 py-4 border-b border-slate-100 dark:border-slate-700"
            >
              <slot name="header">
                <h2
                  :id="`${props.test_id}-title`"
                  class="text-lg font-bold text-slate-900 dark:text-white"
                >
                  {{ props.title }}
                </h2>
              </slot>
              <button
                type="button"
                class="w-8 h-8 inline-flex items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg transition-colors"
                :aria-label="t('common.close')"
                :data-testid="`${props.test_id}-close`"
                @click="close"
              >
                <Icon
                  class="w-5 h-5"
                  icon="heroicons:x-mark"
                  aria-hidden="true"
                />
              </button>
            </div>
            <!-- The body block is omitted when the default slot is empty
                 (a title-only delete dialog must not render an empty
                 box). `hasDefaultSlotContent` inspects the rendered slot
                 vnodes: a slot whose content is `v-if`-guarded away still
                 exists, but renders nothing. -->
            <div
              v-if="hasDefaultSlotContent()"
              class="px-6 py-4 text-sm text-slate-600 dark:text-slate-400"
            >
              <slot />
            </div>
            <div
              v-if="$slots.footer"
              class="flex justify-end gap-3 px-6 py-4 border-t border-slate-100 dark:border-slate-700 bg-slate-100 dark:bg-slate-900/30"
            >
              <slot name="footer" />
            </div>
          </div>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>
