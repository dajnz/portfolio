<!-- AboutEditPage (admin-section-components.md:766-807, admin-section-ui.md:235-251, about.html) — the about edit form: LocaleTabs-bound translatable fields (name/title/short_pitch per B102), a Photo card (DropzoneUpload + per-locale alt texts) and a Work & location settings card (three two-column rows: location [locale-tab-bound, label carries the [en]/[ru] suffix] + timezone / show location + relocation / work type + availability), plus the resume summary link. PUT /admin/about (id=1 single row). -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseTextarea from '@shared-admin/AdminBaseTextarea.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import DropzoneUpload, { type UploadedFile } from '@shared-admin/DropzoneUpload.vue';
import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { useUnsavedChanges } from '@shared-cross/Composables/useUnsavedChanges';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { localesWithErrors } from '@shared-cross/Utils/localeErrors';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { formatTimezoneLabel } from '@shared-cross/Utils/timezoneLabel';
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';

interface AboutEditProps {
    name?: Record<string, string>;
    title?: Record<string, string>;
    short_pitch?: Record<string, string>;
    location?: Record<string, string>;
    photo?: string | null;
    photo_alt?: Record<string, string>;
    show_location?: boolean;
    timezone?: string;
    availability_status?: string | null;
    work_type?: string;
    relocation_readiness?: boolean;
    resume_mode?: 'auto' | 'manual';
}

interface AboutFormValues {
    name: Record<string, string>;
    title: Record<string, string>;
    short_pitch: Record<string, string>;
    location: Record<string, string>;
    photo: string | null;
    photo_alt: Record<string, string>;
    show_location: boolean;
    timezone: string;
    work_type: string;
    relocation_readiness: boolean;
    availability_status: string;
}

const props = withDefaults(
    defineProps<{
        about?: AboutEditProps | null;
        /** IANA timezone identifiers offered by the timezone dropdown
         *  (AboutController — DateTimeZone::listIdentifiers(), the same
         *  set the `timezone` validation rule accepts). */
        timezones?: string[];
        resume_mode?: 'auto' | 'manual';
        test_id?: string;
    }>(),
    {
        about: null,
        timezones: () => [],
        resume_mode: 'manual',
        test_id: 'about-edit-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();
const { post } = useJsonApi();

const activeLocale = ref('en');
const photoFileName = ref<string | null>(null);
const isUploading = ref(false);
const isSaving = ref(false);
const errors = ref<Record<string, string>>({});

// Sticky-bar "stuck" detection (user request 2026-08-07): while the bar
// is pinned below the header its bottom border + edge fades show; at its
// natural position neither does. rAF-throttled scroll listener.
const actionBarEl = ref<HTMLElement | null>(null);
const isBarStuck = ref(false);
let scrollTicking = false;

function updateBarStuckState(): void {
    scrollTicking = false;
    isBarStuck.value = (actionBarEl.value?.getBoundingClientRect().top ?? 0) <= 65;
}

function onBarScroll(): void {
    if (scrollTicking) {
        return;
    }

    scrollTicking = true;
    requestAnimationFrame(updateBarStuckState);
}

onMounted(() => {
    updateBarStuckState();
    window.addEventListener('scroll', onBarScroll, { passive: true });
});

onBeforeUnmount(() => {
    window.removeEventListener('scroll', onBarScroll);
});

// The per-locale editing tabs only offer the ACTIVE locales (the
// shared `supported_locales` prop — disabled locales are not editable,
// 5.2 rework).
const locales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

// The timezone dropdown's option set — each identifier is labelled with
// its current UTC offset ("Europe/Moscow (UTC+3)") so the admin can pick
// by shift without knowing the IANA table.
const timezoneOptions = computed(() =>
    props.timezones.map((value) => ({ value, label: formatTimezoneLabel(value) })),
);

function buildForm(about: AboutEditProps | null | undefined): AboutFormValues {
    const empty = (): Record<string, string> =>
        Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, '']));

    return {
        name: { ...empty(), ...(about?.name ?? {}) },
        title: { ...empty(), ...(about?.title ?? {}) },
        short_pitch: { ...empty(), ...(about?.short_pitch ?? {}) },
        location: { ...empty(), ...(about?.location ?? {}) },
        photo: about?.photo ?? null,
        photo_alt: { ...(about?.photo_alt ?? {}) },
        show_location: about?.show_location ?? false,
        timezone: about?.timezone ?? 'UTC',
        work_type: about?.work_type ?? 'remote',
        relocation_readiness: about?.relocation_readiness ?? false,
        availability_status: about?.availability_status ?? 'available',
    };
}

const form = reactive<AboutFormValues>(buildForm(props.about));
const initialSnapshot = buildForm(props.about);

// Locales with an incomplete required translation (name/title/short_pitch
// are required per locale) — their tab gets the warning marker so the
// admin sees what is missing before the save round-trip.
const warnedLocales = computed(() =>
    SUPPORTED_LOCALES.filter(
        (code) =>
            (form.name[code] ?? '').trim() === '' ||
            (form.title[code] ?? '').trim() === '' ||
            (form.short_pitch[code] ?? '').trim() === '',
    ),
);
// The guard works on plain-data records; the strongly-typed form is
// structurally a record of serializable values.
const unsavedChanges = useUnsavedChanges(
    form as unknown as Record<string, unknown>,
    initialSnapshot as unknown as Record<string, unknown>,
);

// Server re-renders refresh the props after every save; rebuild the form
// from the fresh about data and re-arm the guard on a new baseline.
watch(
    () => props.about,
    (next) => {
        const fresh = buildForm(next);
        Object.assign(form, fresh);
        Object.assign(initialSnapshot, fresh);
        photoFileName.value = fresh.photo !== null ? (fresh.photo.split('/').pop() ?? null) : null;
        unsavedChanges.clear();
    },
);

const photo = computed<UploadedFile | null>(() =>
    form.photo === null
        ? null
        : {
              file_path: form.photo,
              file_name: photoFileName.value ?? form.photo.split('/').pop() ?? form.photo,
          },
);

function fieldError(field: string): string | null {
    return errors.value[`${field}.${activeLocale.value}`] ?? errors.value[field] ?? null;
}

function localeValue(locale: string, value: Record<string, string>): string {
    return value[locale] ?? '';
}

function setLocaleValue(locale: string, record: Record<string, string>, value: string): void {
    record[locale] = value;
}

async function uploadPhoto(file: File): Promise<void> {
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
        const uploaded = data.data;

        form.photo = uploaded.path;
        photoFileName.value = uploaded.name;
    } catch {
        toast.show(t('admin.toasts.upload_failed'), { variant: 'error' });
    } finally {
        isUploading.value = false;
    }
}

function removePhoto(): void {
    form.photo = null;
    form.photo_alt = {};
    photoFileName.value = null;
}

function save(): void {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;
    errors.value = {};

    // The guard must not prompt for the form's own submit.
    unsavedChanges.suppressNextVisit();

    router.put(
        '/admin/about',
        {
            name: form.name,
            title: form.title,
            short_pitch: form.short_pitch,
            location: form.location,
            photo: form.photo,
            photo_alt: form.photo_alt,
            show_location: form.show_location,
            timezone: form.timezone,
            work_type: form.work_type,
            relocation_readiness: form.relocation_readiness,
            availability_status: form.availability_status,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                errors.value = {};
                unsavedChanges.clear();
            },
            onError: (serverErrors) => {
                isSaving.value = false;
                errors.value = { ...serverErrors };

                // Per-locale validation errors are only visible under the
                // active tab's input — surface the missing translations as
                // toasts so the save never fails silently.
                for (const locale of localesWithErrors(serverErrors)) {
                    toast.show(
                        t('admin.toasts.translation_incomplete', {
                            locale: t(`admin.locales.${locale}`),
                        }),
                        { variant: 'error' },
                    );
                }
            },
        },
    );
}

function cancel(): void {
    router.visit('/admin');
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/about'"
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
        {{ t('admin.about.title') }}
      </h1>

      <form
        class="mt-6"
        novalidate
        :data-testid="`${props.test_id}-form`"
        @submit.prevent="save"
      >
        <!-- Sticky glass action bar (user request 2026-08-07): locale tabs
             + save/cancel always available while scrolling. Sticks a few
             px below the fixed admin header (h-16); the negative margins
             span the main column's padding. The bottom border and the
             edge fades appear only while stuck. -->
        <div
          ref="actionBarEl"
          class="sticky top-[65px] z-sticky -mx-4 mb-6 flex flex-wrap items-center justify-between gap-3 bg-slate-50 px-4 py-3 dark:bg-surface-dark sm:-mx-6 sm:px-6 lg:-mx-8 lg:px-8"
          :class="[
            isBarStuck
              ? 'border-b border-slate-200/70 dark:border-slate-700/70 before:pointer-events-none before:absolute before:inset-y-0 before:left-0 before:w-5 before:bg-gradient-to-r before:from-transparent before:to-slate-50 dark:before:to-surface-dark after:pointer-events-none after:absolute after:inset-y-0 after:right-0 after:w-5 after:bg-gradient-to-l after:from-transparent after:to-slate-50 dark:after:to-surface-dark'
              : '',
          ]"
          :data-testid="`${props.test_id}-action-bar`"
        >
          <LocaleTabs
            v-model="activeLocale"
            :locales="locales"
            :warned_locales="warnedLocales"
            :test_id="`${props.test_id}-locale-tabs`"
          />
          <div class="flex items-center gap-3">
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
        </div>

        <!-- Translatable fields (locale-tabbed) -->
        <div class="mb-6 space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary">
          <AdminBaseInput
            :model-value="localeValue(activeLocale, form.name)"
            :label="t('admin.about.name')"
            :required="true"
            :error="fieldError('name')"
            :disabled="isSaving"
            :maxlength="255"
            :test_id="`${props.test_id}-name-${activeLocale}`"
            @update:model-value="setLocaleValue(activeLocale, form.name, $event)"
          />
          <AdminBaseInput
            :model-value="localeValue(activeLocale, form.title)"
            :label="t('admin.about.title_label')"
            :required="true"
            :error="fieldError('title')"
            :disabled="isSaving"
            :maxlength="200"
            :test_id="`${props.test_id}-title-${activeLocale}`"
            @update:model-value="setLocaleValue(activeLocale, form.title, $event)"
          />
          <AdminBaseTextarea
            :model-value="localeValue(activeLocale, form.short_pitch)"
            :label="t('admin.about.short_pitch')"
            :required="true"
            :error="fieldError('short_pitch')"
            :disabled="isSaving"
            :maxlength="500"
            :rows="3"
            :test_id="`${props.test_id}-short-pitch-${activeLocale}`"
            @update:model-value="setLocaleValue(activeLocale, form.short_pitch, $event)"
          />
        </div>

        <!-- Photo (shared — outside the tabs): the per-locale Alt Text
             inputs render in the dropzone's slot. -->
        <div class="mb-6 space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary">
          <DropzoneUpload
            :model-value="photo"
            :label="t('admin.about.photo')"
            :is_loading="isUploading"
            :test_id="`${props.test_id}-photo`"
            @update:model-value="form.photo = $event?.file_path ?? null"
            @upload="uploadPhoto"
            @remove="removePhoto"
          >
            <div
              v-for="locale in SUPPORTED_LOCALES"
              :key="`alt-${locale}`"
              class="w-full"
            >
              <AdminBaseInput
                :model-value="localeValue(locale, form.photo_alt)"
                :label="`${t('admin.about.alt_text')} [${locale}]`"
                :error="fieldError('photo_alt')"
                :disabled="isSaving"
                :maxlength="255"
                :test_id="`${props.test_id}-photo-alt-${locale}`"
                @update:model-value="setLocaleValue(locale, form.photo_alt, $event)"
              />
            </div>
          </DropzoneUpload>
        </div>

        <!-- Work & location settings (shared — outside the tabs; two
             columns on sm+). Location keeps per-locale values: the input
             and the [en]/[ru] suffix in its label follow the active
             locale tab (unlike the rest of this card, which is shared).
             Rows: location + timezone / show location + relocation /
             work type + availability. -->
        <div class="mb-6 space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary">
          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AdminBaseInput
              :model-value="localeValue(activeLocale, form.location)"
              :label="`${t('admin.about.location')} [${activeLocale}]`"
              :error="fieldError('location')"
              :disabled="isSaving"
              :maxlength="100"
              :test_id="`${props.test_id}-location-${activeLocale}`"
              @update:model-value="setLocaleValue(activeLocale, form.location, $event)"
            />
            <AdminBaseDropdown
              v-model="form.timezone"
              :label="t('admin.about.timezone')"
              :placeholder="t('admin.about.timezone_placeholder')"
              :options="timezoneOptions"
              searchable
              :error="errors.timezone ?? null"
              :disabled="isSaving"
              :test_id="`${props.test_id}-timezone`"
            />
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AdminBaseCheckbox
              v-model="form.show_location"
              :label="t('admin.about.show_location')"
              :disabled="isSaving"
              :test_id="`${props.test_id}-show-location`"
            />
            <AdminBaseCheckbox
              v-model="form.relocation_readiness"
              :label="t('admin.about.relocation')"
              :disabled="isSaving"
              :test_id="`${props.test_id}-relocation`"
            />
          </div>

          <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <AdminBaseDropdown
              v-model="form.work_type"
              :label="t('admin.about.work_type')"
              :options="[
                { value: 'remote', label: t('landing.work_type.remote') },
                { value: 'hybrid', label: t('landing.work_type.hybrid') },
                { value: 'office', label: t('landing.work_type.office') },
              ]"
              :error="errors.work_type ?? null"
              :disabled="isSaving"
              :test_id="`${props.test_id}-work-type`"
            />
            <AdminBaseDropdown
              v-model="form.availability_status"
              :label="t('admin.about.availability')"
              :options="[
                { value: 'available', label: t('admin.about.available') },
                { value: 'limited', label: t('admin.about.limited') },
                { value: 'busy', label: t('admin.about.busy') },
              ]"
              :error="errors.availability_status ?? null"
              :disabled="isSaving"
              :test_id="`${props.test_id}-availability`"
            />
          </div>
        </div>

        <!-- Resume summary line — the mode label from the Resume feature
             (resume_mode prop, step 10.1; admin-section-components.md:793). -->
        <div class="mb-6 flex items-center justify-between rounded-lg border border-slate-200 bg-white p-4 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary">
          <p class="text-sm text-slate-600 dark:text-slate-400">
            {{ t('admin.nav.resume') }}
            <span
              class="font-semibold text-slate-800 dark:text-slate-100"
              :data-testid="`${props.test_id}-resume-mode`"
            >
              {{ t(`admin.about.resume_mode_${props.resume_mode ?? 'manual'}`) }}
            </span>
            <span class="text-xs text-slate-400 dark:text-slate-500">
              {{ t('admin.about.resume_managed_on_settings') }}
            </span>
          </p>
          <AdminBaseButton
            tag="a"
            href="/admin/resume"
            variant="ghost"
            size="sm"
            :test_id="`${props.test_id}-configure-resume`"
          >
            {{ t('admin.about.configure_resume') }}
          </AdminBaseButton>
        </div>
      </form>
    </section>
  </AdminPageLayout>
</template>
