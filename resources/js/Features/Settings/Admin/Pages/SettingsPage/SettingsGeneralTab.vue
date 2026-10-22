<!-- SettingsGeneralTab — the Settings page General tab: Site Status (maintenance switch), Languages (supported-locale checkboxes), Content settings, Pagination and Data Retention. Purely presentational — values come in as props, edits go out as `update:<field>` / `toggle-locale` events. -->
<script setup lang="ts">
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseSwitch from '@shared-admin/AdminBaseSwitch.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';

const props = withDefaults(
    defineProps<{
        site_maintenance_mode: boolean;
        supported_locales: string[];
        disabled_locales: string[];
        image_max_dimension: string;
        items_per_page: string;
        projects_per_page: string;
        articles_per_page: string;
        experience_items_per_page: string;
        trash_retention_days: string;
        backup_max_count: string;
        backup_upload_max_size: string;
        errors: Record<string, string>;
        test_id?: string;
    }>(),
    {
        test_id: 'settings-general-tab',
    },
);

const emit = defineEmits<{
    'update:site_maintenance_mode': [value: boolean];
    'toggle-locale': [locale: string, checked: boolean];
    'update:image_max_dimension': [value: string];
    'update:items_per_page': [value: string];
    'update:projects_per_page': [value: string];
    'update:articles_per_page': [value: string];
    'update:experience_items_per_page': [value: string];
    'update:trash_retention_days': [value: string];
    'update:backup_max_count': [value: string];
    'update:backup_upload_max_size': [value: string];
}>();

const { t } = useI18n();

function fieldError(field: string): string | null {
    return props.errors[`settings.${field}`] ?? null;
}
</script>

<template>
  <fieldset class="min-w-0 space-y-4">
    <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.settings.site_status') }}
    </legend>
    <div class="flex flex-wrap items-center justify-between gap-4 rounded-xl border border-amber-200 bg-amber-50 p-4 dark:border-amber-900/50 dark:bg-amber-950/20">
      <div class="min-w-0">
        <span class="block text-sm font-semibold text-slate-800 dark:text-slate-100">
          {{ t('admin.settings.maintenance_mode') }}
        </span>
        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
          {{ t('admin.settings.maintenance_hint') }}
        </p>
      </div>
      <div class="flex min-w-0 items-center gap-3">
        <span
          class="text-xs font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400"
          :data-testid="`${props.test_id}-maintenance-status`"
        >
          {{
            props.site_maintenance_mode
              ? t('admin.settings.switch_on')
              : t('admin.settings.switch_off')
          }}
        </span>
        <AdminBaseSwitch
          :model-value="props.site_maintenance_mode"
          :label="t('admin.settings.maintenance_mode')"
          :test_id="`${props.test_id}-maintenance`"
          @update:model-value="(value) => emit('update:site_maintenance_mode', value)"
        />
      </div>
    </div>
  </fieldset>

  <fieldset class="space-y-3">
    <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.settings.languages') }}
    </legend>
    <div>
      <span class="mb-1.5 block text-sm font-semibold text-slate-700 dark:text-slate-200">
        {{ t('admin.settings.supported_languages') }}
      </span>
      <div class="flex flex-wrap gap-6">
        <AdminBaseCheckbox
          v-for="locale in SUPPORTED_LOCALES"
          :key="locale"
          :model-value="props.supported_locales.includes(locale)"
          :label="`${t(`admin.locales.${locale}`)} (${locale})`"
          :disabled="props.disabled_locales.includes(locale)"
          :test_id="`${props.test_id}-locale-${locale}`"
          @update:model-value="(checked) => emit('toggle-locale', locale, checked)"
        />
      </div>
    </div>
  </fieldset>

  <fieldset class="space-y-4">
    <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.settings.content_settings') }}
    </legend>
    <AdminBaseInput
      :model-value="props.image_max_dimension"
      type="number"
      min="512"
      max="4096"
      :label="t('admin.settings.image_max_dimension')"
      :help="t('admin.settings.image_max_dimension_hint')"
      :error="fieldError('image_max_dimension')"
      :test_id="`${props.test_id}-image-max-dimension`"
      @update:model-value="(value) => emit('update:image_max_dimension', value)"
    />
  </fieldset>

  <fieldset class="space-y-4">
    <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.settings.pagination') }}
    </legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <AdminBaseInput
        :model-value="props.items_per_page"
        type="number"
        min="1"
        :label="t('admin.settings.admin_items_per_page')"
        :error="fieldError('items_per_page')"
        :test_id="`${props.test_id}-items-per-page`"
        @update:model-value="(value) => emit('update:items_per_page', value)"
      />
      <AdminBaseInput
        :model-value="props.projects_per_page"
        type="number"
        min="1"
        :label="t('admin.settings.projects_per_page')"
        :error="fieldError('projects_per_page')"
        :test_id="`${props.test_id}-projects-per-page`"
        @update:model-value="(value) => emit('update:projects_per_page', value)"
      />
      <AdminBaseInput
        :model-value="props.articles_per_page"
        type="number"
        min="1"
        :label="t('admin.settings.articles_per_page')"
        :error="fieldError('articles_per_page')"
        :test_id="`${props.test_id}-articles-per-page`"
        @update:model-value="(value) => emit('update:articles_per_page', value)"
      />
      <AdminBaseInput
        :model-value="props.experience_items_per_page"
        type="number"
        min="1"
        :label="t('admin.settings.experience_items_per_page')"
        :error="fieldError('experience_items_per_page')"
        :test_id="`${props.test_id}-experience-per-page`"
        @update:model-value="(value) => emit('update:experience_items_per_page', value)"
      />
    </div>
  </fieldset>

  <fieldset class="space-y-4">
    <legend class="text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.settings.data_retention') }}
    </legend>
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
      <AdminBaseInput
        :model-value="props.trash_retention_days"
        type="number"
        min="0"
        :label="t('admin.settings.trash_retention_days')"
        :error="fieldError('trash_retention_days')"
        :test_id="`${props.test_id}-trash-days`"
        @update:model-value="(value) => emit('update:trash_retention_days', value)"
      />
      <AdminBaseInput
        :model-value="props.backup_max_count"
        type="number"
        min="1"
        :label="t('admin.settings.backup_max_count')"
        :error="fieldError('backup_max_count')"
        :test_id="`${props.test_id}-backup-max`"
        @update:model-value="(value) => emit('update:backup_max_count', value)"
      />
      <AdminBaseInput
        :model-value="props.backup_upload_max_size"
        type="number"
        min="1"
        :label="t('admin.settings.backup_upload_max_size')"
        :error="fieldError('backup_upload_max_size')"
        :test_id="`${props.test_id}-backup-size`"
        @update:model-value="(value) => emit('update:backup_upload_max_size', value)"
      />
    </div>
  </fieldset>
</template>
