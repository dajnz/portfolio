<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';

type ButtonTag = 'button' | 'a';
type ButtonType = 'button' | 'submit' | 'reset';
type ButtonVariant =
    | 'primary'
    | 'secondary'
    | 'ghost'
    | 'icon-only'
    | 'outline'
    | 'danger'
    | 'icon-outline'
    | 'icon-outline-danger';
type ButtonSize = 'sm' | 'md' | 'lg';
type IconPosition = 'left' | 'right';

const props = withDefaults(
    defineProps<{
        tag?: ButtonTag;
        href?: string;
        type?: ButtonType;
        variant?: ButtonVariant;
        icon?: string;
        icon_position?: IconPosition;
        size?: ButtonSize;
        disabled?: boolean;
        loading?: boolean;
        target?: string;
        test_id?: string;
    }>(),
    {
        tag: 'button',
        href: undefined,
        type: 'button',
        variant: 'primary',
        icon: undefined,
        icon_position: 'left',
        size: 'md',
        disabled: false,
        loading: false,
        target: undefined,
        test_id: 'base-button',
    },
);

const emit = defineEmits<{ click: [event: MouseEvent] }>();

// Icon-only sizing also applies to the outline-icon variants
// (border + icon, no text).
const isIconOnly = computed(() =>
    ['icon-only', 'icon-outline', 'icon-outline-danger'].includes(props.variant),
);
const isDisabled = computed(() => props.disabled || props.loading);
const isLink = computed(() => props.tag === 'a');

const variantClass = computed(() => {
    const map: Record<ButtonVariant, string> = {
        primary: 'bg-primary-600 hover:bg-primary-700 active:bg-primary-600 text-white',
        secondary:
            'bg-white dark:bg-surface-dark-secondary border border-slate-200 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-200',
        danger: 'bg-danger-600 hover:bg-danger-700 text-white',
        ghost: 'text-slate-500 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-slate-800',
        outline: 'border border-primary-600 text-primary-600 hover:bg-primary-600 hover:text-white',
        'icon-only':
            'w-10 h-10 items-center justify-center text-slate-400 hover:text-slate-600 dark:hover:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-800',
        'icon-outline':
            'w-10 h-10 items-center justify-center border border-slate-200 dark:border-slate-700 text-primary-600 hover:bg-primary-50 dark:hover:bg-primary-950 hover:border-primary-300 dark:hover:border-primary-700',
        'icon-outline-danger':
            'w-10 h-10 items-center justify-center border border-slate-200 dark:border-slate-700 text-danger-600 hover:bg-danger-50 dark:hover:bg-danger-950 hover:border-danger-100 dark:hover:border-danger-900',
    };

    return map[props.variant];
});

const sizeClass = computed(() => {
    if (isIconOnly.value) {
        return '';
    }

    const map: Record<ButtonSize, string> = {
        sm: 'h-8 px-3 text-xs rounded-md',
        md: 'h-10 px-4 text-sm',
        lg: 'h-12 px-6 text-base',
    };

    return map[props.size];
});

function handleClick(event: MouseEvent): void {
    if (isDisabled.value) {
        event.preventDefault();
        return;
    }

    emit('click', event);
}
</script>

<template>
  <component
    :is="props.tag"
    :href="isLink ? props.href : undefined"
    :target="isLink ? props.target : undefined"
    :type="isLink ? undefined : props.type"
    :disabled="!isLink && isDisabled ? true : undefined"
    class="select-none inline-flex items-center gap-2 rounded-lg font-semibold transition-colors disabled:opacity-45 disabled:cursor-not-allowed"
    :class="[
      variantClass,
      sizeClass,
      {
        'animate-pulse cursor-wait': props.loading,
        'flex-row-reverse': !isIconOnly && props.icon_position === 'right',
        'cursor-pointer': true,
      },
    ]"
    :aria-disabled="isDisabled || undefined"
    :aria-busy="props.loading || undefined"
    :data-testid="props.test_id"
    @click="handleClick"
  >
    <template v-if="!isIconOnly">
      <span
        v-if="props.icon || $slots.icon"
        class="inline-flex items-center"
      >
        <slot name="icon">
          <Icon
            v-if="props.icon"
            class="w-5 h-5"
            :icon="props.icon"
            aria-hidden="true"
          />
        </slot>
      </span>
      <!-- The label span is omitted when the button has no text — an
           icon-only danger/outline button must stay perfectly centred. -->
      <span v-if="$slots.default">
        <slot />
      </span>
    </template>
    <span
      v-else
      class="inline-flex items-center justify-center"
    >
      <slot name="icon">
        <Icon
          v-if="props.icon"
          class="w-5 h-5"
          :icon="props.icon"
          aria-hidden="true"
        />
      </slot>
    </span>
  </component>
</template>
