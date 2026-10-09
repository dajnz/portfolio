<!-- EncryptionKeyList — the key table (name / created / active / backups) with the Generate New Key action and the per-key Delete (enabled only when no backups reference the key; disabled rows carry the "N backups reference this key." tooltip). Rows carry the DataTable's zebra styling; the created/status/count columns have fixed widths so the rows stay aligned across locales (the RU count label fits 4-digit totals). -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import AdminBaseBadge from '@shared-admin/AdminBaseBadge.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useBackupFormat } from '../Composables/useBackupFormat';

export interface EncryptionKeyListItem {
    id: number;
    name: string;
    created_at: string;
    is_active: boolean;
    backup_count: number;
}

const props = withDefaults(
    defineProps<{
        keys?: EncryptionKeyListItem[];
        test_id?: string;
    }>(),
    {
        keys: () => [],
        test_id: 'encryption-key-list',
    },
);

const emit = defineEmits<{
    generate: [];
    delete: [keyId: number];
}>();

const { t } = useI18n();
// The created column renders the same UTC date format as the table's
// Date column — the shared formatting composable (no local `pad()`).
const { formatBackupDate } = useBackupFormat();
</script>

<template>
  <section
    class="mt-8 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="props.test_id"
  >
    <div class="mb-4 flex items-center justify-between">
      <h2 class="text-lg font-medium text-slate-600 dark:text-slate-400">
        {{ t('admin.backups.encryption_keys') }}
      </h2>
      <AdminBaseButton
        variant="secondary"
        icon="heroicons:plus"
        :test_id="`${props.test_id}-generate`"
        @click="emit('generate')"
      >
        {{ t('admin.backups.generate_new_key') }}
      </AdminBaseButton>
    </div>
    <div class="divide-y divide-slate-100 bg-white dark:divide-slate-700 dark:bg-surface-dark">
      <div
        v-for="key in props.keys"
        :key="key.id"
        class="flex items-center gap-4 px-4 py-3 odd:bg-surface-secondary dark:odd:bg-surface-dark-secondary"
        :data-testid="`${props.test_id}-key-${key.id}`"
      >
        <Icon
          class="w-5 h-5 shrink-0 text-slate-400"
          icon="heroicons:key"
          aria-hidden="true"
        />
        <p class="min-w-0 flex-1 truncate font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">
          {{ key.name }}
        </p>
        <p class="w-24 shrink-0 text-right font-mono text-xs text-slate-400 dark:text-slate-500">
          {{ formatBackupDate(key.created_at) }}
        </p>
        <div class="flex w-28 shrink-0 justify-center">
          <AdminBaseBadge
            size="sm"
            :variant="key.is_active ? 'success' : 'default'"
            :test_id="`${props.test_id}-key-${key.id}-active`"
          >
            {{ key.is_active ? t('admin.backups.active') : t('admin.backups.inactive') }}
          </AdminBaseBadge>
        </div>
        <p
          class="w-44 shrink-0 truncate text-center font-mono text-xs text-slate-400 dark:text-slate-500"
          :title="t('admin.backups.backup_count', { count: key.backup_count })"
        >
          {{ t('admin.backups.backup_count', { count: key.backup_count }) }}
        </p>
        <AdminBaseButton
          variant="danger"
          size="sm"
          class="shrink-0"
          :disabled="key.backup_count > 0"
          :title="
            key.backup_count > 0
              ? t('admin.backups.references_key', { count: key.backup_count })
              : undefined
          "
          :test_id="`${props.test_id}-key-${key.id}-delete`"
          @click="emit('delete', key.id)"
        >
          {{ t('common.delete') }}
        </AdminBaseButton>
      </div>
      <div
        v-if="props.keys.length === 0"
        class="py-6 text-center text-sm text-slate-400 dark:text-slate-500"
        :data-testid="`${props.test_id}-empty`"
      >
        {{ t('admin.backups.no_keys') }}
      </div>
    </div>
  </section>
</template>
