<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

type Cursor = { id: number; sort_value: string | null };
type SortDirection = 'asc' | 'desc';

type PageItem = { type: 'page'; key: string; page: number } | { type: 'ellipsis'; key: string };

const props = withDefaults(
    defineProps<{
        total: number;
        per_page: number;
        current_page: number;
        cursors?: { first: Cursor; last: Cursor; page: number };
        sort_column?: string;
        sort_direction?: SortDirection;
        test_id?: string;
    }>(),
    {
        cursors: () => ({
            first: { id: 0, sort_value: null },
            last: { id: 0, sort_value: null },
            page: 1,
        }),
        sort_column: '',
        sort_direction: 'desc',
        test_id: 'pagination',
    },
);

const emit = defineEmits<{
    navigate: [payload: { cursor: Cursor } | { page: number }];
}>();

const { t } = useI18n();

const totalPages = computed(() => Math.max(1, Math.ceil(props.total / props.per_page)));

const pageItems = computed<Array<PageItem>>(() => {
    const total = totalPages.value;
    const current = props.current_page;
    const items: Array<PageItem> = [];

    const pushRange = (from: number, to: number): void => {
        for (let page = from; page <= to; page += 1) {
            items.push({ type: 'page', key: `page-${page}`, page });
        }
    };

    if (total <= 10) {
        pushRange(1, total);

        return items;
    }

    if (current <= 4) {
        pushRange(1, 5);
        items.push({ type: 'ellipsis', key: 'ellipsis-left' });
        pushRange(total - 2, total);
    } else if (current >= total - 3) {
        pushRange(1, 3);
        items.push({ type: 'ellipsis', key: 'ellipsis-left' });
        pushRange(total - 4, total);
    } else {
        pushRange(1, 3);
        items.push({ type: 'ellipsis', key: 'ellipsis-left' });
        pushRange(current - 1, current + 1);
        items.push({ type: 'ellipsis', key: 'ellipsis-right' });
        pushRange(total - 2, total);
    }

    return items;
});

function goToPrevious(): void {
    emit('navigate', { cursor: props.cursors.first });
}

function goToNext(): void {
    emit('navigate', { cursor: props.cursors.last });
}

function goToPage(page: number): void {
    emit('navigate', { page });
}
</script>

<template>
  <nav
    v-if="totalPages > 1"
    class="mt-6 flex items-center justify-center gap-3 flex-wrap"
    :data-testid="props.test_id"
  >
    <button
      type="button"
      class="select-none w-9 h-9 inline-flex items-center justify-center leading-none text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      :disabled="props.current_page === 1"
      :aria-label="t('common.previous')"
      :data-testid="`${props.test_id}-prev`"
      @click="goToPrevious"
    >
      <Icon
        class="w-5 h-5"
        icon="heroicons:chevron-left"
        aria-hidden="true"
      />
    </button>
    <div class="flex items-center gap-1">
      <template
        v-for="(item, index) in pageItems"
        :key="item.key"
      >
        <span
          v-if="item.type === 'ellipsis'"
          class="w-9 h-9 inline-flex items-center justify-center leading-none text-sm text-slate-400 dark:text-slate-600"
          :data-testid="`${props.test_id}-ellipsis-${index}`"
        >
          …
        </span>
        <span
          v-else-if="item.page === props.current_page"
          class="w-9 h-9 inline-flex items-center justify-center leading-none text-sm font-bold rounded-lg bg-primary-600 text-white"
          :data-testid="`${props.test_id}-page-${item.page}`"
        >
          {{ item.page }}
        </span>
        <button
          v-else
          type="button"
          class="select-none w-9 h-9 inline-flex items-center justify-center leading-none text-sm font-medium rounded-lg text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
          :data-testid="`${props.test_id}-page-${item.page}`"
          @click="goToPage(item.page)"
        >
          {{ item.page }}
        </button>
      </template>
    </div>
    <button
      type="button"
      class="select-none w-9 h-9 inline-flex items-center justify-center leading-none text-sm font-medium rounded-lg border border-slate-200 dark:border-slate-700 text-slate-400 dark:text-slate-600 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
      :disabled="props.current_page === totalPages"
      :aria-label="t('common.next')"
      :data-testid="`${props.test_id}-next`"
      @click="goToNext"
    >
      <Icon
        class="w-5 h-5"
        icon="heroicons:chevron-right"
        aria-hidden="true"
      />
    </button>
  </nav>
</template>
