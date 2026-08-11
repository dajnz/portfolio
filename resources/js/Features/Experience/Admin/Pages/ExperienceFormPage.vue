<!-- ExperienceFormPage (admin-section-ui.md:365-374, admin-section-components.md:1072-1100, experience-form.html) — create/edit form for timeline items: sticky header controls (back link / LocaleTabs / save-cancel per B174), LocaleTabs-bound translatable fields (mandatory position, optional company pair, short description), ProjectSearchCombobox (portfolio project or custom external name), locale-aware date inputs (B174 — no Current checkbox; an empty end date IS the ongoing marker), AchievementList (per-locale descriptions, SortableList reorder + keyboard move up/down), AdminTechSkillPicker tech tags, include-in-resume + published toggles, useUnsavedChanges guard and per-locale validation errors (warned LocaleTabs for missing required translations). POST /admin/experience on create, PUT on edit. -->
<script setup lang="ts">
import type { AchievementRow } from '@experience/Admin/Types/achievement';
import AchievementList from '@experience-admin/AchievementList.vue';
import ProjectSearchCombobox from '@experience-admin/ProjectSearchCombobox.vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseTextarea from '@shared-admin/AdminBaseTextarea.vue';
import AdminDatePicker from '@shared-admin/AdminDatePicker.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
import AdminTechSkillPicker from '@shared-admin/AdminTechSkillPicker.vue';
import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useToast } from '@shared-cross/Composables/useToast';
import { useUnsavedChanges } from '@shared-cross/Composables/useUnsavedChanges';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { localesWithErrors } from '@shared-cross/Utils/localeErrors';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { randomUuid } from '@shared-cross/Utils/randomUuid';
import { computed, reactive, ref, watch } from 'vue';

interface TechTag {
    id: number;
    name: string;
    icon: string;
}

interface ProjectOption {
    id: number;
    title: string;
    slug: string;
}

interface TimelineItemEditProps {
    id: number;
    position: Record<string, string>;
    company_name: Record<string, string>;
    company_country: Record<string, string>;
    project_id: number | null;
    external_project_name: string | null;
    short_description: Record<string, string>;
    started_at: string;
    ended_at: string | null;
    is_published: boolean;
    sort_order: number;
    include_in_resume: boolean;
    achievements: Array<{
        description: Record<string, string | null>;
        project_link: string | null;
    }>;
    tech_tags: TechTag[];
}

interface ExperienceFormValues {
    position: Record<string, string>;
    company_name: Record<string, string>;
    company_country: Record<string, string>;
    short_description: Record<string, string>;
    project_id: number | null;
    external_project_name: string | null;
    started_at: string;
    ended_at: string;
    achievements: AchievementRow[];
    tech_tags: TechTag[];
    include_in_resume: boolean;
    is_published: boolean;
    sort_order: number;
}

const props = withDefaults(
    defineProps<{
        item?: TimelineItemEditProps | null;
        skills?: TechTag[];
        projects?: ProjectOption[];
        test_id?: string;
    }>(),
    {
        item: null,
        skills: () => [],
        projects: () => [],
        test_id: 'experience-form-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();

const activeLocale = ref('en');
const isSaving = ref(false);
const errors = ref<Record<string, string>>({});

const locales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

function emptyLocaleMap(): Record<string, string> {
    return Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, '']));
}

function buildForm(item: TimelineItemEditProps | null | undefined): ExperienceFormValues {
    const descriptionMaps = (item?.achievements ?? []).map(
        (achievement): AchievementRow => ({
            uid: randomUuid(),
            description: Object.fromEntries(
                SUPPORTED_LOCALES.map((code) => [code, achievement.description[code] ?? '']),
            ),
            project_link: achievement.project_link,
        }),
    );

    return {
        position: { ...emptyLocaleMap(), ...(item?.position ?? {}) },
        company_name: { ...emptyLocaleMap(), ...(item?.company_name ?? {}) },
        company_country: { ...emptyLocaleMap(), ...(item?.company_country ?? {}) },
        short_description: { ...emptyLocaleMap(), ...(item?.short_description ?? {}) },
        project_id: item?.project_id ?? null,
        external_project_name: item?.external_project_name ?? null,
        started_at: item?.started_at ?? '',
        ended_at: item?.ended_at ?? '',
        achievements: descriptionMaps,
        tech_tags: [...(item?.tech_tags ?? [])],
        include_in_resume: item?.include_in_resume ?? true,
        is_published: item?.is_published ?? false,
        sort_order: item?.sort_order ?? 0,
    };
}

const form = reactive<ExperienceFormValues>(buildForm(props.item));

// The guard's baseline is a DEEP COPY of the SAME built form — a second
// buildForm() call would regenerate the achievement row `uid`s (client-
// side SortableList keys), making an untouched form look dirty and
// tripping the unsaved-changes prompt on the plain back-link navigation
// (B175). The watch re-baselines both from the same `fresh` object.
const initialSnapshot = JSON.parse(JSON.stringify(form)) as ExperienceFormValues;

// Locales with a missing required translation — `position` and
// `short_description` are required per locale (B174) — their tab gets
// the warning marker before the save round-trip.
const warnedLocales = computed(() =>
    SUPPORTED_LOCALES.filter(
        (code) =>
            (form.position[code] ?? '').trim() === '' ||
            (form.short_description[code] ?? '').trim() === '',
    ),
);

const unsavedChanges = useUnsavedChanges(
    form as unknown as Record<string, unknown>,
    initialSnapshot as unknown as Record<string, unknown>,
);

// Server re-renders refresh the props after every save; rebuild the form
// from the fresh item and re-arm the guard on a new baseline.
watch(
    () => props.item,
    (next) => {
        const fresh = buildForm(next);
        Object.assign(form, fresh);
        Object.assign(initialSnapshot, fresh);
        unsavedChanges.clear();
    },
);

function localeValue(locale: string, record: Record<string, string>): string {
    return record[locale] ?? '';
}

function setLocaleValue(locale: string, record: Record<string, string>, value: string): void {
    record[locale] = value;
}

function fieldError(field: string): string | null {
    return errors.value[`${field}.${activeLocale.value}`] ?? errors.value[field] ?? null;
}

// The first achievements error (e.g. `achievements.0.description` — the
// "description in at least one language" rule) surfaces under the list.
const achievementsError = computed(() => {
    for (const key of Object.keys(errors.value)) {
        if (key.startsWith('achievements.')) {
            return errors.value[key] ?? null;
        }
    }

    return null;
});

function onAchievementsChange(rows: AchievementRow[]): void {
    form.achievements = rows;
}

// Empty rows carry no data (the backend requires a description in at
// least one language and persists only the locales with one) — drop them
// before the submit payload.
function submittableAchievements(): Array<{
    description: Record<string, string>;
    project_link: string | null;
}> {
    return form.achievements
        .filter((row) =>
            SUPPORTED_LOCALES.some((code) => (row.description[code] ?? '').trim() !== ''),
        )
        .map((row) => ({
            description: Object.fromEntries(
                SUPPORTED_LOCALES.map((code) => [code, row.description[code] ?? '']),
            ),
            project_link: row.project_link,
        }));
}

function save(): void {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;
    errors.value = {};

    // The guard must not prompt for the form's own submit.
    unsavedChanges.suppressNextVisit();

    const payload = {
        position: form.position,
        company_name: form.company_name,
        company_country: form.company_country,
        short_description: form.short_description,
        project_id: form.project_id,
        external_project_name: form.external_project_name,
        started_at: form.started_at,
        ended_at: form.ended_at,
        achievements: submittableAchievements(),
        tech_tags: form.tech_tags.map((tag) => tag.id),
        include_in_resume: form.include_in_resume,
        is_published: form.is_published,
        sort_order: form.sort_order,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isSaving.value = false;
            errors.value = {};
            unsavedChanges.clear();
        },
        onError: (serverErrors: Record<string, string>) => {
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
    };

    if (props.item !== null && props.item !== undefined) {
        router.put(`/admin/experience/${props.item.id}`, payload, options);
    } else {
        router.post('/admin/experience', payload, options);
    }
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/experience'"
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
        {{ props.item !== null && props.item !== undefined ? t('admin.experience.edit') : t('admin.experience.add') }}
      </h1>

      <!-- Sticky header controls (B174): back link, locale tabs and
           save/cancel stay visible while scrolling; spread with
           space-between per the user request. -->
      <AdminStickyActionBar :test_id="`${props.test_id}-sticky-bar`">
        <AdminBaseButton
          tag="a"
          href="/admin/experience"
          variant="ghost"
          icon="heroicons:arrow-left"
          icon_position="left"
          :test_id="`${props.test_id}-back-btn`"
        >
          {{ t('admin.experience.back_to_list') }}
        </AdminBaseButton>
        <LocaleTabs
          v-model="activeLocale"
          :locales="locales"
          :warned_locales="warnedLocales"
          :test_id="`${props.test_id}-locale-tabs`"
        />
        <div class="flex items-center gap-3">
          <AdminBaseButton
            type="submit"
            form="experience-form"
            variant="primary"
            :loading="isSaving"
            :test_id="`${props.test_id}-save-btn`"
          >
            {{ t('common.save') }}
          </AdminBaseButton>
        </div>
      </AdminStickyActionBar>

      <form
        id="experience-form"
        class="mt-6 space-y-4 rounded-lg border border-slate-200 bg-white p-6 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary"
        novalidate
        :data-testid="`${props.test_id}-form`"
        @submit.prevent="save"
      >
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
          <AdminBaseInput
            :model-value="localeValue(activeLocale, form.position)"
            :label="t('admin.experience.position')"
            :required="true"
            :error="fieldError('position')"
            :disabled="isSaving"
            :maxlength="200"
            :test_id="`${props.test_id}-position-${activeLocale}`"
            @update:model-value="setLocaleValue(activeLocale, form.position, $event)"
          />
          <AdminBaseInput
            :model-value="localeValue(activeLocale, form.company_name)"
            :label="t('admin.experience.company_name')"
            :error="fieldError('company_name')"
            :disabled="isSaving"
            :maxlength="200"
            :test_id="`${props.test_id}-company-name-${activeLocale}`"
            @update:model-value="setLocaleValue(activeLocale, form.company_name, $event)"
          />
          <AdminBaseInput
            :model-value="localeValue(activeLocale, form.company_country)"
            :label="t('admin.experience.country')"
            :error="fieldError('company_country')"
            :disabled="isSaving"
            :maxlength="100"
            :test_id="`${props.test_id}-country-${activeLocale}`"
            @update:model-value="setLocaleValue(activeLocale, form.company_country, $event)"
          />
          <ProjectSearchCombobox
            :model-value="{
              project_id: form.project_id,
              external_project_name: form.external_project_name,
            }"
            :projects="props.projects"
            :label="t('admin.experience.project')"
            :error="errors.project_id ?? errors.external_project_name ?? null"
            :disabled="isSaving"
            :test_id="`${props.test_id}-project`"
            @update:model-value="
              (value) => {
                form.project_id = value.project_id;
                form.external_project_name = value.external_project_name;
              }
            "
          />
        </div>

        <AdminBaseTextarea
          :model-value="localeValue(activeLocale, form.short_description)"
          :label="t('admin.experience.short_description')"
          :error="fieldError('short_description')"
          :disabled="isSaving"
          :maxlength="500"
          :rows="3"
          :test_id="`${props.test_id}-short-description-${activeLocale}`"
          @update:model-value="setLocaleValue(activeLocale, form.short_description, $event)"
        />

        <div class="grid grid-cols-2 gap-4">
          <AdminDatePicker
            :model-value="form.started_at"
            :label="t('admin.experience.started_at')"
            :required="true"
            :error="errors.started_at ?? null"
            :disabled="isSaving"
            :test_id="`${props.test_id}-started-at`"
            @update:model-value="(value) => (form.started_at = value)"
          />
          <AdminDatePicker
            :model-value="form.ended_at"
            :label="t('admin.experience.ended_at')"
            :error="errors.ended_at ?? null"
            :disabled="isSaving"
            :test_id="`${props.test_id}-ended-at`"
            @update:model-value="(value) => (form.ended_at = value)"
          />
        </div>

        <AchievementList
          :model-value="form.achievements"
          :locale="activeLocale"
          :error="achievementsError"
          :disabled="isSaving"
          :test_id="`${props.test_id}-achievements`"
          @update:model-value="onAchievementsChange"
          @add="
            form.achievements = [
              ...form.achievements,
              { uid: randomUuid(), description: emptyLocaleMap(), project_link: null },
            ]
          "
          @remove="
            (index) =>
              (form.achievements = form.achievements.filter((_, rowIndex) => rowIndex !== index))
          "
        />

        <AdminTechSkillPicker
          :model-value="form.tech_tags"
          :label="t('admin.experience.tech_tags')"
          :error="errors.tech_tags ?? null"
          :disabled="isSaving"
          :test_id="`${props.test_id}-tech-tags`"
          @update:model-value="(tags) => (form.tech_tags = tags)"
        />

        <div class="flex flex-wrap gap-6">
          <AdminBaseCheckbox
            :model-value="form.include_in_resume"
            :label="t('admin.experience.include_in_resume')"
            :disabled="isSaving"
            :test_id="`${props.test_id}-include-resume`"
            @update:model-value="(value) => (form.include_in_resume = Boolean(value))"
          />
          <AdminBaseCheckbox
            :model-value="form.is_published"
            :label="t('admin.experience.published')"
            :disabled="isSaving"
            :test_id="`${props.test_id}-published`"
            @update:model-value="(value) => (form.is_published = Boolean(value))"
          />
        </div>
      </form>
    </section>
  </AdminPageLayout>
</template>
