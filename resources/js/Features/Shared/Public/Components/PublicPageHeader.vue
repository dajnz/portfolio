<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

interface NavItem {
    label: string;
    url: string;
    condition?: boolean;
}

const props = withDefaults(
    defineProps<{
        nav_items?: NavItem[];
        active_route?: string;
        logo_url?: string;
        logo_text?: string;
        test_id?: string;
    }>(),
    {
        nav_items: () => [],
        active_route: undefined,
        logo_url: '/',
        logo_text: undefined,
        test_id: 'public-header',
    },
);

const { t } = useI18n();

const visibleItems = computed(() => props.nav_items.filter((item) => item.condition !== false));

function isActive(item: NavItem): boolean {
    return props.active_route !== undefined && item.url === props.active_route;
}
</script>

<template>
  <header
    class="header"
    :data-testid="props.test_id"
  >
    <nav
      class="header__nav"
      :aria-label="t('nav.main_navigation')"
    >
      <a
        class="header__logo"
        :href="props.logo_url"
        data-testid="public-header-logo"
      >
        {{ props.logo_text }}
      </a>
      <ul class="header__menu">
        <li
          v-for="item in visibleItems"
          :key="item.url"
          class="header__menu-item"
        >
          <a
            class="link--nav"
            :class="{ 'is-active': isActive(item) }"
            :href="item.url"
            :aria-current="isActive(item) ? 'page' : undefined"
            :data-testid="`public-header-nav-${item.url}`"
          >
            {{ item.label }}
          </a>
        </li>
      </ul>
      <div
        v-if="$slots.controls"
        class="header__controls"
      >
        <slot name="controls" />
      </div>
    </nav>
  </header>
</template>

<style scoped>
.header {
    position: sticky;
    top: 0;
    z-index: var(--z-sticky);
    height: var(--header-height);
    display: flex;
    align-items: center;
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    border-bottom: 1px solid var(--glass-border);
    padding: 0 var(--space-xl);
}

.header__nav {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: var(--space-lg);
    width: 100%;
    max-width: var(--wide-max-width);
    margin: 0 auto;
}

.header__logo {
    font-family: var(--font-display);
    font-size: var(--font-size-xl);
    font-weight: var(--font-semibold);
    color: var(--color-text-primary);
    letter-spacing: var(--letter-spacing-tight);
    text-decoration: none;
    white-space: nowrap;
}

.header__menu {
    display: flex;
    align-items: center;
    gap: var(--space-lg);
    margin: 0;
    padding: 0;
}

.header__menu-item {
    list-style: none;
}

.header__controls {
    display: flex;
    align-items: center;
    gap: var(--space-md);
}

.link--nav {
    text-decoration: none;
    font-weight: var(--font-medium);
    color: var(--color-text-secondary);
    transition: color var(--transition-fast) var(--ease-default);
    position: relative;
}

.link--nav:hover {
    color: var(--color-text-primary);
}

.link--nav::after {
    content: '';
    position: absolute;
    bottom: -2px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--color-accent);
    transition: width var(--transition-normal) var(--ease-out);
}

.link--nav:hover::after {
    width: 100%;
}

.link--nav.is-active {
    color: var(--color-text-primary);
}

.link--nav.is-active::after {
    width: 100%;
}
</style>
