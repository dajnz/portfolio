<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { computed } from 'vue';

type BadgeVariant = 'default' | 'primary' | 'success' | 'warning' | 'danger';

const props = withDefaults(
    defineProps<{
        variant?: BadgeVariant;
        size?: 'sm' | 'md';
        removable?: boolean;
        clickable?: boolean;
        icon?: string;
        test_id?: string;
    }>(),
    {
        variant: 'default',
        size: 'md',
        removable: false,
        clickable: false,
        icon: undefined,
        test_id: 'base-badge',
    },
);

const emit = defineEmits<{
    remove: [];
    click: [event: MouseEvent];
}>();

const variantClass = computed(() => {
    const map: Record<BadgeVariant, string> = {
        default: 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400',
        primary: 'bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300',
        success: 'bg-success-50 dark:bg-success-950 text-success-700 dark:text-success-400',
        warning: 'bg-warning-50 dark:bg-warning-950 text-warning-700 dark:text-warning-400',
        danger: 'bg-danger-50 dark:bg-danger-950 text-danger-700 dark:text-danger-400',
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
    class="inline-flex items-center gap-1 font-semibold rounded-full"
    :class="[
      variantClass,
      props.size === 'sm' ? 'text-xs px-2.5 py-0.5' : 'text-sm px-2.5 py-1',
      { 'cursor-pointer': props.clickable },
    ]"
    :role="props.clickable ? 'button' : undefined"
    :tabindex="props.clickable ? 0 : undefined"
    :data-testid="props.test_id"
    @click="props.clickable ? handleClick($event) : undefined"
    @keydown="props.clickable ? onKeydown($event) : undefined"
  >
    <Icon
      v-if="props.icon"
      class="w-3.5 h-3.5"
      :icon="props.icon"
      aria-hidden="true"
    />
    <slot />
    <button
      v-if="props.removable"
      type="button"
      class="inline-flex items-center justify-center text-current/60 hover:opacity-70"
      :aria-label="'Remove'"
      :data-testid="`${props.test_id}-remove`"
      @click.stop="emit('remove')"
    >
      <Icon
        class="w-3.5 h-3.5 cursor-pointer"
        icon="heroicons:x-mark"
        aria-hidden="true"
      />
    </button>
  </span>
</template>
