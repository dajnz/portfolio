<!-- AdminPageLayout (admin-section-components.md:438-501) — root layout for all admin Inertia pages except Login. Owns the mobile-menu open state (`mobileMenuOpen`, page-local, never persisted — ADR-0024): the header hamburger toggles it, the sidebar reports `close-mobile` on nav-item activation, and the layout closes it on Escape (focus back to the hamburger) and on every Inertia navigation start. -->
<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useAdminShortcuts } from '@shared-cross/Composables/useAdminShortcuts';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useSidebarStore } from '@shared-cross/Stores/sidebarStore';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

import AdminBaseToast from './AdminBaseToast.vue';
import AdminCenteredContainer from './AdminCenteredContainer.vue';
import AdminGoTop from './AdminGoTop.vue';
import AdminPageFooter from './AdminPageFooter.vue';
import AdminPageHeader from './AdminPageHeader.vue';
import AdminPageSidebar from './AdminPageSidebar.vue';

interface SidebarGroupItem {
    label: string;
    route: string;
    icon: string;
}

interface SidebarGroup {
    id: string;
    label: string;
    icon: string;
    items: Array<SidebarGroupItem>;
}

interface DashboardItem {
    label: string;
    route: string;
    icon: string;
}

interface LocaleOption {
    code: string;
    native_name: string;
}

interface LocaleProps {
    active_locale: string;
    supported_locales: Array<LocaleOption>;
}

const props = withDefaults(
    defineProps<{
        menu_groups?: Array<SidebarGroup>;
        active_route?: string;
        centered?: boolean;
        logo_url?: string;
        admin_name?: string;
        profile_url?: string;
        logout_url?: string;
        locale_props?: LocaleProps;
        theme_props?: Record<string, string>;
        language_classes?: Record<string, string>;
        copyright_year?: string;
        copyright_name?: string;
        dashboard?: DashboardItem | null;
        test_id?: string;
    }>(),
    {
        menu_groups: () => [],
        active_route: undefined,
        centered: true,
        logo_url: '/admin',
        admin_name: undefined,
        profile_url: '/admin/profile',
        logout_url: '/admin/logout',
        locale_props: () => ({ active_locale: 'en', supported_locales: [] }),
        theme_props: () => ({}),
        language_classes: () => ({}),
        copyright_year: undefined,
        copyright_name: undefined,
        dashboard: null,
        test_id: 'admin-layout',
    },
);

const { t } = useI18n();

// Every admin page shows the Dashboard link at the top of the sidebar —
// the pages don't pass a `dashboard` prop; the layout supplies the
// default (ADR-0024: it was previously passed only by the profile page).
const dashboardItem = computed<DashboardItem>(() => ({
    label: t('admin.nav.dashboard'),
    route: '/admin',
    icon: 'heroicons:chart-bar',
}));

const sidebarStore = useSidebarStore();

const expandedGroups = computed<string[]>({
    get: () =>
        props.menu_groups
            .map((group) => group.id)
            .filter((groupId) => !sidebarStore.isCollapsed(groupId)),
    set: (next: string[]) => {
        const nextSet = new Set(next);
        for (const group of props.menu_groups) {
            if (nextSet.has(group.id)) {
                sidebarStore.expandGroup(group.id);
            } else {
                sidebarStore.collapseGroup(group.id);
            }
        }
    },
});

// Mobile menu state (ADR-0024): below lg the sidebar renders as the full-width
// panel under the header while this flag is true. Closed by the hamburger,
// nav-item clicks (via the sidebar's close-mobile), Escape, and navigation.
const mobileMenuOpen = ref(false);

const HAMBURGER_SELECTOR = '[data-testid="admin-header-hamburger"]';

function focusHamburger(): void {
    document.querySelector<HTMLElement>(HAMBURGER_SELECTOR)?.focus();
}

function handleKeydown(event: KeyboardEvent): void {
    if (event.key === 'Escape' && mobileMenuOpen.value) {
        mobileMenuOpen.value = false;
        focusHamburger();
    }
}

// Click-away dismissal (user request 2026-09-05): while the mobile panel is
// open, any pointer press OUTSIDE it closes it — including the header
// controls (theme/language/identity) — so a header popup like the language
// dropdown can never be overlapped by the panel. The hamburger is excluded
// (its own click toggles the panel) and so is the panel itself.
function handlePointerDown(event: Event): void {
    if (!mobileMenuOpen.value) {
        return;
    }

    const target = event.target;
    if (!(target instanceof Node)) {
        return;
    }

    const panel = document.querySelector<HTMLElement>('[data-testid="admin-sidebar"]');
    if (panel?.contains(target) === true) {
        return;
    }

    const hamburger = document.querySelector<HTMLElement>('[data-testid="admin-header-hamburger"]');
    if (hamburger?.contains(target) === true) {
        return;
    }

    mobileMenuOpen.value = false;
}

let offNavigationStart: (() => void) | undefined;

onMounted(() => {
    window.addEventListener('keydown', handleKeydown);
    window.addEventListener('pointerdown', handlePointerDown);
    offNavigationStart = router.on('start', () => {
        mobileMenuOpen.value = false;
    });
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKeydown);
    window.removeEventListener('pointerdown', handlePointerDown);
    offNavigationStart?.();
});

useAdminShortcuts();
</script>

<template>
  <div
    class="min-h-screen bg-slate-50 dark:bg-surface-dark font-admin text-slate-900 dark:text-slate-100 flex flex-col"
    :data-testid="props.test_id"
  >
    <a
      class="sr-only focus:not-sr-only focus:absolute focus:z-toast focus:px-4 focus:py-2 focus:bg-primary-600 focus:text-white"
      href="#main-content"
      :data-testid="`${props.test_id}-skip-link`"
    >
      {{ t('nav.skip_to_content') }}
    </a>
    <AdminPageHeader
      :logo_url="props.logo_url"
      :admin_name="props.admin_name"
      :profile_url="props.profile_url"
      :logout_url="props.logout_url"
      :mobile_menu_open="mobileMenuOpen"
      :locale_props="props.locale_props"
      :theme_props="props.theme_props"
      :language_classes="props.language_classes"
      @toggle-mobile-menu="mobileMenuOpen = !mobileMenuOpen"
    >
      <template #controls>
        <slot name="controls" />
      </template>
    </AdminPageHeader>
    <div class="flex flex-1">
      <AdminPageSidebar
        v-model:expanded_groups="expandedGroups"
        :menu_groups="props.menu_groups"
        :active_route="props.active_route"
        :mobile_open="mobileMenuOpen"
        :logout_url="props.logout_url"
        :dashboard="props.dashboard ?? dashboardItem"
        @close-mobile="mobileMenuOpen = false"
      />
      <AdminCenteredContainer
        id="main-content"
        tag="main"
        size="xl"
        :centered="props.centered"
        class="flex-1 py-6"
      >
        <slot />
      </AdminCenteredContainer>
    </div>
    <AdminPageFooter
      :copyright_year="props.copyright_year"
      :copyright_name="props.copyright_name"
    >
      <slot name="footer" />
    </AdminPageFooter>
    <AdminGoTop />
    <AdminBaseToast />
  </div>
</template>
