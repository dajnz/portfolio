<!-- Sidebar covering both viewport modes in one element: a sticky w-64 rail
     at lg+, and below lg a fixed full-width panel (id `admin-mobile-menu`)
     under the header while `mobile_open`. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link, router } from '@inertiajs/vue3';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

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

const props = withDefaults(
    defineProps<{
        menu_groups?: Array<SidebarGroup>;
        active_route?: string;
        expanded_groups?: Array<string>;
        mobile_open?: boolean;
        logout_url?: string;
        dashboard?: DashboardItem | null;
        test_id?: string;
    }>(),
    {
        menu_groups: () => [],
        active_route: undefined,
        expanded_groups: () => [],
        mobile_open: false,
        logout_url: '/admin/logout',
        dashboard: null,
        test_id: 'admin-sidebar',
    },
);

const emit = defineEmits<{
    'update:expanded_groups': [ids: string[]];
    'close-mobile': [];
}>();

const { t } = useI18n();

// Mobile-first classes: the base set renders the fixed full-width panel
// under the header; the lg:* overrides restore the in-flow sticky rail so
// `mobile_open` never affects the desktop appearance.
const asideClass = computed(() => [
    'w-full flex-col shrink-0 bg-sidebar text-sidebar-text',
    'fixed inset-x-0 bottom-0 top-16 z-overlay overflow-y-auto',
    'lg:sticky lg:inset-x-auto lg:bottom-auto lg:z-auto lg:w-64 lg:h-[calc(100vh-4rem)]',
    props.mobile_open ? 'flex' : 'hidden lg:flex',
]);

const activeGroupIds = computed(() =>
    props.menu_groups
        .filter((group) => group.items.some((item) => isActive(item.route)))
        .map((group) => group.id),
);

const effectiveExpanded = computed(() => {
    const expanded = new Set(props.expanded_groups);
    for (const groupId of activeGroupIds.value) {
        expanded.add(groupId);
    }
    return [...expanded];
});

function isGroupExpanded(groupId: string): boolean {
    return effectiveExpanded.value.includes(groupId);
}

// The pages pass the FULL route path (`active_route="/admin/experience"`) —
// a prefix match also highlights the section on sub-routes
// (`/admin/experience/create`, `/admin/experience/{id}/edit`). The
// dashboard link (`/admin`) is EXACT-match only — its prefix would
// match every admin page.
function isActive(route: string): boolean {
    if (route === '/admin') {
        return props.active_route === '/admin';
    }

    return route === props.active_route || props.active_route?.startsWith(`${route}/`) === true;
}

function groupHeaderClass(): string {
    return [
        'select-none w-full flex items-center justify-between px-4 py-1.5',
        'text-xs font-bold uppercase tracking-wider text-sidebar-text',
        'hover:text-white cursor-pointer'
    ].join(' ');
}

function navItemClass(route: string): string {
    return [
        'flex items-center gap-3 rounded-lg text-sm font-medium text-sidebar-text',
        'hover:text-sidebar-text-active hover:bg-sidebar-hover transition-colors px-4 py-2.5 mx-2',
        isActive(route) ? 'bg-sidebar-active text-white font-semibold' : '',
    ]
        .filter(Boolean)
        .join(' ');
}

function toggleGroup(groupId: string): void {
    const next = new Set(props.expanded_groups);
    if (next.has(groupId)) {
        next.delete(groupId);
    } else {
        next.add(groupId);
    }
    emit('update:expanded_groups', [...next]);
}

function handleMobileLogout(): void {
    router.post(props.logout_url);
    emit('close-mobile');
}
</script>

<template>
  <aside
    id="admin-mobile-menu"
    :class="asideClass"
    :aria-label="t('admin.nav.sidebar')"
    :data-testid="props.test_id"
  >
    <div class="flex flex-col pb-3">
      <Link
        v-if="props.dashboard !== null"
        class="mt-2"
        :class="navItemClass(props.dashboard.route)"
        :href="props.dashboard.route"
        :data-testid="`${props.test_id}-dashboard`"
        @click="emit('close-mobile')"
      >
        <Icon
          class="w-5 h-5 shrink-0"
          :icon="props.dashboard.icon"
          aria-hidden="true"
        />
        <span>{{ props.dashboard.label }}</span>
      </Link>
      <div
        v-for="group in props.menu_groups"
        :key="group.id"
        class="mt-2 flex flex-col"
      >
        <button
          type="button"
          :class="groupHeaderClass()"
          :aria-expanded="isGroupExpanded(group.id)"
          :data-testid="`${props.test_id}-group-${group.id}`"
          @click="toggleGroup(group.id)"
        >
          <span>{{ group.label }}</span>
          <Icon
            class="w-4 h-4 transition-transform"
            :class="{ 'rotate-180': isGroupExpanded(group.id) }"
            icon="heroicons:chevron-down"
            aria-hidden="true"
          />
        </button>
        <template v-if="isGroupExpanded(group.id)">
          <template
            v-for="item in group.items"
            :key="item.route"
          >
            <Link
              v-if="item.route !== '/horizon'"
              :class="navItemClass(item.route)"
              :href="item.route"
              :data-testid="`${props.test_id}-group-${group.id}-item-${item.route}`"
              @click="emit('close-mobile')"
            >
              <Icon
                class="w-5 h-5 shrink-0"
                :icon="item.icon"
                aria-hidden="true"
              />
              <span>{{ item.label }}</span>
            </Link>
            <a
              v-else
              :class="navItemClass(item.route)"
              :href="item.route"
              :data-testid="`${props.test_id}-group-${group.id}-item-${item.route}`"
              @click="emit('close-mobile')"
            >
              <Icon
                class="w-5 h-5 shrink-0"
                :icon="item.icon"
                aria-hidden="true"
              />
              <span>{{ item.label }}</span>
            </a>
          </template>
        </template>
      </div>
    </div>
    <!-- Mobile panel only (below lg; the desktop rail hides this row via
         lg:hidden): Logout is the last element of the full-width menu. -->
    <div class="mt-3 border-t border-white/10 px-2 pb-3 pt-3 lg:hidden">
      <button
        type="button"
        class="flex w-full items-center gap-3 rounded-lg px-4 py-2.5 text-sm font-medium text-sidebar-text transition-colors hover:bg-sidebar-hover hover:text-sidebar-text-active"
        :data-testid="`${props.test_id}-logout-mobile`"
        @click="handleMobileLogout"
      >
        <Icon
          class="w-5 h-5 shrink-0"
          icon="heroicons:arrow-right-start-on-rectangle"
          aria-hidden="true"
        />
        <span>{{ t('admin.nav.logout') }}</span>
      </button>
    </div>
  </aside>
</template>
