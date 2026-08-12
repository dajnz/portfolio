<!-- ProjectMetaSection — the Meta card of the project form: the URL
     pair (github/demo) and the Featured + sort-order / Published
     checkbox rows. Pure value+emit contract. -->
<script setup lang="ts">
import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';

const props = withDefaults(
    defineProps<{
        github_url?: string;
        live_demo_url?: string;
        is_featured?: boolean;
        featured_sort_order?: string;
        is_published?: boolean;
        errors: Record<string, string>;
        disabled: boolean;
        test_id?: string;
    }>(),
    {
        github_url: '',
        live_demo_url: '',
        is_featured: false,
        featured_sort_order: '',
        is_published: false,
        test_id: 'project-meta-section',
    },
);

const emit = defineEmits<{
    'update:github_url': [value: string];
    'update:live_demo_url': [value: string];
    'update:is_featured': [value: boolean];
    'update:featured_sort_order': [value: string];
    'update:is_published': [value: boolean];
}>();

const { t } = useI18n();
</script>

<template>
  <AdminBaseCard :test_id="`${props.test_id}-section-meta`">
    <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.projects.meta') }}
    </h2>
    <!-- Two columns with a clear gap (user request 2026-08-12,
         round 3): left = the URL pair stacked, right = the
         Featured + sort-order row, then the Published row
         vertically centered against the demo URL. -->
    <div class="grid grid-cols-1 gap-x-12 gap-y-4 sm:grid-cols-2">
      <div class="space-y-4">
        <AdminBaseInput
          :model-value="props.github_url"
          :label="t('admin.projects.github_url')"
          type="url"
          :error="props.errors.github_url ?? null"
          :disabled="props.disabled"
          :maxlength="500"
          :test_id="`${props.test_id}-github-url`"
          @update:model-value="(value) => emit('update:github_url', value)"
        />
        <AdminBaseInput
          :model-value="props.live_demo_url"
          :label="t('admin.projects.live_demo_url')"
          type="url"
          :error="props.errors.live_demo_url ?? null"
          :disabled="props.disabled"
          :maxlength="500"
          :test_id="`${props.test_id}-demo-url`"
          @update:model-value="(value) => emit('update:live_demo_url', value)"
        />
      </div>
      <div class="space-y-4">
        <!-- Row 1: Featured checkbox + the sort-order input taking
             the remaining width. `items-end` + `mb-3` centers the
             checkbox against the INPUT (not the label block) —
             exact regardless of the label wrapping. -->
        <div class="flex items-end gap-4">
          <AdminBaseCheckbox
            :model-value="props.is_featured"
            :label="t('admin.projects.featured')"
            class="mb-3"
            :disabled="props.disabled"
            :test_id="`${props.test_id}-featured`"
            @update:model-value="(value) => emit('update:is_featured', Boolean(value))"
          />
          <AdminBaseInput
            :model-value="props.featured_sort_order"
            :label="t('admin.projects.featured_sort_order')"
            type="number"
            min="0"
            class="flex-1"
            :error="props.errors.featured_sort_order ?? null"
            :disabled="props.disabled || !props.is_featured"
            :test_id="`${props.test_id}-featured-order`"
            @update:model-value="(value) => emit('update:featured_sort_order', value)"
          />
        </div>
        <!-- Row 2: Published — own row container, vertically
             centered against the demo URL input's middle (the
             ~50px offset was verified by the user). -->
        <div class="mt-[50px]">
          <AdminBaseCheckbox
            :model-value="props.is_published"
            :label="t('admin.projects.published')"
            :disabled="props.disabled"
            :test_id="`${props.test_id}-published`"
            @update:model-value="(value) => emit('update:is_published', Boolean(value))"
          />
        </div>
      </div>
    </div>
  </AdminBaseCard>
</template>
