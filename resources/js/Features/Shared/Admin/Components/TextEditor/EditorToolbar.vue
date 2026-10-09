<!-- Presentational toolbar: every button emits a click event (with the
     native MouseEvent where the parent needs it for popup anchoring); the
     parent owns the editor commands and the popup state machine. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { computed } from 'vue';
import type { TextEditorButton } from './TextEditor.vue';
import type { EditorActiveState } from './useEditorInstance';
import type { EditorPopupMode } from './useEditorPopup';

const props = withDefaults(
    defineProps<{
        buttons: TextEditorButton[];
        selectable_heading: boolean;
        state: EditorActiveState;
        active_popup?: EditorPopupMode | null;
        test_id?: string;
    }>(),
    {
        active_popup: null,
        test_id: 'text-editor',
    },
);

const emit = defineEmits<{
    'click-heading': [event: MouseEvent];
    'click-list': [type: 'bulletList' | 'orderedList', event: MouseEvent];
    'click-mark': [type: string, event: MouseEvent];
    'click-code-block': [event: MouseEvent];
    'click-blockquote': [event: MouseEvent];
    'click-image': [];
    'click-table': [event: MouseEvent];
    'click-callout': [event: MouseEvent];
    'click-link': [event: MouseEvent];
    'click-mode-toggle': [];
}>();

const { t } = useI18n();

const activeButtonClass = 'bg-slate-200 dark:bg-slate-700';
const idleButtonClass =
    'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700';

const hasBlockButtons = computed(
    () =>
        props.buttons.includes('heading') ||
        props.buttons.includes('bullet-list') ||
        props.buttons.includes('ordered-list'),
);
const hasInlineButtons = computed(
    () =>
        props.buttons.includes('bold') ||
        props.buttons.includes('italic') ||
        props.buttons.includes('underline') ||
        props.buttons.includes('strike'),
);
const hasScriptButtons = computed(
    () => props.buttons.includes('subscript') || props.buttons.includes('superscript'),
);
const hasCodeButtons = computed(
    () =>
        props.buttons.includes('code') ||
        props.buttons.includes('code-block') ||
        props.buttons.includes('blockquote'),
);
const hasMediaButtons = computed(
    () =>
        props.buttons.includes('image') ||
        props.buttons.includes('table') ||
        props.buttons.includes('callout') ||
        props.buttons.includes('link'),
);
const hasImageButton = computed(() => props.buttons.includes('image'));
const hasTableButton = computed(() => props.buttons.includes('table'));
const hasCalloutButton = computed(() => props.buttons.includes('callout'));
const hasLinkButton = computed(() => props.buttons.includes('link'));
</script>

<template>
  <div
    class="thin-scrollbar sticky top-[129px] z-10 flex items-center gap-1 overflow-x-auto px-3 py-2 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900"
    role="toolbar"
    :aria-label="t('admin.editor.toolbar')"
    :data-testid="`${props.test_id}-toolbar`"
  >
    <template v-if="!props.state.isRawMode">
      <template v-if="props.buttons.includes('heading')">
        <button
          v-if="props.selectable_heading"
          type="button"
          :class="[idleButtonClass, { [activeButtonClass]: props.state.activeHeadingLevel > 0 }]"
          class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-xs font-bold rounded transition-colors"
          :aria-label="t('admin.editor.heading_level_label')"
          :title="t('admin.editor.heading_level_label')"
          :aria-pressed="props.active_popup === 'heading'"
          :data-testid="`${props.test_id}-heading`"
          @click="emit('click-heading', $event)"
        >
          {{ props.state.activeHeadingLevel > 0 ? `H${props.state.activeHeadingLevel}` : 'H' }}
        </button>
        <button
          v-else
          type="button"
          :class="[
            idleButtonClass,
            { [activeButtonClass]: props.state.activeHeadingLevel === 2 },
          ]"
          class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.heading')"
          :title="t('admin.editor.heading')"
          :aria-pressed="props.state.activeHeadingLevel === 2"
          :data-testid="`${props.test_id}-heading`"
          @click="emit('click-heading', $event)"
        >
          H
        </button>
      </template>
      <button
        v-if="props.buttons.includes('bullet-list')"
        type="button"
        :class="[
          idleButtonClass,
          { [activeButtonClass]: props.state.activeMarks.bulletList },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.bullet_list')"
        :title="t('admin.editor.bullet_list')"
        :aria-pressed="props.state.activeMarks.bulletList"
        :data-testid="`${props.test_id}-bullet-list`"
        @click="emit('click-list', 'bulletList', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:list-bullet"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="props.buttons.includes('ordered-list')"
        type="button"
        :class="[
          idleButtonClass,
          { [activeButtonClass]: props.state.activeMarks.orderedList },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.ordered_list')"
        :title="t('admin.editor.ordered_list')"
        :aria-pressed="props.state.activeMarks.orderedList"
        :data-testid="`${props.test_id}-ordered-list`"
        @click="emit('click-list', 'orderedList', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:numbered-list"
          aria-hidden="true"
        />
      </button>

      <template v-if="hasInlineButtons && hasBlockButtons">
        <div class="w-px h-5 shrink-0 bg-slate-200 dark:bg-slate-700 mx-1" />
      </template>
      <button
        v-if="props.buttons.includes('bold')"
        type="button"
        :class="[
          `${idleButtonClass} font-bold`,
          { [activeButtonClass]: props.state.activeMarks.bold },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.bold')"
        :title="t('admin.editor.bold')"
        :aria-pressed="props.state.activeMarks.bold"
        :data-testid="`${props.test_id}-bold`"
        @click="emit('click-mark', 'bold', $event)"
      >
        B
      </button>
      <button
        v-if="props.buttons.includes('italic')"
        type="button"
        :class="[
          `${idleButtonClass} italic`,
          { [activeButtonClass]: props.state.activeMarks.italic },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.italic')"
        :title="t('admin.editor.italic')"
        :aria-pressed="props.state.activeMarks.italic"
        :data-testid="`${props.test_id}-italic`"
        @click="emit('click-mark', 'italic', $event)"
      >
        I
      </button>
      <button
        v-if="props.buttons.includes('underline')"
        type="button"
        :class="[
          idleButtonClass,
          { [activeButtonClass]: props.state.activeMarks.underline },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.underline')"
        :title="t('admin.editor.underline')"
        :aria-pressed="props.state.activeMarks.underline"
        :data-testid="`${props.test_id}-underline`"
        @click="emit('click-mark', 'underline', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:underline"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="props.buttons.includes('strike')"
        type="button"
        :class="[idleButtonClass, { [activeButtonClass]: props.state.activeMarks.strike }]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.strikethrough')"
        :title="t('admin.editor.strikethrough')"
        :aria-pressed="props.state.activeMarks.strike"
        :data-testid="`${props.test_id}-strike`"
        @click="emit('click-mark', 'strike', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:strikethrough"
          aria-hidden="true"
        />
      </button>

      <template v-if="hasScriptButtons && hasInlineButtons">
        <div class="w-px h-5 shrink-0 bg-slate-200 dark:bg-slate-700 mx-1" />
      </template>
      <button
        v-if="props.buttons.includes('subscript')"
        type="button"
        :class="[
          idleButtonClass,
          { [activeButtonClass]: props.state.activeMarks.subscript },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.subscript')"
        :title="t('admin.editor.subscript')"
        :aria-pressed="props.state.activeMarks.subscript"
        :data-testid="`${props.test_id}-subscript`"
        @click="emit('click-mark', 'subscript', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="lucide:subscript"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="props.buttons.includes('superscript')"
        type="button"
        :class="[
          idleButtonClass,
          { [activeButtonClass]: props.state.activeMarks.superscript },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.superscript')"
        :title="t('admin.editor.superscript')"
        :aria-pressed="props.state.activeMarks.superscript"
        :data-testid="`${props.test_id}-superscript`"
        @click="emit('click-mark', 'superscript', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="lucide:superscript"
          aria-hidden="true"
        />
      </button>

      <template v-if="hasCodeButtons && hasScriptButtons">
        <div class="w-px h-5 shrink-0 bg-slate-200 dark:bg-slate-700 mx-1" />
      </template>
      <button
        v-if="props.buttons.includes('code')"
        type="button"
        :class="[idleButtonClass, { [activeButtonClass]: props.state.activeMarks.code }]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.code')"
        :title="t('admin.editor.code')"
        :aria-pressed="props.state.activeMarks.code"
        :data-testid="`${props.test_id}-code`"
        @click="emit('click-mark', 'code', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:code-bracket"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="props.buttons.includes('code-block')"
        type="button"
        :class="[
          idleButtonClass,
          { [activeButtonClass]: props.state.isCodeBlockActive },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.code_block')"
        :title="t('admin.editor.code_block')"
        :aria-pressed="props.state.isCodeBlockActive"
        :data-testid="`${props.test_id}-code-block`"
        @click="emit('click-code-block', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:code-bracket-square"
          aria-hidden="true"
        />
      </button>
      <button
        v-if="props.buttons.includes('blockquote')"
        type="button"
        :class="[
          idleButtonClass,
          { [activeButtonClass]: props.state.activeMarks.blockquote },
        ]"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.blockquote')"
        :title="t('admin.editor.blockquote')"
        :aria-pressed="props.state.activeMarks.blockquote"
        :data-testid="`${props.test_id}-blockquote`"
        @click="emit('click-blockquote', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:chat-bubble-oval-left-ellipsis"
          aria-hidden="true"
        />
      </button>

      <template v-if="hasMediaButtons && hasCodeButtons">
        <div class="w-px h-5 shrink-0 bg-slate-200 dark:bg-slate-700 mx-1" />
      </template>
      <template v-if="hasImageButton">
        <button
          type="button"
          :class="idleButtonClass"
          class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.insert_image')"
          :title="t('admin.editor.insert_image')"
          :data-testid="`${props.test_id}-image`"
          @click="emit('click-image')"
        >
          <Icon
            class="w-4 h-4"
            icon="heroicons:photo"
            aria-hidden="true"
          />
        </button>
      </template>
      <button
        v-if="hasTableButton"
        type="button"
        :class="idleButtonClass"
        class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
        :aria-label="t('admin.editor.insert_table')"
        :title="t('admin.editor.insert_table')"
        :aria-pressed="props.active_popup === 'table'"
        :data-testid="`${props.test_id}-table`"
        @click="emit('click-table', $event)"
      >
        <Icon
          class="w-4 h-4"
          icon="heroicons:table-cells"
          aria-hidden="true"
        />
      </button>
      <template v-if="hasCalloutButton">
        <button
          type="button"
          :class="[
            idleButtonClass,
            { [activeButtonClass]: props.state.isCalloutActive },
          ]"
          class="select-none px-2 h-8 shrink-0 inline-flex items-center justify-center text-xs font-medium rounded transition-colors"
          :aria-label="t('admin.editor.callout')"
          :title="t('admin.editor.callout')"
          :aria-pressed="props.state.isCalloutActive"
          :data-testid="`${props.test_id}-callout`"
          @click="emit('click-callout', $event)"
        >
          [!]
        </button>
      </template>
      <template v-if="hasLinkButton">
        <button
          type="button"
          :class="[idleButtonClass, { [activeButtonClass]: props.state.activeMarks.link }]"
          class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.link')"
          :title="t('admin.editor.link')"
          :aria-pressed="props.active_popup === 'link'"
          :data-testid="`${props.test_id}-link`"
          @click="emit('click-link', $event)"
        >
          <Icon
            class="w-4 h-4"
            icon="heroicons:link"
            aria-hidden="true"
          />
        </button>
      </template>
      <div class="w-px h-5 shrink-0 bg-slate-200 dark:bg-slate-700 mx-1" />
    </template>
    <button
      type="button"
      :class="[idleButtonClass, { [activeButtonClass]: props.state.isRawMode }]"
      class="select-none w-8 h-8 shrink-0 inline-flex items-center justify-center text-sm rounded transition-colors"
      :aria-label="
        t(props.state.isRawMode ? 'admin.editor.rendered_mode' : 'admin.editor.raw_mode')
      "
      :title="t(props.state.isRawMode ? 'admin.editor.rendered_mode' : 'admin.editor.raw_mode')"
      :aria-pressed="props.state.isRawMode"
      :data-testid="`${props.test_id}-mode-toggle`"
      @click="emit('click-mode-toggle')"
    >
      <Icon
        class="w-4 h-4"
        :icon="props.state.isRawMode ? 'heroicons:pencil-square' : 'heroicons:document-text'"
        aria-hidden="true"
      />
    </button>
  </div>
</template>
