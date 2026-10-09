<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { usePage } from '@inertiajs/vue3';
import LanguageSelector from '@shared-cross/Components/LanguageSelector.vue';
import ThemeSwitch from '@shared-cross/Components/ThemeSwitch.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useThemeStore } from '@shared-cross/Stores/themeStore';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

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

// The prepared theme-variant logo (Makefile `images` pipeline) — the
// store is hydrated from the shared `theme` prop on every visit (SSR
// included), and the ThemeSwitch keeps it reactive client-side.
const themeStore = useThemeStore();
const logoImage = computed(() => `/images/logo-full-${themeStore.theme}.svg`);

// Self-contained theme/locale controls (public-section-components.md:389):
// the header renders ThemeSwitch + LanguageSelector internally unless the
// host overrides them via the `controls` slot (ADR-0023 bridge). The page must
// be read REACTIVELY: `usePage().props` is the props object of the
// current visit — capturing it once at setup keeps the stale visit's
// locale forever (the component tree survives Inertia visits), which
// breaks the language switch after the first change.
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const activeLocale = computed(() => page.props?.locale ?? 'en');

// The public selector also respects the `supported_locales` setting
// (disabled locales are not offered, 5.2 rework).
const supportedLocales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

const visibleItems = computed(() => props.nav_items.filter((item) => item.condition !== false));

// Mobile: the nav collapses into a hamburger-toggled panel below the
// header (user request 2026-08-15); a link click closes it. The panel is
// a DOM sibling of the header (NOT teleported) so the natural tab order
// stays hamburger → panel links → main (a11y 13.6 #1); it sits outside
// the header's backdrop root so its own backdrop-filter still blurs the
// page content.
const menuOpen = ref(false);
const panelEl = ref<HTMLElement | null>(null);
const hamburgerEl = ref<HTMLButtonElement | null>(null);

function toggleMenu(): void {
    menuOpen.value = !menuOpen.value;
}

function closeMenu(): void {
    menuOpen.value = false;
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && menuOpen.value) {
        closeMenu();
        hamburgerEl.value?.focus();
    }
}

// Click-away dismissal (the admin mobile menu pattern, admin-section-
// components.md): any pointer press outside the panel and its hamburger
// closes it — including the header controls, so a header popup such as
// the language dropdown never sits under the panel.
function handlePointerDown(event: Event): void {
    if (!menuOpen.value) {
        return;
    }

    const target = event.target;
    if (!(target instanceof Node)) {
        return;
    }

    if (panelEl.value?.contains(target) === true || hamburgerEl.value?.contains(target) === true) {
        return;
    }

    closeMenu();
}

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('pointerdown', handlePointerDown);
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('pointerdown', handlePointerDown);
});

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
        :aria-label="t('nav.home_link')"
        data-testid="public-header-logo"
      >
        <img
          class="header__logo-image"
          :src="logoImage"
          :alt="props.logo_text || t('nav.home_link')"
          width="83"
          height="22"
          data-testid="public-header-logo-image"
        >
      </a>
      <ul
        class="header__menu"
        :class="{ 'is-open': menuOpen }"
      >
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
            @click="closeMenu"
          >
            {{ item.label }}
          </a>
        </li>
      </ul>
      <div class="header__controls">
        <slot name="controls">
          <LanguageSelector
            :active_locale="activeLocale"
            :supported_locales="supportedLocales"
            track_locale_changes
          />
          <ThemeSwitch />
        </slot>
      </div>
      <button
        ref="hamburgerEl"
        type="button"
        class="header__hamburger"
        :aria-label="t('nav.toggle_menu')"
        :aria-expanded="menuOpen"
        aria-controls="public-mobile-menu"
        :data-testid="`${props.test_id}-hamburger`"
        @click="toggleMenu"
      >
        <Icon
          class="header__hamburger-icon"
          :icon="menuOpen ? 'heroicons:x-mark' : 'heroicons:bars-3'"
          aria-hidden="true"
        />
      </button>
    </nav>
  </header>
  <!-- The mobile menu panel is a DOM sibling of the sticky header (NOT
       teleported): it must follow the hamburger in tab order (a11y
       13.6 #1), and as a non-descendant of the header it stays outside
       the header's backdrop root, so its own backdrop-filter still
       blurs the content below it. -->
  <div
    v-if="menuOpen"
    id="public-mobile-menu"
    ref="panelEl"
    class="header__mobile-menu"
    :data-testid="`${props.test_id}-mobile-menu`"
  >
    <ul class="header__mobile-menu-list">
      <li
        v-for="item in visibleItems"
        :key="item.url"
        class="header__mobile-menu-item"
      >
        <a
          class="link--nav"
          :class="{ 'is-active': isActive(item) }"
          :href="item.url"
          :aria-current="isActive(item) ? 'page' : undefined"
          :data-testid="`public-header-nav-${item.url}`"
          @click="closeMenu"
        >
          {{ item.label }}
        </a>
      </li>
    </ul>
  </div>
</template>

<style scoped>
.header {
    position: sticky;
    top: 0;
    z-index: var(--z-header);
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

    /* The header content follows the landing content width token
       (user request 2026-08-15). */
    max-width: var(--layout-content);
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

.header__logo-image {
    display: block;
    height: 32px;
    width: auto;
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

/* The hamburger only exists on mobile — desktop keeps the inline menu. */
.header__hamburger {
    display: none;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    padding: 0;
    border: 1px solid var(--color-border);
    border-radius: var(--radius-md);
    background: var(--color-surface);
    cursor: pointer;
    color: var(--color-text-secondary);
    transition: all var(--transition-fast);
}

.header__hamburger:hover {
    background: var(--color-bg-secondary);
    color: var(--color-text-primary);
}

.header__hamburger-icon {
    width: 1.25em;
    height: 1.25em;
}

/* Mobile: only logo + controls + hamburger in the bar; the inline menu
   hides and the teleported glass panel (below) takes over. */
@media (width <= 768px) {
    .header__hamburger {
        display: inline-flex;
    }

    .header__menu {
        display: none;
    }

    /* User rework 2026-08-16: on mobile the language+theme group sits
       flush against the hamburger — right-aligned (the nav's
       space-between would otherwise center it) with the SAME gap
       between the hamburger and the group as inside the group
       (space-md), so the three buttons read as one unit. */
    .header__nav {
        gap: var(--space-md);
    }

    .header__controls {
        margin-left: auto;
    }
}

/* The teleported panel — fixed below the sticky header (its top edge
   aligns with the header's bottom edge). Teleported to <body> so the
   backdrop-filter blurs the page content: inside the header it would sit
   in the header's backdrop root and blur nothing below it. */
.header__mobile-menu {
    position: fixed;
    top: var(--header-height);
    left: 0;
    right: 0;
    z-index: var(--z-sticky);
    padding: var(--space-sm) var(--space-xl) var(--space-lg);
    background: var(--glass-bg);
    backdrop-filter: blur(var(--glass-blur));
    border-bottom: 1px solid var(--glass-border);
    box-shadow: var(--shadow-md);
}

.header__mobile-menu-list {
    list-style: none;
    margin: 0;
    padding: 0;
}

.header__mobile-menu-item {
    border-bottom: 1px solid var(--color-border);
}

.header__mobile-menu-item:last-child {
    border-bottom: none;
}

.header__mobile-menu-item .link--nav {
    display: block;
    padding: var(--space-md) 0;
    font-size: var(--font-size-lg);
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
