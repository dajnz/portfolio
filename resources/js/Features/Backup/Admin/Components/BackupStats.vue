<!-- BackupStats — the stats card: total count + size, the encryption state ("Active (k{id})" / "Inactive"), the last backup stamp and the Create Backup Now action. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

export interface BackupStatsData {
    total_backups: number;
    total_size_bytes: number;
    total_size_display: string;
    encryption_active: boolean;
    last_backup_date: string | null;
}

const props = withDefaults(
    defineProps<{
        stats: BackupStatsData;
        active_key_id?: number | null;
        is_creating?: boolean;
        test_id?: string;
    }>(),
    {
        active_key_id: null,
        is_creating: false,
        test_id: 'backup-stats',
    },
);

const emit = defineEmits<{
    'create-backup': [];
}>();

const { t } = useI18n();

const encryptionLabel = (): string => {
    if (props.active_key_id !== null) {
        return t('admin.backups.active_key', { key_id: props.active_key_id });
    }

    return t('admin.backups.inactive');
};
</script>

<template>
  <div
    class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="props.test_id"
  >
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="flex flex-wrap gap-8">
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            {{ t('admin.backups.total_backups') }}
          </p>
          <p
            class="mt-0.5 font-mono text-xl font-bold text-slate-900 dark:text-white"
            :data-testid="`${props.test_id}-total`"
          >
            {{ props.stats.total_backups }}
          </p>
        </div>
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            {{ t('admin.backups.total_size') }}
          </p>
          <p
            class="mt-0.5 font-mono text-xl font-bold text-slate-900 dark:text-white"
            :data-testid="`${props.test_id}-size`"
          >
            {{ props.stats.total_size_display }}
          </p>
        </div>
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            {{ t('admin.backups.encryption') }}
          </p>
          <p
            class="mt-0.5 flex items-center gap-1.5 text-sm font-semibold text-emerald-600 dark:text-emerald-400"
            :class="props.active_key_id === null ? 'text-slate-500 dark:text-slate-400' : ''"
            :data-testid="`${props.test_id}-encryption`"
          >
            <Icon
              class="w-4 h-4"
              icon="heroicons:shield-check"
              aria-hidden="true"
            />
            {{ encryptionLabel() }}
          </p>
        </div>
        <div>
          <p class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500">
            {{ t('admin.backups.last_backup') }}
          </p>
          <p
            class="mt-0.5 font-mono text-sm font-semibold text-slate-700 dark:text-slate-200"
            :data-testid="`${props.test_id}-last-backup`"
          >
            {{ props.stats.last_backup_date ?? '—' }}
          </p>
        </div>
      </div>
      <AdminBaseButton
        variant="primary"
        icon="heroicons:plus"
        :loading="props.is_creating"
        :test_id="`${props.test_id}-create`"
        @click="emit('create-backup')"
      >
        {{ t('admin.backups.create_now') }}
      </AdminBaseButton>
    </div>
  </div>
</template>
