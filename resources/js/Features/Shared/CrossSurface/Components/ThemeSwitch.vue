<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useTheme } from '@shared-cross/Composables/useTheme';
import { computed } from 'vue';

interface ThemeSwitchClasses {
    button?: string;
    icon_light?: string;
    icon_dark?: string;
}

const props = withDefaults(
    defineProps<{
        classes?: ThemeSwitchClasses;
        test_id?: string;
    }>(),
    {
        classes: undefined,
        test_id: 'theme-switch',
    },
);

const { theme, toggle } = useTheme();
const { t } = useI18n();

// Icon shows the mode the switch switches TO: the light state displays
// the moon, the dark state displays the sun — the `icon_light`/`icon_dark`
// class keys follow the CURRENT state.
const isDark = computed(() => theme.value === 'dark');
const ariaLabel = computed(() =>
    isDark.value ? t('nav.switch_to_light_mode') : t('nav.switch_to_dark_mode'),
);
const icon = computed(() => (isDark.value ? 'heroicons:sun' : 'heroicons:moon'));
const iconClass = computed(() =>
    isDark.value ? props.classes?.icon_dark : props.classes?.icon_light,
);
</script>

<template>
  <span
    class="theme-switch"
    :data-testid="props.test_id"
    data-component="theme-switch"
    :data-state="isDark ? 'dark' : 'light'"
  >
    <button
      type="button"
      class="theme-switch__button"
      :class="props.classes?.button"
      :aria-label="ariaLabel"
      :data-testid="`${props.test_id}-button`"
      @click="toggle"
    >
      <Icon
        class="theme-switch__icon"
        :class="iconClass"
        :icon="icon"
        aria-hidden="true"
      />
    </button>
  </span>
</template>

<style scoped>
.theme-switch {
    display: inline-flex;
}

.theme-switch__button {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    cursor: pointer;
    color: var(--color-text-secondary);
    transition: all var(--transition-fast);
}

.theme-switch__button:hover {
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
}

.theme-switch__icon {
    width: 1.1em;
    height: 1.1em;
}
</style>
