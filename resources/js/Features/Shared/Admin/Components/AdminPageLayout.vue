<!-- AdminPageLayout (admin-section-components.md:370-403) — root layout for all admin Inertia pages except Login. -->
<script setup lang="ts">
import { useAdminShortcuts } from '@shared-cross/Composables/useAdminShortcuts';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useSidebarStore } from '@shared-cross/Stores/sidebarStore';
import { computed, ref, watch } from 'vue';

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
        logout_url: undefined,
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

const COLLAPSED_STORAGE_KEY = 'admin_sidebar_collapsed';

function readCollapsed(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    try {
        const raw = window.localStorage.getItem(COLLAPSED_STORAGE_KEY);
        if (!raw) {
            return false;
        }

        const parsed: unknown = JSON.parse(raw);

        return parsed === true;
    } catch {
        return false;
    }
}

const collapsed = ref(readCollapsed());

watch(collapsed, (value) => {
    if (typeof window === 'undefined') {
        return;
    }

    window.localStorage.setItem(COLLAPSED_STORAGE_KEY, JSON.stringify(value));
});

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
      :sidebar_collapsed="collapsed"
      :locale_props="props.locale_props"
      :theme_props="props.theme_props"
      :language_classes="props.language_classes"
      @toggle-sidebar="collapsed = !collapsed"
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
        :collapsed="collapsed"
        :dashboard="props.dashboard"
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
