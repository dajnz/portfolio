<!-- UploadRestoreForm (admin-section-components.md:1526-1548, backups.html) — the disaster-recovery restore: the .zip DropzoneUpload (client-side type + size validation), the monospace show/hide key input and the password confirmation, with the indeterminate ProgressBar during upload + restore. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import DropzoneUpload, { type UploadedFile } from '@shared-admin/DropzoneUpload.vue';
import ProgressBar from '@shared-admin/ProgressBar.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed, ref, watch } from 'vue';

const props = withDefaults(
    defineProps<{
        file?: File | null;
        encryption_key?: string;
        password?: string;
        is_restoring?: boolean;
        restore_progress?: number | null;
        restore_error?: string | null;
        /** Incremented on every successful DR restore — the form clears
         *  its inputs on the tick (B311): a reload can't do it, the
         *  component outlives the Inertia visit. */
        restore_success_tick?: number;
        /** Max file size in KB from the `backup_upload_max_size` setting
         *  (default 102400 = 100 MB, admin-section-components.md:1526). */
        max_file_size?: number;
        test_id?: string;
    }>(),
    {
        file: null,
        encryption_key: '',
        password: '',
        is_restoring: false,
        restore_progress: null,
        restore_error: null,
        restore_success_tick: 0,
        max_file_size: 102400,
        test_id: 'upload-restore-form',
    },
);

const emit = defineEmits<{
    restore: [payload: { file: File; encryption_key: string; password: string }];
}>();

const { t } = useI18n();

const KEY_MIN_LENGTH = 32;

const selectedFile = ref<File | null>(props.file);
const key = ref(props.encryption_key);
const confirmPassword = ref(props.password);
const showKey = ref(false);

const fileError = ref<string | null>(null);
const keyError = ref<string | null>(null);
const passwordError = ref<string | null>(null);

// A successful DR restore clears the submitted inputs (B311) — the
// parent increments the tick exactly when the restore-mode poll
// observes the completion (a failed restore keeps the inputs so the
// admin can retry). Failure-driven server errors land via props and
// stay untouched here.
watch(
    () => props.restore_success_tick,
    (tick) => {
        if (tick <= 0) {
            return;
        }

        selectedFile.value = null;
        key.value = '';
        confirmPassword.value = '';
        showKey.value = false;
        fileError.value = null;
        keyError.value = null;
        passwordError.value = null;
    },
);

const fileInputError = computed(() => fileError.value ?? props.restore_error);

// The shared dropzone's `modelValue` is an uploaded-file shape — the DR
// form re-uploads the raw File, so the selected file is mirrored into a
// display-only entry (document icon + name + Replace/Remove).
const displayFile = computed<UploadedFile | null>(() =>
    selectedFile.value === null
        ? null
        : { file_path: selectedFile.value.name, file_name: selectedFile.value.name },
);

function onFilePicked(file: File): void {
    if (!file.name.toLowerCase().endsWith('.zip')) {
        fileError.value = t('admin.backups.zip_file_required');
        selectedFile.value = null;

        return;
    }

    fileError.value = null;
    selectedFile.value = file;
}

function removeFile(): void {
    selectedFile.value = null;
    fileError.value = null;
}

function validate(): boolean {
    fileError.value = null;
    keyError.value = null;
    passwordError.value = null;

    if (selectedFile.value === null) {
        fileError.value = t('admin.backups.file_required');

        return false;
    }

    if (key.value.trim().length < KEY_MIN_LENGTH) {
        keyError.value = t('admin.backups.key_min_length');

        return false;
    }

    if (confirmPassword.value === '') {
        passwordError.value = t('admin.backups.password_required');

        return false;
    }

    return true;
}

function submit(): void {
    if (props.is_restoring || !validate() || selectedFile.value === null) {
        return;
    }

    emit('restore', {
        file: selectedFile.value,
        encryption_key: key.value.trim(),
        password: confirmPassword.value,
    });
}
</script>

<template>
  <section
    class="mt-8 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
    :data-testid="props.test_id"
  >
    <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.backups.upload_backup') }}
      <span class="ml-1 rounded-full bg-primary-50 px-2 py-0.5 text-xs font-bold uppercase tracking-wider text-primary-700 dark:bg-primary-950 dark:text-primary-300">
        {{ t('admin.backups.disaster_recovery') }}
      </span>
    </h2>
    <div class="mb-4 flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 dark:border-slate-700 dark:bg-slate-900/50 dark:text-slate-400">
      <Icon
        class="mt-0.5 w-5 h-5 shrink-0"
        icon="heroicons:information-circle"
        aria-hidden="true"
      />
      <p>{{ t('admin.backups.restore_description') }}</p>
    </div>
    <div class="max-w-lg space-y-4">
      <DropzoneUpload
        :model-value="displayFile"
        :label="t('admin.backups.backup_file')"
        :accept="'.zip'"
        :max_size="props.max_file_size * 1024"
        :help="t('admin.backups.zip_limit')"
        :error="fileInputError"
        :is_loading="props.is_restoring"
        :test_id="`${props.test_id}-file`"
        @upload="onFilePicked"
        @remove="removeFile"
      />
      <div>
        <AdminBaseInput
          v-model="key"
          :type="showKey ? 'text' : 'password'"
          :label="t('admin.backups.encryption_key')"
          :required="true"
          :disabled="props.is_restoring"
          :autocomplete="'off'"
          :error="keyError"
          :test_id="`${props.test_id}-key`"
        />
        <div class="relative mt-1">
          <button
            type="button"
            class="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200"
            :aria-label="showKey ? t('admin.backups.hide_key') : t('admin.backups.show_key')"
            :data-testid="`${props.test_id}-toggle-key`"
            @click="showKey = !showKey"
          >
            <Icon
              class="w-3.5 h-3.5"
              :icon="showKey ? 'heroicons:eye-slash' : 'heroicons:eye'"
              aria-hidden="true"
            />
            {{ showKey ? t('admin.backups.hide_key') : t('admin.backups.show_key') }}
          </button>
          <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">
            {{ t('admin.backups.raw_key_warning') }}
          </p>
        </div>
      </div>
      <div class="flex items-start gap-3 rounded-lg border border-warning-200 bg-warning-50 px-4 py-3 text-sm text-warning-700 dark:border-warning-900 dark:bg-warning-950/50 dark:text-warning-400">
        <Icon
          class="mt-0.5 w-5 h-5 shrink-0"
          icon="heroicons:exclamation-triangle"
          aria-hidden="true"
        />
        <p>{{ t('admin.backups.replace_all_warning') }}</p>
      </div>
      <AdminBaseInput
        v-model="confirmPassword"
        type="password"
        :label="t('admin.backups.confirm_password')"
        :required="true"
        :disabled="props.is_restoring"
        :autocomplete="'current-password'"
        :error="passwordError"
        :test_id="`${props.test_id}-password`"
      />
      <div
        v-if="props.is_restoring"
        :data-testid="`${props.test_id}-progress`"
      >
        <ProgressBar
          :value="props.restore_progress"
          :label="t('admin.backups.restoring_files')"
          :test_id="`${props.test_id}-progress-bar`"
        />
      </div>
      <AdminBaseButton
        variant="danger"
        :disabled="props.is_restoring"
        :loading="props.is_restoring"
        :test_id="`${props.test_id}-submit`"
        @click="submit"
      >
        {{ t('admin.backups.upload_and_restore') }}
      </AdminBaseButton>
      <p
        v-if="props.restore_error !== null"
        class="text-sm text-danger-600 dark:text-danger-400 font-medium"
        role="alert"
        :data-testid="`${props.test_id}-error`"
      >
        {{ props.restore_error }}
      </p>
    </div>
  </section>
</template>
