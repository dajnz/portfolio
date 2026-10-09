<!-- TextEditor (admin-section-components.md:217-249) — the complex
     component's ORCHESTRATOR (2026-08-14 user request): Tiptap Markdown
     WYSIWYG editor with callouts (`> [!WARNING]` marker syntax,
     extension in EditorExtensions/Callout.ts), lowlight code blocks
     with language selector, image upload events, drag-resizable images
     (Tiptap's built-in ResizableNodeView — the size is an
     editor-session aid: markdown cannot carry the width without
     breaking the public safe-mode pipeline, ADR-0024), GFM tables, links,
     underline/strikethrough/subscript/superscript marks, and the
     5-group toolbar (block / inline / script / code+quote / media).
     The root owns the editor instance, the modelValue round trip, the
     raw/WYSIWYG switch and the placeholder; the toolbar row is
     `EditorToolbar.vue`, the shared config popup is
     `EditorConfigPopup.vue`, the popup state machine is
     `useEditorPopup.ts`, the table-size grid is
     `useEditorTableGrid.ts`. The `is_loading` prop drives the spec's
     `loading` skeleton state (gap-fill — the spec documents the state
     without a triggering prop). -->
<script lang="ts">
/** The toolbar buttons the editor can expose — the `buttons` prop
 *  picks a subset (blog-only controls like image/table/callout are
 *  excluded by the projects config; the 2026-08-13 toolbar rework —
 *  ADR-0024 — reorganized the groups and added underline/strike/sub/sup/
 *  table/link). */
export type TextEditorButton =
    | 'heading'
    | 'bullet-list'
    | 'ordered-list'
    | 'bold'
    | 'italic'
    | 'underline'
    | 'strike'
    | 'subscript'
    | 'superscript'
    | 'code'
    | 'code-block'
    | 'blockquote'
    | 'image'
    | 'table'
    | 'callout'
    | 'link';

/** The blog-only controls — image upload, GFM tables and callouts. */
export type BlogOnlyEditorButton = 'image' | 'table' | 'callout';

/** The projects-specific button subset — the Markdown fields keep the
 *  shared formatting but no blog-only controls: images go through the
 *  Media section (DropzoneUpload + screenshots gallery), tables and
 *  callouts are blog-only (ADR-0024). */
export type ProjectEditorButton = Exclude<TextEditorButton, BlogOnlyEditorButton>;

/** The blog config — every button the editor can expose. */
export const BLOG_EDITOR_BUTTONS: TextEditorButton[] = [
    'heading',
    'bullet-list',
    'ordered-list',
    'bold',
    'italic',
    'underline',
    'strike',
    'subscript',
    'superscript',
    'code',
    'code-block',
    'blockquote',
    'image',
    'table',
    'callout',
    'link',
];

/** The projects config — {@link ProjectEditorButton} in full. */
export const PROJECT_EDITOR_BUTTONS: ProjectEditorButton[] = [
    'heading',
    'bullet-list',
    'ordered-list',
    'bold',
    'italic',
    'underline',
    'strike',
    'subscript',
    'superscript',
    'code',
    'code-block',
    'blockquote',
    'link',
];

/** The parent-facing surface of the editor — the callbacks the
 *  consuming forms (BlogFormPage) can invoke after an upload. */
export interface TextEditorExposed {
    insertImage: (url: string) => void;
}
</script>

<script setup lang="ts">
import { useI18n } from '@shared-cross/Composables/useI18n';
import { EditorContent } from '@tiptap/vue-3';
import { computed, ref } from 'vue';

import AdminBaseSkeleton from '../AdminBaseSkeleton.vue';
import EditorConfigPopup from './EditorConfigPopup.vue';
import EditorToolbar from './EditorToolbar.vue';
import { useEditorCommands } from './useEditorCommands';
import { useEditorInstance } from './useEditorInstance';
import { useEditorPopup } from './useEditorPopup';

const props = withDefaults(
    defineProps<{
        modelValue: string;
        label?: string;
        error?: string | null;
        required?: boolean;
        help?: string;
        placeholder?: string;
        disabled?: boolean;
        is_loading?: boolean;
        /** The toolbar buttons to expose — a subset of
         *  {@link TextEditorButton}. Use the exported per-surface
         *  configs (`PROJECT_EDITOR_BUTTONS`, `BLOG_EDITOR_BUTTONS`)
         *  instead of ad-hoc lists. */
        buttons?: TextEditorButton[];
        /** Selectable heading level (H1–H6 dropdown) instead of the
         *  single H button — the blog article editor config (ADR-0024).
         *  Default `false` keeps the one-click heading button. */
        selectable_heading?: boolean;
        /** Minimum editor height (CSS length) — the WYSIWYG view
         *  grows with the content from this baseline, the raw view
         *  gets a FIXED height of the same value with its own
         *  scrollbar (user request 2026-08-13 — the blog form's
         *  editor reaches nearly to the bottom of the screen). */
        min_height?: string;
        test_id?: string;
    }>(),
    {
        label: undefined,
        error: null,
        required: false,
        help: undefined,
        placeholder: undefined,
        disabled: false,
        is_loading: false,
        buttons: () => [...BLOG_EDITOR_BUTTONS],
        selectable_heading: false,
        min_height: undefined,
        test_id: 'text-editor',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: string];
    focus: [];
    blur: [];
    'image-upload': [file: File];
}>();

const { t } = useI18n();

const imageInputEl = ref<HTMLInputElement | null>(null);
const isFocused = ref(false);
const toolbarRef = ref<InstanceType<typeof EditorToolbar> | null>(null);
const popupRef = ref<InstanceType<typeof EditorConfigPopup> | null>(null);

const placeholderText = computed(() => props.placeholder ?? t('admin.editor.placeholder'));

// ── The shared config popup (ADR-0024) — the state machine in
//    useEditorPopup.ts: one popup at a time, fixed-anchored under the
//    clicked toolbar button, closed by outside click (capture
//    listener excluding the toolbar/popup), Escape or applying an
//    option — NOT by the editor blur (focusing the popup's own input
//    blurs the editor first, which would instantly close the
//    just-opened popup). The popup's DOM element is forwarded by
//    EditorConfigPopup for the outside-click check. ──
const popup = useEditorPopup({
    popupElement: () => popupRef.value?.rootElement ?? null,
    toolbarElement: () => toolbarRef.value?.$el ?? null,
});

const { mode: popupMode, position: popupPosition } = popup;

// ── The Tiptap instance (useEditorInstance.ts): the editor
//    lifecycle, the active-state tracking, the raw/WYSIWYG mode and
//    the content/editable syncs. ──
const { editor, state, isEmpty, rawValue, toggleMode, onRawInput } =
    useEditorInstance({
        content: () => props.modelValue,
        editable: () => !props.disabled,
        onUpdate: (markdown) => emit('update:modelValue', markdown),
        onFocus: () => {
            isFocused.value = true;
            emit('focus');
        },
        onBlur: () => {
            isFocused.value = false;
            emit('blur');
        },
        onRawInput: (value) => emit('update:modelValue', value),
        onBeforeModeSwitch: popup.close,
    });

// ── The editor commands + the toolbar/popup handlers
//    (useEditorCommands.ts) — the UI events become transactions. ──
const {
    linkHref,
    toggleBlockquote,
    setCodeLanguage,
    switchCalloutType,
    applyHeading,
    applyLink,
    removeLink,
    applyTable,
    insertImage,
    onHeadingClick,
    onListClick,
    onMarkClick,
    onCodeBlockClick,
    onCalloutClick,
    onTableClick,
    onLinkClick,
} = useEditorCommands({
    editor: () => editor.value,
    state,
    popup,
    selectableHeading: () => props.selectable_heading,
});

defineExpose({ insertImage });

// The min-height style of the content area — the shared container
// style covers the WYSIWYG view (the ProseMirror content grows from
// the baseline); the raw textarea needs its own fixed height (with a
// scrollbar) since it is an independent element (user request
// 2026-08-13).
const contentMinHeightStyle = computed(() =>
    props.min_height !== undefined ? { minHeight: props.min_height } : undefined,
);
const rawTextareaStyle = computed(() =>
    props.min_height !== undefined ? { height: props.min_height } : undefined,
);

function onImageClick(): void {
    imageInputEl.value?.click();
}

function onImageChange(event: Event): void {
    const file = (event.target as HTMLInputElement).files?.[0];

    if (file !== undefined) {
        emit('image-upload', file);
    }

    (event.target as HTMLInputElement).value = '';
}
</script>

<template>
  <div>
    <label
      v-if="props.label"
      class="block text-sm font-semibold mb-1.5 text-slate-700 dark:text-slate-200"
    >
      {{ props.label }}
      <span
        v-if="props.required"
        class="text-danger-500"
      >*</span>
    </label>
    <AdminBaseSkeleton
      v-if="props.is_loading"
      shape="rectangle"
      width="100%"
      height="400px"
      :test_id="`${props.test_id}-skeleton`"
    />
    <div
      v-else
      class="relative"
    >
      <!-- The bordered box uses `overflow-clip` (NOT `overflow-hidden`):
           `hidden` makes the box the sticky scrollport and pins the
           toolbar's corner radii to the box — `clip` clips children to
           the border's exact inner curve (the toolbar's top corners and
           the content's bottom corners) WITHOUT blocking the sticky
           toolbar, so no per-child radius classes are needed and the
           stuck toolbar gets square corners by itself (2026-08-14
           review find). The toolbar's sticky offset is 129 px — the
           fixed admin header (h-16) + the page's `AdminStickyActionBar`
           (64 px) — so the stuck row sits flush under it, no gap. -->
      <div
        :class="[
          {
            'border-danger-300 dark:border-danger-700': props.error !== null,
            'opacity-45 pointer-events-none': props.disabled,
          },
        ]"
        class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-clip bg-white dark:bg-surface-dark-secondary focus-within:border-primary-500 transition-all"
        :data-testid="props.test_id"
      >
        <EditorToolbar
          v-if="!props.disabled"
          ref="toolbarRef"
          :buttons="props.buttons"
          :selectable_heading="props.selectable_heading"
          :state="state"
          :active_popup="popupMode"
          :test_id="props.test_id"
          @click-heading="onHeadingClick"
          @click-list="onListClick"
          @click-mark="onMarkClick"
          @click-code-block="onCodeBlockClick"
          @click-blockquote="toggleBlockquote"
          @click-image="onImageClick"
          @click-table="onTableClick"
          @click-callout="onCalloutClick"
          @click-link="onLinkClick"
          @click-mode-toggle="toggleMode"
        />
        <input
          v-if="props.buttons.includes('image')"
          ref="imageInputEl"
          type="file"
          accept="image/*"
          class="hidden"
          :data-testid="`${props.test_id}-image-input`"
          @change="onImageChange"
        >
        <div
          class="relative"
          :style="contentMinHeightStyle"
          :aria-invalid="props.error !== null || undefined"
          :data-testid="`${props.test_id}-content`"
        >
          <textarea
            v-if="state.isRawMode"
            class="w-full min-h-[200px] px-4 py-3 font-mono text-sm leading-relaxed text-slate-800 dark:text-slate-200 bg-transparent outline-none placeholder-slate-400 dark:placeholder-slate-600"
            :class="
              props.min_height !== undefined ? 'resize-none overflow-y-auto' : 'resize-y'
            "
            :style="rawTextareaStyle"
            :value="rawValue"
            :placeholder="placeholderText"
            :disabled="props.disabled || undefined"
            :data-testid="`${props.test_id}-raw`"
            @input="onRawInput"
          />
          <template v-else>
            <EditorContent
              v-if="editor !== null"
              :editor="editor"
              class="text-editor__content px-4 py-3 text-sm text-slate-800 dark:text-slate-200 leading-relaxed"
            />
            <!-- The placeholder hides as soon as the editor is focused
               (the cursor sits where the first line starts) and
               reappears on blur while the content is still empty
               (ADR-0024 — the focus state also fixes the vertical
               alignment: the first content line's margin is zeroed
               via CSS, so both start at the same padding offset). -->
            <p
              v-if="isEmpty && !isFocused && !props.disabled"
              class="absolute top-0 left-0 px-4 py-3 text-sm text-slate-400 dark:text-slate-600 pointer-events-none"
              :data-testid="`${props.test_id}-placeholder`"
            >
              {{ placeholderText }}
            </p>
          </template>
        </div>

        <!-- ══ The shared config popup (ADR-0024) — one mode at a time,
           fixed-anchored under the clicked toolbar button. ══ -->
        <EditorConfigPopup
          v-if="popupMode !== null"
          ref="popupRef"
          :mode="popupMode"
          :position="popupPosition"
          :heading_level="state.activeHeadingLevel"
          :link_href="linkHref"
          :active_code_language="state.activeCodeLanguage"
          :active_callout_type="state.activeCalloutType"
          :test_id="props.test_id"
          @apply-heading="applyHeading"
          @apply-link="applyLink"
          @remove-link="removeLink"
          @select-language="setCodeLanguage"
          @select-callout="switchCalloutType"
          @apply-table="applyTable"
        />
      </div>
    </div>
    <p
      v-if="props.error !== null"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
      :data-testid="`${props.test_id}-error`"
    >
      {{ props.error }}
    </p>
    <p
      v-else-if="props.help"
      class="mt-1.5 text-xs text-slate-400 dark:text-slate-500"
    >
      {{ props.help }}
    </p>
  </div>
</template>

<!-- Editor-content typography (user request 2026-08-12): the global
     resets strip heading sizes (Tailwind preflight inherits them), list
     markers (public-tokens.css `ul, ol { list-style: none }`) and
     blockquote/quote affordances (universal margin/padding reset), so
     the ProseMirror content needs its own rules. EditorContent renders
     a plain wrapper div and moves the ProseMirror view DOM into it, so
     the rules anchor on the component-unique `.text-editor__content`
     class instead of Vue's scoped attribute (which cannot reach the
     moved-in content). -->
<style>
.text-editor__content h1,
.text-editor__content h2,
.text-editor__content h3,
.text-editor__content h4 {
    font-weight: 700;
    line-height: 1.3;
    margin: 1.5rem 0 0.5rem;
    color: inherit;
}

.text-editor__content h1 {
    font-size: 1.875rem;
}

.text-editor__content h2 {
    font-size: 1.5rem;
}

.text-editor__content h3 {
    font-size: 1.25rem;
    font-weight: 600;
}

.text-editor__content h4 {
    font-size: 1.125rem;
    font-weight: 600;
}

.text-editor__content p {
    margin: 0.5rem 0;
}

/* Long unbroken strings (URLs, long words) must wrap inside the editor:
   without `overflow-wrap: anywhere` the content's min-content width
   grows past the box and drags the whole editor (and its grid/flex
   column) wider than the layout allows (2026-08-14 review find).
   `anywhere` (NOT ProseMirror's injected `break-word` — which only
   wraps visually without capping the min-content size) is set on the
   `.ProseMirror` element itself, beating the ProseMirror stylesheet
   (higher specificity), so grid columns keep shrinking. Code blocks
   (`pre`) stay exempt: their own rule below resets `overflow-wrap`
   and uses `contain: inline-size` so the long lines scroll
   horizontally (`overflow-x: auto`) instead of wrapping. */
/* stylelint-disable-next-line selector-class-pattern -- Tiptap's own injected class name */
.text-editor__content .ProseMirror {
    overflow-wrap: anywhere;
}

/* The first content line starts exactly at the padding offset — the
   same Y as the placeholder text (ADR-0024; the placeholder previously
   sat above the cursor line by the first paragraph's top margin). */
.text-editor__content [contenteditable='true'] > :first-child {
    margin-top: 0;
}

.text-editor__content ul,
.text-editor__content ol {
    margin: 0.5rem 0;
    padding-left: 1.5rem;
}

.text-editor__content ul {
    list-style: disc;
}

.text-editor__content ol {
    list-style: decimal;
}

.text-editor__content li {
    margin: 0.25rem 0;
}

.text-editor__content li > ul,
.text-editor__content li > ol {
    margin: 0.25rem 0;
}

.text-editor__content blockquote {
    border-left: 3px solid #cbd5e1;
    padding-left: 1rem;
    margin: 0.75rem 0;
    color: #64748b;
}

/* GFM tables (ADR-0024 — the editor's table button). */
.text-editor__content table {
    border-collapse: collapse;
    margin: 0.75rem 0;
    width: 100%;
}

.text-editor__content th,
.text-editor__content td {
    border: 1px solid #cbd5e1;
    padding: 0.375rem 0.75rem;
    text-align: left;
    vertical-align: top;
}

.text-editor__content th {
    background: #f1f5f9;
    font-weight: 600;
}

.text-editor__content pre {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.875rem;
    line-height: 1.6;
    background: #f1f5f9;
    color: #0f172a;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 0.75rem 1rem;
    overflow-x: auto;
    margin: 0.75rem 0;

    /* Long code lines keep their layout — the inherited
       `overflow-wrap: anywhere` is reset so they scroll horizontally,
       and `contain: inline-size` stops the line's min-content width
       from growing the editor (2026-08-14 review find). */
    overflow-wrap: normal;
    contain: inline-size;

    /* The thin scrollbar (the `thin-scrollbar` utility's declarations —
       self-contained here so the SFC style block stays plain CSS). */
    scrollbar-width: thin;
    scrollbar-color: #cbd5e1 transparent;
}

.text-editor__content pre::-webkit-scrollbar {
    width: 6px;
    height: 6px;
}

.text-editor__content pre::-webkit-scrollbar-thumb {
    border-radius: 9999px;
    background-color: #cbd5e1;
}

.text-editor__content pre::-webkit-scrollbar-track {
    background-color: transparent;
}

.text-editor__content code {
    font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
    font-size: 0.875em;
    background: #f1f5f9;
    color: inherit;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
}

.text-editor__content pre code {
    background: none;
    padding: 0;
    font-size: 1em;
    border-radius: 0;
}

.text-editor__content a {
    color: #2447c9;
    text-decoration: underline;
    text-underline-offset: 2px;
}

.text-editor__content a:hover {
    color: #1b369e;
}

.dark .text-editor__content a {
    color: #8fb0f7;
}

.dark .text-editor__content a:hover {
    color: #b9cffb;
}

/* Callout content spacing (the NodeView chrome — the label row owns
   the top margin, the block's own padding the sides; the paragraph
   rules above would otherwise push the first line off the padding). */
.text-editor__content [data-node-view-wrapper] [data-node-view-content] > :first-child,
.text-editor__content [data-callout] > .callout__content > :first-child {
    margin-top: 0;
}

.text-editor__content [data-node-view-wrapper] [data-node-view-content] > :last-child,
.text-editor__content [data-callout] > .callout__content > :last-child {
    margin-bottom: 0;
}

.text-editor__content [data-callout] p {
    margin: 0.25rem 0;
}

/* lowlight syntax theme for the editor's code blocks (ADR-0024 —
   CodeBlockLowlight decorates tokens with `hljs-*` spans; without
   colors they all render as plain text). A compact slate palette
   matching the admin surface; the server-side public pipeline uses
   Shiki with its own theme (MarkdownContentPipeline). */
.text-editor__content pre code .hljs-keyword,
.text-editor__content pre code .hljs-selector-tag,
/* stylelint-disable-next-line selector-class-pattern -- highlight.js token classes carry underscores by design */
.text-editor__content pre code .hljs-built_in,
.text-editor__content pre code .hljs-type {
    color: #9333ea;
}

.text-editor__content pre code .hljs-string,
.text-editor__content pre code .hljs-regexp,
.text-editor__content pre code .hljs-addition,
.text-editor__content pre code .hljs-attribute {
    color: #15803d;
}

.text-editor__content pre code .hljs-number,
.text-editor__content pre code .hljs-literal {
    color: #c2410c;
}

.text-editor__content pre code .hljs-title,
/* stylelint-disable-next-line selector-class-pattern -- highlight.js token classes carry underscores by design */
.text-editor__content pre code .hljs-title.function_,
.text-editor__content pre code .hljs-section,
.text-editor__content pre code .hljs-function .hljs-title {
    color: #1d4ed8;
}

.text-editor__content pre code .hljs-comment,
.text-editor__content pre code .hljs-quote {
    color: #64748b;
    font-style: italic;
}

.text-editor__content pre code .hljs-variable,
.text-editor__content pre code .hljs-template-variable,
.text-editor__content pre code .hljs-params {
    color: #0f172a;
}

.text-editor__content pre code .hljs-meta {
    color: #6d28d9;
}

.text-editor__content pre code .hljs-deletion {
    color: #b91c1c;
}

.text-editor__content pre code .hljs-symbol,
.text-editor__content pre code .hljs-bullet {
    color: #0369a1;
}

.dark .text-editor__content pre code .hljs-keyword,
.dark .text-editor__content pre code .hljs-selector-tag,
/* stylelint-disable-next-line selector-class-pattern -- highlight.js token classes carry underscores by design */
.dark .text-editor__content pre code .hljs-built_in,
.dark .text-editor__content pre code .hljs-type {
    color: #c084fc;
}

.dark .text-editor__content pre code .hljs-string,
.dark .text-editor__content pre code .hljs-regexp,
.dark .text-editor__content pre code .hljs-addition,
.dark .text-editor__content pre code .hljs-attribute {
    color: #4ade80;
}

.dark .text-editor__content pre code .hljs-number,
.dark .text-editor__content pre code .hljs-literal {
    color: #fb923c;
}

.dark .text-editor__content pre code .hljs-title,
/* stylelint-disable-next-line selector-class-pattern -- highlight.js token classes carry underscores by design */
.dark .text-editor__content pre code .hljs-title.function_,
.dark .text-editor__content pre code .hljs-section,
.dark .text-editor__content pre code .hljs-function .hljs-title {
    color: #93c5fd;
}

.dark .text-editor__content pre code .hljs-comment,
.dark .text-editor__content pre code .hljs-quote {
    color: #94a3b8;
}

.dark .text-editor__content pre code .hljs-variable,
.dark .text-editor__content pre code .hljs-template-variable,
.dark .text-editor__content pre code .hljs-params {
    color: #e2e8f0;
}

.dark .text-editor__content pre code .hljs-meta {
    color: #a78bfa;
}

.dark .text-editor__content pre code .hljs-deletion {
    color: #f87171;
}

.dark .text-editor__content pre code .hljs-symbol,
.dark .text-editor__content pre code .hljs-bullet {
    color: #38bdf8;
}

/* The drag-resize handles of the image node view (ADR-0024 — Tiptap's
   built-in ResizableNodeView renders them as bare positioned divs;
   the corners get the grab affordance, visible on hover / while the
   image is selected / during a resize). The resized width is an
   editor-session aid — the markdown round trip drops it (ADR-0024). */
.text-editor__content [data-resize-wrapper] {
    display: inline-block;
    max-width: 100%;
    margin: 0.75rem 0;
}

.text-editor__content [data-resize-wrapper] img {
    display: block;
    max-width: 100%;
    border-radius: 0.5rem;
}

.text-editor__content [data-resize-handle] {
    position: absolute;
    width: 12px;
    height: 12px;
    border-radius: 9999px;
    background: #2563eb;
    border: 2px solid #fff;
    box-shadow: 0 1px 3px rgb(0 0 0 / 30%);
    opacity: 0;
    transition: opacity 0.15s;
}

.text-editor__content [data-resize-wrapper]:hover [data-resize-handle],
.text-editor__content [data-resize-state='true'] [data-resize-handle] {
    opacity: 1;
}

.text-editor__content [data-resize-handle="bottom-right"],
.text-editor__content [data-resize-handle="top-left"] {
    cursor: nwse-resize;
}

.text-editor__content [data-resize-handle="bottom-left"],
.text-editor__content [data-resize-handle="top-right"] {
    cursor: nesw-resize;
}

.text-editor__content img {
    display: block;
    max-width: 100%;
    border-radius: 0.5rem;
    margin: 0.75rem 0;
}

.text-editor__content hr {
    border-color: #e2e8f0;
    margin: 1rem 0;
}

.dark .text-editor__content blockquote {
    border-left-color: #475569;
    color: #94a3b8;
}

.dark .text-editor__content th,
.dark .text-editor__content td {
    border-color: #334155;
}

.dark .text-editor__content th {
    background: #1e293b;
}

.dark .text-editor__content pre {
    background: #1e293b;
    color: #e2e8f0;
    border-color: #334155;
    scrollbar-color: #475569 transparent;
}

.dark .text-editor__content pre::-webkit-scrollbar-thumb {
    background-color: #475569;
}

.dark .text-editor__content code {
    background: #1e293b;
}

.dark .text-editor__content hr {
    border-color: #334155;
}
</style>
