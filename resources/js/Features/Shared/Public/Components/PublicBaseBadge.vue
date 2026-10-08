<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

type BadgeVariant = 'default' | 'primary' | 'success' | 'warning' | 'danger';

const props = withDefaults(
    defineProps<{
        variant?: BadgeVariant;
        size?: 'sm' | 'md';
        removable?: boolean;
        clickable?: boolean;
        icon?: string;
        /** Accessible name of the remove button — callers pass the item
         *  name so each removal is distinguishable; falls back to the
         *  generic localized "Remove". */
        remove_label?: string;
        test_id?: string;
    }>(),
    {
        variant: 'default',
        size: 'md',
        removable: false,
        clickable: false,
        icon: undefined,
        remove_label: undefined,
        test_id: 'base-badge',
    },
);

const { t } = useI18n();

const emit = defineEmits<{
    remove: [];
    click: [event: MouseEvent];
}>();

const variantClass = computed(() => {
    const map: Record<BadgeVariant, string> = {
        default: 'badge--default',
        primary: 'badge--accent',
        success: 'badge--success',
        warning: 'badge--warning',
        danger: 'badge--error',
    };

    return map[props.variant];
});

function handleClick(event: MouseEvent): void {
    emit('click', event);
}

function onKeydown(event: KeyboardEvent): void {
    if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        emit('click', event as unknown as MouseEvent);
    }
}
</script>

<template>
  <span
    class="badge"
    :class="[variantClass, `badge--${props.size}`, { 'badge--clickable': props.clickable }]"
    :role="props.clickable ? 'button' : undefined"
    :tabindex="props.clickable ? 0 : undefined"
    :data-testid="props.test_id"
    @click="props.clickable ? handleClick($event) : undefined"
    @keydown="props.clickable ? onKeydown($event) : undefined"
  >
    <Icon
      v-if="props.icon"
      class="badge__icon"
      :icon="props.icon"
      aria-hidden="true"
    />
    <slot />
    <button
      v-if="props.removable"
      type="button"
      class="badge__remove"
      :aria-label="props.remove_label ?? t('common.remove')"
      :data-testid="`${props.test_id}-remove`"
      @click.stop="emit('remove')"
    >
      <Icon
        class="badge__remove-icon"
        icon="heroicons:x-mark"
        aria-hidden="true"
      />
    </button>
  </span>
</template>

<style scoped>
.badge {
    display: inline-flex;
    align-items: center;
    gap: 0.3em;
    padding: 0.2em 0.65em;
    font-size: var(--font-size-sm);
    font-weight: var(--font-medium);
    border-radius: var(--radius-full);
    white-space: nowrap;
    transition: all var(--transition-fast) var(--ease-default);
}

.badge--default {
    background: var(--color-bg-secondary);
    color: var(--color-text-secondary);
    border: 1px dashed var(--color-border);
}

.badge--accent {
    background: var(--color-accent-muted);
    color: var(--color-accent);
    border: 1px dashed var(--color-accent);
}

.badge--success {
    background: var(--color-success-bg);
    color: var(--color-success);
    border: 1px dashed var(--color-success);
}

.badge--warning {
    background: var(--color-warning-bg);
    color: var(--color-warning);
    border: 1px dashed var(--color-warning);
}

.badge--error {
    background: var(--color-error-bg);
    color: var(--color-error);
    border: 1px dashed var(--color-error);
}

.badge--sm {
    font-size: var(--font-size-xs);
    padding: 0.15em 0.5em;
}

.badge--clickable {
    cursor: pointer;
    user-select: none;
    text-decoration: none;
    border: 1px dashed var(--color-border);
}

.badge--clickable:hover {
    background: var(--color-accent);
    border: 1px dashed var(--color-accent);
    color: #fff;
}

.badge--clickable.badge--default:hover {
    background: var(--color-bg-tertiary);
    color: var(--color-text-primary);
}

.badge__icon {
    width: 1em;
    height: 1em;
    flex-shrink: 0;
}

.badge__remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-left: 0.1em;
    margin-right: -0.3em;
    width: 1.15em;
    height: 1.15em;
    border: none;
    border-radius: var(--radius-full);
    background: transparent;
    color: inherit;
    cursor: pointer;
    padding: 0;
    font-size: 1em;
    line-height: 1;
}

.badge__remove:hover {
    background: rgb(0 0 0 / 12%);
}

.badge__remove-icon {
    width: 1em;
    height: 1em;
}
</style>
