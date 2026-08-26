<!-- RestoreForm (admin-section-components.md:1511-1524, backups.html) — the same-instance restore: the completed-backup dropdown, the destructive warning, the current-password confirmation and the indeterminate ProgressBar driven by the parent's status polling. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import ProgressBar from '@shared-admin/ProgressBar.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, ref, watch } from 'vue';

export interface RestoreBackupOption {
    id: number;
    filename: string;
}

const props = withDefaults(
    defineProps<{
        backups?: RestoreBackupOption[];
        is_restoring?: boolean;
        restore_progress?: number | null;
        restore_error?: string | null;
        password_error?: string | null;
        test_id?: string;
    }>(),
    {
        backups: () => [],
        is_restoring: false,
        restore_progress: null,
        restore_error: null,
        password_error: null,
        test_id: 'restore-form',
    },
);

const emit = defineEmits<{
    restore: [payload: { backup_id: number; password: string }];
}>();

const { t } = useI18n();

const selectedId = ref<string>('');
const password = ref('');

const options = computed(() =>
    props.backups.map((backup) => ({
        value: String(backup.id),
        label: backup.filename,
    })),
);

// The full filename of the selected backup — the trigger label is
// truncated, the native tooltip carries the whole name (the same
// pattern as the blog form's Related Articles field).
const selectedLabel = computed(
    () => options.value.find((option) => option.value === selectedId.value)?.label ?? '',
);

const canSubmit = computed(
    () => !props.is_restoring && selectedId.value !== '' && password.value.trim() !== '',
);

watch(
    () => props.is_restoring,
    (restoring) => {
        if (restoring) {
            return;
        }

        // Back to idle — drop the submitted password from the form.
        password.value = '';
    },
);

function submit(): void {
    if (!canSubmit.value) {
        return;
    }

    emit('restore', {
        backup_id: Number(selectedId.value),
        password: password.value,
    });
}
</script>

<template>
  <section
    class="mt-8 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="props.test_id"
  >
    <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.backups.restore') }}
    </h2>
    <div class="mb-4 flex items-start gap-3 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-900 dark:bg-warning-950/50 dark:text-warning-400">
      <Icon
        class="mt-0.5 w-5 h-5"
        icon="heroicons:exclamation-triangle"
        aria-hidden="true"
      />
      <p>{{ t('admin.backups.restore_warning') }}</p>
    </div>
    <div class="grid max-w-lg grid-cols-1 gap-4 sm:grid-cols-2">
      <AdminBaseDropdown
        v-model="selectedId"
        :options="options"
        :label="t('admin.backups.select_backup')"
        :placeholder="t('admin.backups.select_backup')"
        :title="selectedLabel"
        :disabled="props.is_restoring"
        class="sm:col-span-2"
        :test_id="`${props.test_id}-backup`"
      />
      <AdminBaseInput
        v-model="password"
        type="password"
        :label="t('admin.backups.confirm_password')"
        :required="true"
        :disabled="props.is_restoring"
        :autocomplete="'current-password'"
        :error="props.password_error"
        :test_id="`${props.test_id}-password`"
      />
    </div>
    <div
      v-if="props.is_restoring"
      class="mt-4 max-w-md"
      :data-testid="`${props.test_id}-progress`"
    >
      <ProgressBar
        :value="props.restore_progress"
        :label="t('admin.backups.restoring')"
        :test_id="`${props.test_id}-progress-bar`"
      />
    </div>
    <div class="mt-4">
      <AdminBaseButton
        variant="danger"
        :disabled="!canSubmit"
        :loading="props.is_restoring"
        :test_id="`${props.test_id}-submit`"
        @click="submit"
      >
        {{ t('admin.backups.restore_selected') }}
      </AdminBaseButton>
      <p
        v-if="props.restore_error !== null"
        class="mt-2 text-sm text-danger-600 dark:text-danger-400 font-medium"
        role="alert"
        :data-testid="`${props.test_id}-error`"
      >
        {{ props.restore_error }}
      </p>
    </div>
  </section>
</template>
