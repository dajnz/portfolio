<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';

type ButtonTag = 'button' | 'a';
type ButtonType = 'button' | 'submit' | 'reset';
type ButtonVariant = 'primary' | 'secondary' | 'ghost' | 'icon-only' | 'outline' | 'danger';
type IconPosition = 'left' | 'right';

const props = withDefaults(
    defineProps<{
        tag?: ButtonTag;
        href?: string;
        type?: ButtonType;
        variant?: ButtonVariant;
        icon?: string;
        icon_position?: IconPosition;
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
        disabled: false,
        loading: false,
        target: undefined,
        test_id: 'base-button',
    },
);

const emit = defineEmits<{ click: [event: MouseEvent] }>();

const isIconOnly = computed(() => props.variant === 'icon-only');
const isDisabled = computed(() => props.disabled || props.loading);
const isLink = computed(() => props.tag === 'a');

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
    class="btn"
    :class="[`btn--${props.variant}`, { 'is-disabled': isDisabled, 'is-loading': props.loading }]"
    :aria-disabled="isDisabled || undefined"
    :aria-busy="props.loading || undefined"
    :data-testid="props.test_id"
    @click="handleClick"
  >
    <template v-if="!isIconOnly">
      <span
        v-if="props.icon || $slots.icon"
        class="btn__icon-wrap"
        :class="`btn__icon-wrap--${props.icon_position}`"
      >
        <slot name="icon">
          <Icon
            v-if="props.icon"
            class="btn__icon"
            :icon="props.icon"
            aria-hidden="true"
          />
        </slot>
      </span>
      <span
        class="btn__label"
        :class="{ 'btn__label--loading': props.loading }"
      >
        <slot />
      </span>
    </template>
    <span
      v-else
      class="btn__icon-only"
    >
      <slot name="icon">
        <Icon
          v-if="props.icon"
          class="btn__icon"
          :icon="props.icon"
          aria-hidden="true"
        />
      </slot>
    </span>
  </component>
</template>

<style scoped>
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: var(--space-sm);
    height: var(--btn-height);
    padding: var(--btn-padding-y) var(--btn-padding-x);
    font-size: var(--btn-font-size);
    font-weight: var(--btn-font-weight);
    border-radius: var(--btn-radius);
    border: 1px solid transparent;
    cursor: pointer;
    transition: var(--btn-transition);
    text-decoration: none;
    white-space: nowrap;
    user-select: none;
}

.btn--primary {
    background: var(--color-accent);
    color: #fff;
    border-color: var(--color-accent);
}

.btn--primary:hover {
    background: var(--color-accent-hover);
    border-color: var(--color-accent-hover);
}

.btn--primary:active {
    background: var(--color-accent-hover);
    box-shadow: inset 0 0 2px rgb(0 0 0 / 10%),
        inset 0 1px 3px rgb(0 0 0 / 30%);
}

.btn--secondary {
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
    border-color: var(--color-border);
}

.btn--secondary:hover {
    background: var(--color-bg-tertiary);
}

.btn--secondary:active {
    box-shadow: inset 0 0 1px rgb(0 0 0 / 10%),
        inset 0 1px 2px rgb(0 0 0 / 10%);
}

.btn--outline {
    background: transparent;
    color: var(--color-accent);
    border-color: var(--color-accent);
}

.btn--outline:hover {
    background: var(--color-accent);
    color: #fff;
}

.btn--outline:active {
    box-shadow: inset 0 0 2px rgb(0 0 0 / 10%),
        inset 0 1px 3px rgb(0 0 0 / 30%);
}

.btn--ghost {
    background: transparent;
    color: var(--color-text-secondary);
    border-color: transparent;
}

.btn--ghost:hover {
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
}

.btn--ghost:active {
    box-shadow: inset 0 0 1px rgb(0 0 0 / 10%),
        inset 0 1px 2px rgb(0 0 0 / 10%);
}

.btn--danger {
    background: var(--color-error);
    color: #fff;
    border-color: var(--color-error);
}

.btn--danger:hover {
    background: #953a30;
    border-color: #953a30;
}

.btn--danger:active {
    box-shadow: inset 0 0 2px rgb(0 0 0 / 10%),
        inset 0 1px 3px rgb(0 0 0 / 30%);
}

.btn--icon-only {
    width: var(--btn-height);
    padding: 0;
}

.btn.is-disabled {
    opacity: 0.45;
    pointer-events: none;
    cursor: not-allowed;
}

.btn.is-loading {
    animation: btn-pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
    cursor: wait;
}

.btn.is-loading:hover,
.btn.is-loading:active {
    background-color: var(--color-accent);
    border-color: var(--color-accent);
    box-shadow: none;
}

.btn__label--loading {
    visibility: hidden;
}

.btn__icon-wrap {
    display: inline-flex;
    align-items: center;
}

.btn__icon-wrap--right {
    order: 1;
}

.btn__icon-only {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.btn__icon {
    width: 1.25em;
    height: 1.25em;
    flex-shrink: 0;
}

@keyframes btn-pulse {
    0%,
    100% {
        opacity: 1;
    }

    50% {
        opacity: 0.7;
    }
}
</style>
