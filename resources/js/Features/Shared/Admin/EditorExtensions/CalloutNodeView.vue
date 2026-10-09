<!-- The in-editor rendering of the callout node (ADR-0024 — 2026-08-14
     review): a Vue NodeView (via `Callout.addNodeView`) so the block can
     show the translated type label + per-type icon (renderHTML cannot
     translate, CSS cannot localize), matching the public style-guide
     callout shape — full border radius, 4px left border, uppercase
     icon+label title row (style-guide.html #callouts). The wrapper keeps
     the `data-callout` marker (tests + copy/paste parseHTML), the
     markdown round trip stays in `Callout.ts`. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { NodeViewContent, NodeViewWrapper, nodeViewProps } from '@tiptap/vue-3';
import { computed } from 'vue';
import { CALLOUT_CLASSES, CALLOUT_FRAME_CLASSES, type CalloutType } from './Callout';

defineOptions({ name: 'CalloutNodeView' });

const props = defineProps({ ...nodeViewProps });

const { t } = useI18n();

const type = computed<CalloutType>(() => (props.node.attrs.type as CalloutType) ?? 'warning');

const CALLOUT_ICONS: Record<CalloutType, string> = {
    warning: 'heroicons:exclamation-triangle',
    tip: 'heroicons:light-bulb',
    important: 'heroicons:information-circle',
    tradeoff: 'heroicons:arrow-path',
};
</script>

<template>
  <NodeViewWrapper
    :as="'div'"
    :class="[CALLOUT_FRAME_CLASSES, CALLOUT_CLASSES[type]]"
    class="text-sm leading-relaxed"
    :data-callout="type"
  >
    <div class="mb-1 flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider">
      <Icon
        class="h-4 w-4"
        :icon="CALLOUT_ICONS[type]"
        aria-hidden="true"
      />
      <span>{{ t(`admin.editor.callout_${type}`) }}</span>
    </div>
    <div class="callout__content">
      <NodeViewContent />
    </div>
  </NodeViewWrapper>
</template>
