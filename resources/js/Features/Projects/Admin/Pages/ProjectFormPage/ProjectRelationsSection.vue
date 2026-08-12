<!-- ProjectRelationsSection — the Relations card of the project form:
     the tech-tag picker and the tech-writeup article select over the
     published articles. Exports the wire types the page orchestrator
     shares with it. -->
<script setup lang="ts">
import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import AdminBaseDropdown from '@shared-admin/AdminBaseDropdown.vue';
import AdminTechSkillPicker from '@shared-admin/AdminTechSkillPicker.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

export interface TechTag {
    id: number;
    name: string;
    // The wire shape's icon is nullable — normalized to '' for the
    // picker (the picker contract wants a string).
    icon: string;
}

export interface ArticleOption {
    id: number;
    slug: string;
    title: string;
}

const props = withDefaults(
    defineProps<{
        tech_tags?: TechTag[];
        tech_writeup_article_id?: number | null;
        articles?: ArticleOption[];
        errors: Record<string, string>;
        disabled: boolean;
        test_id?: string;
    }>(),
    {
        tech_tags: () => [],
        tech_writeup_article_id: null,
        articles: () => [],
        test_id: 'project-relations-section',
    },
);

const emit = defineEmits<{
    'update:tech_tags': [tags: TechTag[]];
    'update:tech_writeup_article_id': [id: number | null];
}>();

const { t } = useI18n();

const techWriteupOptions = computed(() => [
    { value: '', label: t('common.none') },
    ...props.articles.map((article) => ({ value: String(article.id), label: article.title })),
]);

function onTechWriteupChange(value: string | string[]): void {
    const next = Array.isArray(value) ? value[0] : value;

    emit('update:tech_writeup_article_id', next === '' ? null : Number(next));
}
</script>

<template>
  <AdminBaseCard :test_id="`${props.test_id}-section-relations`">
    <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.projects.relations') }}
    </h2>
    <div class="space-y-4">
      <AdminTechSkillPicker
        :model-value="props.tech_tags"
        :label="t('admin.projects.tech_tags')"
        :error="props.errors.tech_tags ?? null"
        :disabled="props.disabled"
        :test_id="`${props.test_id}-tech-tags`"
        @update:model-value="(tags) => emit('update:tech_tags', tags)"
      />
      <div class="max-w-md">
        <AdminBaseDropdown
          :model-value="
            props.tech_writeup_article_id === null
              ? ''
              : String(props.tech_writeup_article_id)
          "
          :options="techWriteupOptions"
          :label="t('admin.projects.tech_writeup_article')"
          :error="props.errors.tech_writeup_article_id ?? null"
          :disabled="props.disabled"
          :test_id="`${props.test_id}-tech-writeup`"
          @update:model-value="onTechWriteupChange"
        />
        <p class="mt-1.5 text-xs text-slate-400 dark:text-slate-500">
          {{ t('admin.projects.tech_writeup_hint') }}
        </p>
      </div>
    </div>
  </AdminBaseCard>
</template>
