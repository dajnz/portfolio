<!-- BlogFormMetaSidebar — the blog form's right panel (the two-panel
     layout's meta sidebar): the locale-tabbed Title / Short Description,
     the shared Slug (the auto-generation hint), the Lead Image dropzone
     with per-locale alt text, the unified tag picker, the Related
     Articles multi-select, the Published toggle and the
     read-only published_at block. A presentational component: the fields
     bind their model values as props and emit raw updates; the page
     orchestrator (BlogFormPage + useArticleForm) owns the state, the
     slug generation and the uploads. -->
<script setup lang="ts">
import type { ArticleOption } from '@blog/Admin/Types/article';
import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseTextarea from '@shared-admin/AdminBaseTextarea.vue';
import AdminBaseToggle from '@shared-admin/AdminBaseToggle.vue';
import AdminBlogTagPicker from '@shared-admin/AdminBlogTagPicker.vue';
import DropzoneUpload, { type UploadedFile } from '@shared-admin/DropzoneUpload.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';

/** The unified tag-picker pill — the `{id, name, type}` wire shape. */
export interface UnifiedTagValue {
    id: number;
    name: string;
    type: 'tag' | 'skill';
}

const props = withDefaults(
    defineProps<{
        title: Record<string, string>;
        slug: string;
        short_description: Record<string, string>;
        lead_image: string | null;
        lead_image_alt: Record<string, string>;
        tags: UnifiedTagValue[];
        related_ids: number[];
        articles?: ArticleOption[];
        related_tooltip?: string;
        is_published: boolean;
        published_at?: string | null;
        errors: Record<string, string>;
        active_locale: string;
        is_saving: boolean;
        is_uploading: boolean;
        test_id?: string;
    }>(),
    {
        articles: () => [],
        related_tooltip: undefined,
        published_at: null,
        test_id: 'blog-form-page',
    },
);

const emit = defineEmits<{
    'update:title': [locale: string, value: string];
    'update:slug': [value: string];
    'update:short_description': [locale: string, value: string];
    'update:lead_image': [value: string | null];
    'update:lead_image_alt': [locale: string, value: string];
    'update:tags': [tags: UnifiedTagValue[]];
    'update:related': [ids: number[]];
    'update:published': [value: boolean];
    'upload-lead': [file: File];
    'remove-lead': [];
}>();

const { t } = useI18n();

/** The DropzoneUpload model value from the stored RustFS path. */
function leadFileFor(): UploadedFile | null {
    if (props.lead_image === null) {
        return null;
    }

    return {
        file_path: props.lead_image,
        file_name: props.lead_image.split('/').pop() ?? props.lead_image,
    };
}

function formatPublishedAt(value: string | null): string {
    if (value === null || value === '') {
        return '—';
    }

    return `${new Date(value).toISOString().replace('T', ' ').slice(0, 16)} UTC`;
}

function onLeadImageChange(value: UploadedFile | null): void {
    emit('update:lead_image', value?.file_path ?? null);
}

function onRelatedArticlesChange(value: string | string[]): void {
    emit(
        'update:related',
        (Array.isArray(value) ? value : [value]).filter((id) => id !== '').map(Number),
    );
}

// The array-field validation errors arrive keyed per item
// (`related_articles.0`, `tags.2`, `skill_tags.0` — Laravel's dotted
// index keys), while the field's `error` prop binds the bare key.
// Fall back to the first `field.N` entry so nested errors are
// actually visible.
function nestedFieldError(field: string): string | null {
    return (
        props.errors[field] ??
        Object.entries(props.errors).find(([key]) => key.startsWith(`${field}.`))?.[1] ??
        null
    );
}
</script>

<template>
  <AdminBaseCard
    class="space-y-4"
    :test_id="`${props.test_id}-sidebar`"
  >
    <AdminBaseInput
      :model-value="props.title[props.active_locale] ?? ''"
      :label="`${t('common.title')} [${props.active_locale}]`"
      required
      :maxlength="200"
      :error="props.errors[`title.${props.active_locale}`] ?? props.errors.title ?? null"
      :disabled="props.is_saving"
      :test_id="`${props.test_id}-title-${props.active_locale}`"
      @update:model-value="(value) => emit('update:title', props.active_locale, value)"
    />
    <AdminBaseInput
      :model-value="props.slug"
      :label="t('admin.blog.slug')"
      required
      :maxlength="200"
      :error="props.errors.slug ?? null"
      :help="t('admin.blog.slug_hint')"
      :disabled="props.is_saving"
      :test_id="`${props.test_id}-slug`"
      @update:model-value="(value) => emit('update:slug', value)"
    />
    <AdminBaseTextarea
      :model-value="props.short_description[props.active_locale] ?? ''"
      :label="`${t('admin.blog.short_description')} [${props.active_locale}]`"
      required
      :maxlength="300"
      :error="props.errors[`short_description.${props.active_locale}`] ?? props.errors.short_description ?? null"
      :disabled="props.is_saving"
      :test_id="`${props.test_id}-short-description-${props.active_locale}`"
      @update:model-value="(value) => emit('update:short_description', props.active_locale, value)"
    />
    <DropzoneUpload
      :model-value="leadFileFor()"
      :label="t('admin.blog.lead_image')"
      accept="image/*"
      :is_loading="props.is_uploading"
      :error="props.errors.lead_image ?? null"
      :disabled="props.is_saving"
      :test_id="`${props.test_id}-lead-dropzone`"
      @update:model-value="onLeadImageChange"
      @upload="(file: File) => emit('upload-lead', file)"
      @remove="emit('remove-lead')"
    >
      <div
        v-for="code in SUPPORTED_LOCALES"
        :key="`lead-alt-${code}`"
        class="w-full"
      >
        <AdminBaseInput
          class="pt-3"
          :model-value="props.lead_image_alt[code] ?? ''"
          :label="`${t('admin.blog.alt_text')} [${code}]`"
          :maxlength="500"
          :error="props.errors[`lead_image_alt.${code}`] ?? null"
          :disabled="props.is_saving"
          :test_id="`${props.test_id}-lead-alt-${code}`"
          @update:model-value="(value) => emit('update:lead_image_alt', code, value)"
        />
      </div>
    </DropzoneUpload>
    <AdminBlogTagPicker
      :model-value="props.tags"
      :label="t('admin.blog.tags')"
      :error="
        nestedFieldError('tags') ?? nestedFieldError('skill_tags')
      "
      :disabled="props.is_saving"
      :test_id="`${props.test_id}-tags`"
      @update:model-value="(tags) => emit('update:tags', tags)"
    />
    <AdminBaseDropdown
      :model-value="props.related_ids.map(String)"
      :options="props.articles.map((article) => ({ value: String(article.id), label: article.title }))"
      multiple
      searchable
      :label="t('admin.blog.related_articles')"
      :title="props.related_tooltip"
      :error="nestedFieldError('related_articles')"
      :disabled="props.is_saving"
      :test_id="`${props.test_id}-related`"
      @update:model-value="onRelatedArticlesChange"
    />
    <p class="-mt-2 text-xs text-slate-400 dark:text-slate-500">
      {{ t('admin.blog.related_hint') }}
    </p>
    <div class="flex flex-wrap gap-4">
      <AdminBaseToggle
        :model-value="props.is_published"
        :label="t('admin.blog.published')"
        :disabled="props.is_saving"
        :test_id="`${props.test_id}-published`"
        @update:model-value="(checked) => emit('update:published', checked)"
      />
    </div>
    <div class="rounded-lg bg-slate-50 px-3 py-2.5 dark:bg-slate-900/50">
      <p class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
        {{ t('admin.blog.published_at') }}:
        <span
          class="font-mono font-semibold text-slate-600 dark:text-slate-300"
          :data-testid="`${props.test_id}-published-at`"
        >{{ formatPublishedAt(props.published_at ?? null) }}</span>
      </p>
      <p class="mt-0.5 text-xs text-slate-400 dark:text-slate-500">
        {{ t('admin.blog.published_at_hint') }}
      </p>
    </div>
  </AdminBaseCard>
</template>
