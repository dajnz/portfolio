<!-- BlogFormPage — the create/edit article form: the complex component's ORCHESTRATOR: the two-panel layout (the TextEditor left, the BlogFormMetaSidebar right), the page wiring of the `useArticleForm` composable (form state, locale handling, slug auto-generation, save round-trip, uploads). POST /admin/blog on create, PUT on edit; the per-locale maps follow the LocaleTabs convention; the unified tag picker splits into the backend's `tags` (article-tag ids) + `skill_tags` (skill ids) fields; the Related Articles multi-select binds `related_article_ids` (published-only options); Preview opens the signed URL; the editor's image button uploads through /api/upload and inserts the /assets/ URL at the cursor (TextEditor.insertImage); useUnsavedChanges guards the whole form. -->
<script setup lang="ts">
import type { ArticleEditData, ArticleOption, TagData } from '@blog/Admin/Types/article';
import { usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import TextEditor, { type TextEditorExposed } from '@shared-admin/TextEditor/TextEditor.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, ref } from 'vue';

import BlogFormMetaSidebar from './BlogFormMetaSidebar.vue';
import { useArticleForm } from './useArticleForm';

const props = withDefaults(
    defineProps<{
        article?: ArticleEditData | null;
        tags?: TagData[];
        articles?: ArticleOption[];
        preview_url?: string | null;
        test_id?: string;
    }>(),
    {
        article: null,
        tags: () => [],
        articles: () => [],
        preview_url: null,
        test_id: 'blog-form-page',
    },
);

const { t } = useI18n();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();

const activeLocale = ref('en');
const editorRef = ref<TextEditorExposed | null>(null);

// The editor reaches almost to the bottom screen edge (the sticky bar +
// the page title above it, the usual 24-40 px page gap below — measured:
// the content area sits ~19.5rem below the viewport top); the TextEditor
// applies it as the WYSIWYG baseline and the raw view's fixed height.
const EDITOR_MIN_HEIGHT = 'calc(100vh - 19.5rem)';

const {
    form,
    errors,
    isSaving,
    isUploadingImage,
    isEdit,
    warnedLocales,
    fieldError,
    onTitleChange,
    onSlugChange,
    setLocaleValue,
    uploadLeadImage,
    removeLeadImage,
    onEditorImageUpload,
    save,
} = useArticleForm({
    article: () => props.article,
    activeLocale: () => activeLocale.value,
    editorRef,
});

const locales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

// The Related Articles dropdown tooltip: the full alphabetical list of
// the linked article names (the sidebar is too narrow for the truncated
// trigger label; newlines render as line breaks in native tooltips).
// Built from the article's own related rows (`ArticleEditData.related`)
// — the picker options only cover PUBLISHED articles, while
// linked-but-unpublished ones are still linked and must be listed.
const relatedTooltip = computed(() =>
    (props.article?.related ?? [])
        .map((article) => article.title)
        .sort((a, b) => a.localeCompare(b))
        .join('\n'),
);
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/blog'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-6xl"
      :data-testid="props.test_id"
    >
      <!-- Sticky header controls: back link / locale tabs / preview +
           save (no Cancel — the back link replaces it), spread with
           space-between. -->
      <AdminStickyActionBar :test_id="`${props.test_id}-sticky-bar`">
        <AdminBaseButton
          tag="a"
          href="/admin/blog"
          variant="ghost"
          icon="heroicons:arrow-left"
          icon_position="left"
          :test_id="`${props.test_id}-back-btn`"
        >
          {{ t('admin.blog.back_to_list') }}
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
            {{ t('admin.blog.preview') }}
          </AdminBaseButton>
          <AdminBaseButton
            type="submit"
            form="blog-form"
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
          isEdit ? t('admin.blog.edit') : t('admin.blog.create')
        }}
      </h1>

      <form
        id="blog-form"
        class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_360px]"
        novalidate
        :data-testid="`${props.test_id}-form`"
        @submit.prevent="save"
      >
        <div class="space-y-4">
          <TextEditor
            ref="editorRef"
            v-model="form.content[activeLocale]"
            required
            :min_height="EDITOR_MIN_HEIGHT"
            selectable_heading
            :error="fieldError('content')"
            :placeholder="t('admin.blog.start_writing')"
            :disabled="isSaving"
            :test_id="`${props.test_id}-editor-${activeLocale}`"
            @image-upload="onEditorImageUpload"
          />
        </div>

        <BlogFormMetaSidebar
          :title="form.title"
          :slug="form.slug"
          :short_description="form.short_description"
          :lead_image="form.lead_image"
          :lead_image_alt="form.lead_image_alt"
          :tags="form.tags"
          :related_ids="form.related_article_ids"
          :articles="props.articles"
          :related_tooltip="relatedTooltip"
          :is_published="form.is_published"
          :published_at="props.article?.published_at ?? null"
          :errors="errors"
          :active_locale="activeLocale"
          :is_saving="isSaving"
          :is_uploading="isUploadingImage"
          :test_id="props.test_id"
          @update:title="onTitleChange"
          @update:slug="onSlugChange"
          @update:short_description="(locale, value) => setLocaleValue(locale, form.short_description, value)"
          @update:lead_image="(value) => (form.lead_image = value)"
          @update:lead_image_alt="(locale, value) => setLocaleValue(locale, form.lead_image_alt, value)"
          @update:tags="(tags) => (form.tags = tags)"
          @update:related="(ids) => (form.related_article_ids = ids)"
          @update:published="(checked) => (form.is_published = checked)"
          @upload-lead="uploadLeadImage"
          @remove-lead="removeLeadImage"
        />
      </form>
    </section>
  </AdminPageLayout>
</template>
