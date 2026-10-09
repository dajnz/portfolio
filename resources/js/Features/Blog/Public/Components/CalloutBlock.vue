<!-- CalloutBlock — a styled callout from a `> [!TYPE]` Markdown block. The server-side pipeline already rendered the static `<aside data-callout-type>` with its classes — the enhancer mounts this component in place carrying the extracted type/title/body. The type determines the icon and the colour (the shared `.callout` styles; the icon mapping mirrors the admin editor's per-type icons). Title is plain text (the pipeline renders the inline markdown — the enhancer extracts the text content); the body HTML is the pipeline's safe-mode output. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';

type CalloutType = 'warning' | 'tip' | 'important' | 'tradeoff';

const TYPE_ICONS: Record<CalloutType, string> = {
    warning: 'heroicons:exclamation-triangle',
    tip: 'heroicons:light-bulb',
    important: 'heroicons:information-circle',
    tradeoff: 'heroicons:arrow-path',
};

const props = withDefaults(
    defineProps<{
        type?: CalloutType;
        title?: string;
        content_html?: string;
        test_id?: string;
    }>(),
    {
        type: 'tip',
        title: '',
        content_html: '',
        test_id: 'callout-block',
    },
);

const { t } = useI18n();

// The heading label: the markdown `> [!TYPE] Title` title when present,
// otherwise the localized type label — the heading row is ALWAYS shown.
const headingLabel = computed(() =>
    props.title !== '' ? props.title : t(`blog.callout_${props.type}`),
);
</script>

<template>
  <aside
    role="note"
    class="callout"
    :class="`callout--${props.type}`"
    :data-callout-type="props.type"
    :data-testid="props.test_id"
  >
    <div class="callout__title">
      <Icon
        class="callout__title-icon"
        :icon="TYPE_ICONS[props.type]"
        aria-hidden="true"
      />
      {{ headingLabel }}
    </div>
    <!-- eslint-disable vue/no-v-html -->
    <div
      class="callout__content"
      v-html="props.content_html"
    />
    <!-- eslint-enable vue/no-v-html -->
  </aside>
</template>

<style scoped>
.callout__title-icon {
    width: 0.95em;
    height: 0.95em;
    flex-shrink: 0;
}
</style>
