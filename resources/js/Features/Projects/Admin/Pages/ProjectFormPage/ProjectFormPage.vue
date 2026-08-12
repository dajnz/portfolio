<!-- ProjectFormPage (admin-section-ui.md:348-359, admin-section-components.md:995-1038, project-form.html) — the create/edit project form: the page orchestrator holding the form state, the locale handling, the slug auto-generation and the save round-trip, composing six section cards (Content / Basic / Media / Meta / Relations / Engineering Details) that each own their template and emit value updates. POST /admin/projects on create, PUT on edit; the per-locale maps follow the LocaleTabs convention; empty screenshot/detail rows are dropped before submit (the backend's `required_with` rules); the slug auto-generates from the EN title on create until manually edited (A3 keeps the manual override; the backend regenerates on save when the submitted slug still equals the stored one); Preview opens the signed URL; useUnsavedChanges guards the whole form. -->
<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import type { EngineeringDetailRow } from '@projects-admin/EngineeringDetailItem.vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
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

import ProjectBasicSection from './ProjectBasicSection.vue';
import ProjectContentSection from './ProjectContentSection.vue';
import ProjectEngineeringSection from './ProjectEngineeringSection.vue';
import ProjectMediaSection, { type ScreenshotRow } from './ProjectMediaSection.vue';
import ProjectMetaSection from './ProjectMetaSection.vue';
import ProjectRelationsSection, {
    type ArticleOption,
    type TechTag,
} from './ProjectRelationsSection.vue';

interface ProjectEditProps {
    id: number;
    slug: string;
    title: Record<string, string>;
    brief_description: Record<string, string>;
    full_description: Record<string, string | null>;
    problem: Record<string, string | null>;
    solution: Record<string, string | null>;
    preview_image: string | null;
    preview_image_alt: Record<string, string | null>;
    github_url: string | null;
    live_demo_url: string | null;
    is_featured: boolean;
    featured_sort_order: number | null;
    is_published: boolean;
    tech_writeup_article_id: number | null;
    tech_tags: TechTag[];
    screenshots: Array<{
        id?: number;
        image_path: string;
        alt_text: Record<string, string | null>;
        title: Record<string, string | null>;
        description: Record<string, string | null>;
    }>;
    detail_items: Array<{
        id?: number;
        icon: string | null;
        title: Record<string, string | null>;
        description: Record<string, string | null>;
    }>;
}

interface ProjectFormValues {
    slug: string;
    title: Record<string, string>;
    brief_description: Record<string, string>;
    full_description: Record<string, string>;
    problem: Record<string, string>;
    solution: Record<string, string>;
    preview_image: string | null;
    preview_image_alt: Record<string, string>;
    github_url: string;
    live_demo_url: string;
    is_featured: boolean;
    is_published: boolean;
    featured_sort_order: string;
    tech_writeup_article_id: number | null;
    tech_tags: TechTag[];
    screenshots: ScreenshotRow[];
    detail_items: EngineeringDetailRow[];
}

const props = withDefaults(
    defineProps<{
        project?: ProjectEditProps | null;
        skills?: TechTag[];
        articles?: ArticleOption[];
        preview_url?: string | null;
        test_id?: string;
    }>(),
    {
        project: null,
        skills: () => [],
        articles: () => [],
        preview_url: null,
        test_id: 'project-form-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();

const activeLocale = ref('en');
const isSaving = ref(false);
const errors = ref<Record<string, string>>({});
const slugTouched = ref(props.project !== null && props.project !== undefined);

const locales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

const isEdit = computed(() => props.project !== null && props.project !== undefined);

function emptyLocaleMap(): Record<string, string> {
    return Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, '']));
}

function stringMap(values: Record<string, string | null> | undefined): Record<string, string> {
    const normalized = { ...emptyLocaleMap() };

    for (const [locale, value] of Object.entries(values ?? {})) {
        normalized[locale] = value ?? '';
    }

    return normalized;
}

function buildScreenshots(screenshots: ProjectEditProps['screenshots']): ScreenshotRow[] {
    return screenshots.map((screenshot) => ({
        uid: randomUuid(),
        id: screenshot.id,
        image: screenshot.image_path,
        alt_text: stringMap(screenshot.alt_text),
        title: stringMap(screenshot.title),
        description: stringMap(screenshot.description),
    }));
}

function buildDetailItems(detailItems: ProjectEditProps['detail_items']): EngineeringDetailRow[] {
    return detailItems.map((item) => ({
        uid: randomUuid(),
        id: item.id,
        icon: item.icon,
        title: stringMap(item.title),
        description: stringMap(item.description),
    }));
}

function buildForm(project: ProjectEditProps | null | undefined): ProjectFormValues {
    return {
        slug: project?.slug ?? '',
        title: { ...emptyLocaleMap(), ...(project?.title ?? {}) },
        brief_description: { ...emptyLocaleMap(), ...(project?.brief_description ?? {}) },
        full_description: stringMap(project?.full_description),
        problem: stringMap(project?.problem),
        solution: stringMap(project?.solution),
        preview_image: project?.preview_image ?? null,
        preview_image_alt: stringMap(project?.preview_image_alt),
        github_url: project?.github_url ?? '',
        live_demo_url: project?.live_demo_url ?? '',
        is_featured: project?.is_featured ?? false,
        is_published: project?.is_published ?? false,
        featured_sort_order:
            project?.featured_sort_order === null || project?.featured_sort_order === undefined
                ? ''
                : String(project.featured_sort_order),
        tech_writeup_article_id: project?.tech_writeup_article_id ?? null,
        tech_tags: (project?.tech_tags ?? []).map((tag) => ({
            ...tag,
            icon: tag.icon ?? '',
        })),
        screenshots: buildScreenshots(project?.screenshots ?? []),
        detail_items: buildDetailItems(project?.detail_items ?? []),
    };
}

const form = reactive<ProjectFormValues>(buildForm(props.project));

// The guard's baseline is a DEEP COPY of the SAME built form — a second
// buildForm() call would regenerate the screenshot/detail row `uid`s,
// making an untouched form look dirty (the Experience form precedent,
// B175).
const initialSnapshot = JSON.parse(JSON.stringify(form)) as ProjectFormValues;

// Locales with a missing required translation — their tab gets the
// warning marker before the save round-trip. Beyond the mandatory
// `title` + `brief_description`, two dynamic policies (user request
// 2026-08-12) mark a locale incomplete:
//  1. the Markdown trio (full description / problem / solution) is
//     all-empty or all-filled ACROSS locales, per field — a field
//     filled in one language must be filled in every language (the
//     OTHER locales get the warning, not the filled one);
//  2. every submittable engineering detail needs the locale's title +
//     description (the backend's `required_with:detail_items` rule).
const MARKDOWN_FIELDS = ['full_description', 'problem', 'solution'] as const;

const warnedLocales = computed(() =>
    SUPPORTED_LOCALES.filter((code) => {
        const titleMissing = (form.title[code] ?? '').trim() === '';
        const briefMissing = (form.brief_description[code] ?? '').trim() === '';

        // A locale is incomplete when a trio field has content in SOME
        // locale but is empty in this one.
        const markdownIncomplete = MARKDOWN_FIELDS.some(
            (field) =>
                SUPPORTED_LOCALES.some((locale) => (form[field][locale] ?? '').trim() !== '') &&
                (form[field][code] ?? '').trim() === '',
        );

        const detailsIncomplete = submittableDetailItems().some(
            (item) =>
                (item.title[code] ?? '').trim() === '' ||
                (item.description[code] ?? '').trim() === '',
        );

        return titleMissing || briefMissing || markdownIncomplete || detailsIncomplete;
    }),
);

const unsavedChanges = useUnsavedChanges(
    form as unknown as Record<string, unknown>,
    initialSnapshot as unknown as Record<string, unknown>,
);

// Server re-renders refresh the props after every save; rebuild the form
// from the fresh project and re-arm the guard on a new baseline.
watch(
    () => props.project,
    (next) => {
        const fresh = buildForm(next);
        Object.assign(form, fresh);
        Object.assign(initialSnapshot, fresh);
        unsavedChanges.clear();
    },
);

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function onTitleChange(locale: string, value: string): void {
    form.title[locale] = value;

    // Auto-generate the slug from the EN title while the field is still
    // untouched (create mode). Once edited, the manual override wins
    // (A3 — the backend keeps a submitted slug that differs from the
    // stored one).
    if (locale === 'en' && !slugTouched.value) {
        form.slug = slugify(value);
    }
}

function setLocaleValue(locale: string, record: Record<string, string>, value: string): void {
    record[locale] = value;
}

function onSlugChange(value: string): void {
    form.slug = slugify(value);
    slugTouched.value = true;
}

function submittableScreenshots(): Array<{
    id?: number;
    image: string;
    alt_text: Record<string, string>;
    title: Record<string, string>;
    description: Record<string, string>;
}> {
    return form.screenshots
        .filter((row) => row.image !== null && row.image !== '')
        .map((row) => ({
            ...(row.id !== undefined ? { id: row.id } : {}),
            image: row.image as string,
            alt_text: row.alt_text,
            title: row.title,
            description: row.description,
        }));
}

function submittableDetailItems(): Array<{
    id?: number;
    icon: string;
    title: Record<string, string>;
    description: Record<string, string>;
}> {
    return form.detail_items
        .filter(
            (row) =>
                row.icon !== null &&
                row.icon !== '' &&
                SUPPORTED_LOCALES.some((code) => (row.title[code] ?? '').trim() !== '') &&
                SUPPORTED_LOCALES.some((code) => (row.description[code] ?? '').trim() !== ''),
        )
        .map((row) => ({
            ...(row.id !== undefined ? { id: row.id } : {}),
            icon: row.icon as string,
            title: row.title,
            description: row.description,
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
        slug: form.slug,
        title: form.title,
        brief_description: form.brief_description,
        full_description: form.full_description,
        problem: form.problem,
        solution: form.solution,
        preview_image: form.preview_image,
        preview_image_alt: form.preview_image_alt,
        github_url: form.github_url === '' ? null : form.github_url,
        live_demo_url: form.live_demo_url === '' ? null : form.live_demo_url,
        is_featured: form.is_featured,
        is_published: form.is_published,
        featured_sort_order:
            form.featured_sort_order === '' ? null : Number(form.featured_sort_order),
        tech_writeup_article_id: form.tech_writeup_article_id,
        tech_tags: form.tech_tags.map((tag) => tag.id),
        screenshots: submittableScreenshots(),
        detail_items: submittableDetailItems(),
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

    if (isEdit.value) {
        router.put(`/admin/projects/${props.project?.id}`, payload, options);
    } else {
        router.post('/admin/projects', payload, options);
    }
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/projects'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-4xl"
      :data-testid="props.test_id"
    >
      <!-- Sticky header controls (user request 2026-08-12): back link /
           locale tabs / preview + save, spread with space-between —
           the preview/save group is a single distributed item (its
           internal gap stays 3). -->
      <AdminStickyActionBar :test_id="`${props.test_id}-sticky-bar`">
        <AdminBaseButton
          tag="a"
          href="/admin/projects"
          variant="ghost"
          icon="heroicons:arrow-left"
          icon_position="left"
          :test_id="`${props.test_id}-back-btn`"
        >
          {{ t('admin.projects.back_to_list') }}
        </AdminBaseButton>
        <LocaleTabs
          v-model="activeLocale"
          :locales="locales"
          :warned_locales="warnedLocales"
          :test_id="`${props.test_id}-locale-tabs`"
        />
        <div class="flex items-center gap-3">
          <AdminBaseButton
            v-if="props.preview_url !== null && props.preview_url !== undefined"
            tag="a"
            :href="props.preview_url"
            target="_blank"
            variant="secondary"
            icon="heroicons:arrow-top-right-on-square"
            icon_position="left"
            :test_id="`${props.test_id}-preview-btn`"
          >
            {{ t('admin.projects.preview') }}
          </AdminBaseButton>
          <AdminBaseButton
            type="submit"
            form="project-form"
            variant="primary"
            :loading="isSaving"
            :test_id="`${props.test_id}-save-btn`"
          >
            {{ t('common.save') }}
          </AdminBaseButton>
        </div>
      </AdminStickyActionBar>

      <h1 class="mb-6 mt-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{
          isEdit ? t('admin.projects.edit') : t('admin.projects.add')
        }}
      </h1>

      <form
        id="project-form"
        class="space-y-6"
        novalidate
        :data-testid="`${props.test_id}-form`"
        @submit.prevent="save"
      >
        <ProjectContentSection
          :title="form.title"
          :brief_description="form.brief_description"
          :full_description="form.full_description"
          :problem="form.problem"
          :solution="form.solution"
          :locale="activeLocale"
          :errors="errors"
          :disabled="isSaving"
          :test_id="props.test_id"
          @update:title="onTitleChange"
          @update:brief_description="(locale, value) => setLocaleValue(locale, form.brief_description, value)"
          @update:full_description="(locale, value) => setLocaleValue(locale, form.full_description, value)"
          @update:problem="(locale, value) => setLocaleValue(locale, form.problem, value)"
          @update:solution="(locale, value) => setLocaleValue(locale, form.solution, value)"
        />

        <ProjectBasicSection
          :slug="form.slug"
          :slug_error="errors.slug ?? null"
          :disabled="isSaving"
          :test_id="props.test_id"
          @update:slug="onSlugChange"
        />

        <ProjectMediaSection
          :preview_image="form.preview_image"
          :preview_image_alt="form.preview_image_alt"
          :screenshots="form.screenshots"
          :locale="activeLocale"
          :errors="errors"
          :disabled="isSaving"
          :test_id="props.test_id"
          @update:preview_image="(path) => (form.preview_image = path)"
          @update:preview_image_alt="(locale, value) => setLocaleValue(locale, form.preview_image_alt, value)"
          @update:screenshots="(rows) => (form.screenshots = rows)"
          @remove:preview="() => {
            form.preview_image = null;
            form.preview_image_alt = {};
          }"
        />

        <ProjectMetaSection
          :github_url="form.github_url"
          :live_demo_url="form.live_demo_url"
          :is_featured="form.is_featured"
          :featured_sort_order="form.featured_sort_order"
          :is_published="form.is_published"
          :errors="errors"
          :disabled="isSaving"
          :test_id="props.test_id"
          @update:github_url="(value) => (form.github_url = value)"
          @update:live_demo_url="(value) => (form.live_demo_url = value)"
          @update:is_featured="(value) => (form.is_featured = value)"
          @update:featured_sort_order="(value) => (form.featured_sort_order = value)"
          @update:is_published="(value) => (form.is_published = value)"
        />

        <ProjectRelationsSection
          :tech_tags="form.tech_tags"
          :tech_writeup_article_id="form.tech_writeup_article_id"
          :articles="props.articles"
          :errors="errors"
          :disabled="isSaving"
          :test_id="props.test_id"
          @update:tech_tags="(tags) => (form.tech_tags = tags)"
          @update:tech_writeup_article_id="(id) => (form.tech_writeup_article_id = id)"
        />

        <ProjectEngineeringSection
          :detail_items="form.detail_items"
          :locale="activeLocale"
          :errors="errors"
          :disabled="isSaving"
          :test_id="props.test_id"
          @update:detail_items="(rows) => (form.detail_items = rows)"
        />
      </form>
    </section>
  </AdminPageLayout>
</template>
