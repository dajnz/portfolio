<!-- AdminPageSidebar (admin-section-components.md:331-353, admin-section-ui.md:47-53) — collapsible navigation groups, sticky on desktop, icons-only when collapsed. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { Link } from '@inertiajs/vue3';
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
        collapsed?: boolean;
        expanded_groups?: Array<string>;
        dashboard?: DashboardItem | null;
        test_id?: string;
    }>(),
    {
        menu_groups: () => [],
        active_route: undefined,
        collapsed: false,
        expanded_groups: () => [],
        dashboard: null,
        test_id: 'admin-sidebar',
    },
);

const emit = defineEmits<{
    'update:collapsed': [];
    'update:expanded_groups': [ids: string[]];
}>();

const { t } = useI18n();

const widthClass = computed(() => (props.collapsed ? 'w-16' : 'w-64'));

const activeGroupIds = computed(() =>
    props.menu_groups
        .filter((group) => group.items.some((item) => item.route === props.active_route))
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

function isActive(route: string): boolean {
    return route === props.active_route;
}

function groupHeaderClass(): string {
    return [
        'select-none w-full py-1.5 text-xs font-bold uppercase tracking-wider text-sidebar-text/60',
        props.collapsed
            ? 'flex items-center justify-center'
            : 'flex items-center justify-between px-4',
    ].join(' ');
}

function navItemClass(route: string): string {
    return [
        'flex items-center rounded-lg text-sm font-medium text-sidebar-text hover:text-sidebar-text-active hover:bg-sidebar-hover transition-colors',
        props.collapsed ? 'justify-center px-0 py-2.5 mx-2' : 'gap-3 px-4 py-2.5 mx-2',
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
</script>

<template>
  <aside
    class="flex h-full flex-col bg-sidebar text-sidebar-text lg:sticky lg:top-0 lg:max-h-screen lg:overflow-y-auto"
    :class="widthClass"
    :aria-label="t('admin.nav.sidebar')"
    :data-testid="props.test_id"
  >
    <div class="flex flex-col pb-3">
      <Link
        v-if="props.dashboard !== null"
        class="mt-2"
        :class="navItemClass(props.dashboard.route)"
        :href="props.dashboard.route"
        :title="props.collapsed ? props.dashboard.label : undefined"
        :data-testid="`${props.test_id}-dashboard`"
      >
        <Icon
          class="w-5 h-5 shrink-0"
          :icon="props.dashboard.icon"
          aria-hidden="true"
        />
        <span v-if="!props.collapsed">{{ props.dashboard.label }}</span>
      </Link>
      <div
        v-for="group in props.menu_groups"
        :key="group.id"
        class="mt-2 flex flex-col"
      >
        <button
          type="button"
          :class="groupHeaderClass()"
          :title="props.collapsed ? group.label : undefined"
          :aria-expanded="isGroupExpanded(group.id)"
          :data-testid="`${props.test_id}-group-${group.id}`"
          @click="toggleGroup(group.id)"
        >
          <Icon
            v-if="props.collapsed"
            class="w-5 h-5 shrink-0"
            :icon="group.icon"
            aria-hidden="true"
          />
          <span v-else>{{ group.label }}</span>
          <Icon
            v-if="!props.collapsed"
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
              :title="props.collapsed ? item.label : undefined"
              :data-testid="`${props.test_id}-group-${group.id}-item-${item.route}`"
            >
              <Icon
                class="w-5 h-5 shrink-0"
                :icon="item.icon"
                aria-hidden="true"
              />
              <span v-if="!props.collapsed">{{ item.label }}</span>
            </Link>
            <a
              v-else
              :class="navItemClass(item.route)"
              :href="item.route"
              :title="props.collapsed ? item.label : undefined"
              :data-testid="`${props.test_id}-group-${group.id}-item-${item.route}`"
            >
              <Icon
                class="w-5 h-5 shrink-0"
                :icon="item.icon"
                aria-hidden="true"
              />
              <span v-if="!props.collapsed">{{ item.label }}</span>
            </a>
          </template>
        </template>
      </div>
    </div>
  </aside>
</template>
