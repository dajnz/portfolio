<!-- ResumeEditPage — the resume settings form: mode radio, the auto-mode layout editor (template + ResumeSectionEditor) with per-locale About Me text, the inclusion summary, Regenerate + Download preview, and the manual-mode PDF dropzone. Layout changes (mode/template/visibility/order) auto-save via PUT /admin/resume without `about_text` (the backend leaves the stored texts untouched — UpdateResumeConfigUseCase reconciles only the provided locales); the About Me texts persist via the explicit Save button. -->
<script setup lang="ts">
import type { RequestPayload } from '@inertiajs/core';
import { router, usePage } from '@inertiajs/vue3';
import ResumeSectionEditor from '@resume-admin/ResumeSectionEditor.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminBaseTextarea from '@shared-admin/AdminBaseTextarea.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import AdminRadioGroup from '@shared-admin/AdminRadioGroup.vue';
import DropzoneUpload, { type UploadedFile } from '@shared-admin/DropzoneUpload.vue';
import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { useUnsavedChanges } from '@shared-cross/Composables/useUnsavedChanges';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, reactive, ref } from 'vue';

import {
    DEFAULT_RESUME_LAYOUT,
    type ResumeLayout,
    type ResumeSectionKey,
    type ResumeSectionsMap,
} from '../Types/layout';

interface ResumeEditProps {
    mode?: 'auto' | 'manual';
    layout?: ResumeLayout;
    custom_resume_file?: string | null;
    about_texts?: Record<string, string>;
    included_skills_count?: number;
    included_experience_count?: number;
    included_contacts_count?: number;
    test_id?: string;
}

interface ResumeFormValues {
    mode: 'auto' | 'manual';
    layout: ResumeLayout;
    customResumeFile: string | null;
    aboutTexts: Record<string, string>;
}

type FormKey = keyof ResumeFormValues;

const props = withDefaults(defineProps<ResumeEditProps>(), {
    mode: 'auto',
    layout: () => JSON.parse(JSON.stringify(DEFAULT_RESUME_LAYOUT)) as ResumeLayout,
    custom_resume_file: null,
    about_texts: () => ({}),
    included_skills_count: 0,
    included_experience_count: 0,
    included_contacts_count: 0,
    test_id: 'resume-edit-page',
});

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();
const { post } = useJsonApi();

const SAVE_KEYS: FormKey[] = ['mode', 'layout', 'customResumeFile', 'aboutTexts'];
const AUTO_SAVE_KEYS: FormKey[] = ['mode', 'layout', 'customResumeFile'];

// The per-locale editing tabs only offer the ACTIVE locales (the shared
// `supported_locales` prop — disabled locales are not editable).
const locales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

const SECTION_LABELS: Record<ResumeSectionKey, string> = {
    header: t('resume.section_header'),
    about_me: t('resume.section_about_me'),
    skills: t('resume.section_skills'),
    experience: t('resume.section_experience'),
    contacts: t('resume.section_contacts'),
};

function buildLayout(layout: ResumeLayout | undefined): ResumeLayout {
    const template =
        layout?.template === 'classic' || layout?.template === 'compact'
            ? layout.template
            : 'modern';

    const sections = { ...DEFAULT_RESUME_LAYOUT.sections };

    for (const key of Object.keys(sections) as ResumeSectionKey[]) {
        const incoming = layout?.sections?.[key];

        if (incoming !== undefined) {
            sections[key] = { ...incoming };
        }
    }

    return { template, sections };
}

function buildForm(editProps: ResumeEditProps): ResumeFormValues {
    return {
        mode: editProps.mode === 'manual' ? 'manual' : 'auto',
        layout: buildLayout(editProps.layout),
        customResumeFile: editProps.custom_resume_file ?? null,
        aboutTexts: { ...(editProps.about_texts ?? {}) },
    };
}

const form = reactive<ResumeFormValues>(buildForm(props));
const baseline = buildForm(props);

// The guard works on plain-data records; the strongly-typed form is
// structurally a record of serializable values.
const unsavedChanges = useUnsavedChanges(
    form as unknown as Record<string, unknown>,
    baseline as unknown as Record<string, unknown>,
);

const activeLocale = ref('en');
const customFileName = ref<string | null>(null);
const isSaving = ref(false);
const isAutoSaving = ref(false);
const isUploading = ref(false);
const isRegenerating = ref(false);
const errors = ref<Record<string, string>>({});

const resumeFile = computed<UploadedFile | null>(() => {
    if (form.customResumeFile === null) {
        return null;
    }

    const name =
        customFileName.value ?? form.customResumeFile.split('/').pop() ?? form.customResumeFile;

    return { file_path: form.customResumeFile, file_name: name };
});

const downloadPreviewUrl = computed(() => `/cv?locale=${activeLocale.value}`);

function setMode(value: string): void {
    form.mode = value === 'manual' ? 'manual' : 'auto';
    autoSave();
}

function setTemplate(value: string | string[]): void {
    form.layout.template = value === 'classic' || value === 'compact' ? value : 'modern';
    autoSave();
}

function setSections(next: ResumeSectionsMap): void {
    form.layout.sections = next;
    autoSave();
}

/** The auto-saved changes never carry `about_text` — the backend leaves
 *  the stored texts untouched, so unsaved textarea edits survive the
 *  round-trip (UpdateResumeConfigUseCase reconciles only the provided
 *  locales). */
function autoSave(): void {
    if (isSaving.value || isAutoSaving.value) {
        return;
    }

    isAutoSaving.value = true;
    errors.value = {};
    unsavedChanges.suppressNextVisit();

    router.put('/admin/resume', savePayload(false), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isAutoSaving.value = false;
            syncBaseline(AUTO_SAVE_KEYS);
        },
        onError: (serverErrors) => {
            isAutoSaving.value = false;
            errors.value = { ...serverErrors };
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        },
    });
}

function save(): void {
    if (isSaving.value || isAutoSaving.value) {
        return;
    }

    isSaving.value = true;
    errors.value = {};
    unsavedChanges.suppressNextVisit();

    router.put('/admin/resume', savePayload(true), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            isSaving.value = false;
            errors.value = {};
            syncBaseline(SAVE_KEYS);
        },
        onError: (serverErrors) => {
            isSaving.value = false;
            errors.value = { ...serverErrors };
        },
    });
}

function savePayload(includeAboutTexts: boolean): RequestPayload {
    const payload: RequestPayload = {
        mode: form.mode,
        layout: form.layout,
        custom_resume_file: form.customResumeFile,
    };

    if (includeAboutTexts) {
        payload.about_text = form.aboutTexts;
    }

    return payload;
}

// The saved keys become the new guard baseline — deep-cloned, because
// the layout map is mutated in place and the guard must compare against
// an independent snapshot (the SettingsPage scalar-key precedent).
function syncBaseline(keys: FormKey[]): void {
    for (const key of keys) {
        (baseline as unknown as Record<string, unknown>)[key] = JSON.parse(
            JSON.stringify(form[key]),
        ) as unknown;
    }

    unsavedChanges.rebase(baseline as unknown as Record<string, unknown>);
}

async function uploadFile(file: File): Promise<void> {
    if (isUploading.value) {
        return;
    }

    isUploading.value = true;

    try {
        const body = new FormData();
        body.append('file', file);
        body.append('entity_type', 'about');

        const data = await post<{ data: { path: string; name: string } }>('/api/upload', {
            formData: body,
        });

        form.customResumeFile = data.data.path;
        customFileName.value = data.data.name;
        // The file satisfies the manual-mode `required_if` rule — clear
        // any stale validation error the dropzone shows.
        errors.value = {};
    } catch {
        toast.show(t('admin.toasts.upload_failed'), { variant: 'error' });
    } finally {
        isUploading.value = false;
    }
}

function removeFile(): void {
    form.customResumeFile = null;
    customFileName.value = null;
}

async function regenerate(): Promise<void> {
    if (isRegenerating.value) {
        return;
    }

    isRegenerating.value = true;

    try {
        await post('/api/resume/regenerate');
        toast.show(t('admin.resume.regenerated'));
    } catch {
        toast.show(t('admin.resume.regenerate_failed'), { variant: 'error' });
    } finally {
        isRegenerating.value = false;
    }
}

function cancel(): void {
    router.visit('/admin');
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/resume'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-3xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.resume.title') }}
      </h1>

      <form
        novalidate
        :data-testid="`${props.test_id}-form`"
        @submit.prevent="save"
      >
        <!-- Resume Mode — switching auto-saves. -->
        <fieldset class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary">
          <AdminRadioGroup
            :model-value="form.mode"
            name="resume-mode"
            :label="t('admin.resume.mode')"
            :options="[
              { value: 'auto', label: t('admin.resume.auto') },
              { value: 'manual', label: t('admin.resume.custom_upload') },
            ]"
            :disabled="isSaving || isAutoSaving"
            :test_id="`${props.test_id}-mode`"
            @update:model-value="setMode"
          />
        </fieldset>

        <div
          v-if="form.mode === 'auto'"
          class="mb-6 space-y-6 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
          :data-testid="`${props.test_id}-auto-block`"
        >
          <AdminBaseDropdown
            :model-value="form.layout.template"
            :label="t('admin.resume.template')"
            :options="[
              { value: 'modern', label: t('admin.resume.modern') },
              { value: 'classic', label: t('admin.resume.classic') },
              { value: 'compact', label: t('admin.resume.compact') },
            ]"
            :disabled="isSaving || isAutoSaving"
            :test_id="`${props.test_id}-template`"
            @update:model-value="setTemplate"
          />

          <div>
            <h3 class="mb-3 text-lg font-medium text-slate-600 dark:text-slate-400">
              {{ t('admin.resume.section_order_visibility') }}
            </h3>
            <ResumeSectionEditor
              :model-value="form.layout.sections"
              :labels="SECTION_LABELS"
              :test_id="`${props.test_id}-sections`"
              @update:model-value="setSections"
            />
          </div>

          <!-- About Me text — explicitly saved. -->
          <div class="space-y-4">
            <LocaleTabs
              v-model="activeLocale"
              :locales="locales"
              :test_id="`${props.test_id}-locale-tabs`"
            />
            <AdminBaseTextarea
              :model-value="form.aboutTexts[activeLocale] ?? ''"
              :label="t('admin.resume.about_me_text')"
              :placeholder="t('admin.resume.about_me_fallback')"
              :help="t('admin.resume.about_me_fallback')"
              :max="1000"
              :character_counter="true"
              :disabled="isSaving || isAutoSaving"
              :test_id="`${props.test_id}-about-${activeLocale}`"
              @update:model-value="form.aboutTexts[activeLocale] = $event"
            />
          </div>

          <div
            class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600 dark:bg-slate-900/50 dark:text-slate-400"
            :data-testid="`${props.test_id}-inclusion-summary`"
          >
            <p class="mb-2 text-xs font-bold uppercase tracking-wider text-slate-400">
              {{ t('admin.resume.included_in_resume') }}
            </p>
            <div class="space-y-1">
              <p :data-testid="`${props.test_id}-included-skills`">
                <span class="font-mono font-semibold text-slate-800 dark:text-slate-100">
                  {{ props.included_skills_count }}
                </span>
                {{ t('admin.resume.skills_included') }}
                <AdminBaseButton
                  tag="a"
                  href="/admin/skills"
                  variant="ghost"
                  size="sm"
                  :test_id="`${props.test_id}-manage-skills`"
                >
                  {{ t('admin.resume.manage') }}
                </AdminBaseButton>
              </p>
              <p :data-testid="`${props.test_id}-included-experience`">
                <span class="font-mono font-semibold text-slate-800 dark:text-slate-100">
                  {{ props.included_experience_count }}
                </span>
                {{ t('admin.resume.experience_included') }}
                <AdminBaseButton
                  tag="a"
                  href="/admin/experience"
                  variant="ghost"
                  size="sm"
                  :test_id="`${props.test_id}-manage-experience`"
                >
                  {{ t('admin.resume.manage') }}
                </AdminBaseButton>
              </p>
              <p :data-testid="`${props.test_id}-included-contacts`">
                <span class="font-mono font-semibold text-slate-800 dark:text-slate-100">
                  {{ props.included_contacts_count }}
                </span>
                {{ t('admin.resume.contacts_included') }}
                <AdminBaseButton
                  tag="a"
                  href="/admin/contacts"
                  variant="ghost"
                  size="sm"
                  :test_id="`${props.test_id}-manage-contacts`"
                >
                  {{ t('admin.resume.manage') }}
                </AdminBaseButton>
              </p>
            </div>
          </div>

          <div class="flex flex-wrap gap-3">
            <AdminBaseButton
              type="button"
              variant="secondary"
              icon="heroicons:arrow-path"
              :loading="isRegenerating"
              :test_id="`${props.test_id}-regenerate`"
              @click="regenerate"
            >
              {{ t('admin.resume.regenerate') }}
            </AdminBaseButton>
            <AdminBaseButton
              tag="a"
              :href="downloadPreviewUrl"
              target="_blank"
              variant="ghost"
              icon="heroicons:arrow-top-right-on-square"
              :test_id="`${props.test_id}-download-preview`"
            >
              {{ t('admin.resume.download_preview') }}
            </AdminBaseButton>
          </div>
        </div>

        <!-- Manual mode: the uploaded PDF. -->
        <div
          v-else
          class="mb-6 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
          :data-testid="`${props.test_id}-manual-block`"
        >
          <DropzoneUpload
            :model-value="resumeFile"
            :label="t('admin.resume.resume_file')"
            :accept="'.pdf'"
            :max_size="5242880"
            :help="t('admin.resume.pdf_limit')"
            :error="errors.custom_resume_file ?? null"
            :is_loading="isUploading"
            :test_id="`${props.test_id}-file`"
            @update:model-value="removeFile"
            @upload="uploadFile"
            @remove="removeFile"
          />
          <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500">
            {{ t('admin.resume.manual_mode_ignores_layout') }}
          </p>
        </div>

        <div class="flex items-center justify-end gap-3">
          <AdminBaseButton
            type="submit"
            variant="primary"
            :loading="isSaving"
            :test_id="`${props.test_id}-save-btn`"
          >
            {{ t('common.save') }}
          </AdminBaseButton>
          <AdminBaseButton
            type="button"
            variant="ghost"
            :disabled="isSaving"
            :test_id="`${props.test_id}-cancel-btn`"
            @click="cancel"
          >
            {{ t('common.cancel') }}
          </AdminBaseButton>
        </div>
      </form>
    </section>
  </AdminPageLayout>
</template>
