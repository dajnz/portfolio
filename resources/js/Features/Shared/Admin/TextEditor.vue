<!-- TextEditor (admin-section-components.md:217-249) — Tiptap Markdown WYSIWYG editor with callouts
     (`> [!WARNING]` marker syntax), lowlight code blocks with language selector, and image upload
     events. The `is_loading` prop drives the spec's `loading` skeleton state (gap-fill — the spec
     documents the state without a triggering prop). -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import type { Editor as CoreEditor, MarkdownToken, MarkdownTokenizer } from '@tiptap/core';
import { mergeAttributes, Node } from '@tiptap/core';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import Image from '@tiptap/extension-image';
import { Markdown } from '@tiptap/markdown';
import StarterKit from '@tiptap/starter-kit';
import { Editor, EditorContent } from '@tiptap/vue-3';
import { common, createLowlight } from 'lowlight';
import { computed, onBeforeUnmount, ref, watch } from 'vue';

import AdminBaseDropdown from './AdminBaseDropdown.vue';
import AdminBaseSkeleton from './AdminBaseSkeleton.vue';

const CALLOUT_TYPES = ['warning', 'tip', 'important', 'tradeoff'] as const;
type CalloutType = (typeof CALLOUT_TYPES)[number];

const CALLOUT_MARKER = /^[ \t]*>[ \t]*\[!(WARNING|TIP|IMPORTANT|TRADEOFF)\][ \t]*$/i;
const CALLOUT_INLINE_MARKER = /^\[!(WARNING|TIP|IMPORTANT|TRADEOFF)\][ \t]*$/i;

const CALLOUT_CLASSES: Record<CalloutType, string> = {
    warning:
        'border-warning-500 bg-warning-50 dark:bg-warning-950 text-warning-700 dark:text-warning-400',
    tip: 'border-success-500 bg-success-50 dark:bg-success-950 text-success-700 dark:text-success-400',
    important:
        'border-primary-500 bg-primary-50 dark:bg-primary-950 text-primary-700 dark:text-primary-300',
    tradeoff:
        'border-slate-300 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/50 text-slate-600 dark:text-slate-300',
};

const CODE_LANGUAGES: Array<{ value: string; label: string }> = [
    { value: '', label: 'none' },
    { value: 'javascript', label: 'JavaScript' },
    { value: 'typescript', label: 'TypeScript' },
    { value: 'php', label: 'PHP' },
    { value: 'python', label: 'Python' },
    { value: 'java', label: 'Java' },
    { value: 'go', label: 'Go' },
    { value: 'rust', label: 'Rust' },
    { value: 'c', label: 'C' },
    { value: 'cpp', label: 'C++' },
    { value: 'css', label: 'CSS' },
    { value: 'html', label: 'HTML' },
    { value: 'json', label: 'JSON' },
    { value: 'bash', label: 'Bash' },
    { value: 'sql', label: 'SQL' },
    { value: 'ruby', label: 'Ruby' },
    { value: 'markdown', label: 'Markdown' },
];

const lowlight = createLowlight(common);

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
const activeMarks = ref<Record<string, boolean>>({});
const isCodeBlockActive = ref(false);
const activeCodeLanguage = ref('');
const isCalloutActive = ref(false);
const activeCalloutType = ref<CalloutType>('warning');
const isEmpty = ref(true);

const placeholderText = computed(() => props.placeholder ?? t('admin.editor.placeholder'));

function calloutTokenizer(): MarkdownTokenizer {
    return {
        name: 'callout',
        level: 'block',
        start(src: string): number {
            return CALLOUT_MARKER.test(src) ? 0 : -1;
        },
        tokenize(src: string, _tokens: MarkdownToken[], lexer) {
            const lines = src.split('\n');
            const contentLines: string[] = [];
            let consumed = 0;
            let index = 0;
            const blockquoteLine = /^[ \t]*>[ \t]?(.*)$/;

            while (index < lines.length) {
                const match = blockquoteLine.exec(lines[index]);

                if (match !== null) {
                    contentLines.push(match[1]);
                    consumed += lines[index].length + 1;
                    index += 1;
                    continue;
                }

                if (lines[index].trim() === '') {
                    const next = index + 1;

                    if (next < lines.length && blockquoteLine.test(lines[next])) {
                        contentLines.push('');
                        consumed += lines[index].length + 1;
                        index += 1;
                        continue;
                    }
                }

                break;
            }

            if (index >= lines.length && !src.endsWith('\n')) {
                consumed -= 1;
            }

            const marker = CALLOUT_INLINE_MARKER.exec(contentLines[0] ?? '');

            if (marker === null) {
                return undefined;
            }

            const type = marker[1].toLowerCase() as CalloutType;
            contentLines.shift();

            return {
                type: 'callout',
                calloutType: type,
                raw: src.slice(0, Math.max(consumed, 0)),
                tokens: lexer.blockTokens(contentLines.join('\n')),
            };
        },
    };
}

const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,
    addAttributes() {
        return {
            type: {
                default: 'warning',
            },
        };
    },
    parseHTML() {
        return [
            {
                tag: 'div[data-callout]',
                getAttrs: (element) => ({
                    type: (element as HTMLElement).getAttribute('data-callout') ?? 'warning',
                }),
            },
        ];
    },
    renderHTML({ HTMLAttributes }) {
        const type = (HTMLAttributes.type as CalloutType) ?? 'warning';

        return [
            'div',
            mergeAttributes(HTMLAttributes, {
                'data-callout': type,
                class: `border-l-4 px-4 py-3 my-4 rounded-r-lg ${CALLOUT_CLASSES[type]}`,
            }),
            0,
        ];
    },
    markdown: {
        tokenName: 'callout',
        tokenizer: calloutTokenizer(),
        parseMarkdown: (token, helpers) =>
            helpers.createNode(
                'callout',
                { type: token.calloutType ?? 'warning' },
                helpers.parseChildren(token.tokens ?? []),
            ),
        renderMarkdown: (node, helpers) => {
            const type = String((node.attrs?.type as CalloutType) ?? 'warning').toUpperCase();
            const body = helpers.renderChildren(node.content ?? []);
            const lines = body.split('\n').map((line) => (line.trim() === '' ? '>' : `> ${line}`));

            return `> [!${type}]\n${lines.join('\n')}`;
        },
    },
});

function refreshState(editor: CoreEditor): void {
    activeMarks.value = {
        bold: editor.isActive('bold'),
        italic: editor.isActive('italic'),
        code: editor.isActive('code'),
        heading: editor.isActive('heading'),
        blockquote: editor.isActive('blockquote'),
        bulletList: editor.isActive('bulletList'),
        orderedList: editor.isActive('orderedList'),
    };
    isCodeBlockActive.value = editor.isActive('codeBlock');
    activeCodeLanguage.value = isCodeBlockActive.value
        ? String(editor.getAttributes('codeBlock').language ?? '')
        : '';
    isCalloutActive.value = editor.isActive('callout');
    activeCalloutType.value = isCalloutActive.value
        ? ((editor.getAttributes('callout').type as CalloutType) ?? 'warning')
        : 'warning';
    isEmpty.value = editor.isEmpty;
}

const editor =
    typeof window === 'undefined'
        ? null
        : new Editor({
              extensions: [
                  StarterKit.configure({ codeBlock: false }),
                  lowlightExtension(),
                  Image,
                  Callout,
                  Markdown,
              ],
              content: props.modelValue,
              contentType: 'markdown',
              editorProps: {
                  attributes: {
                      class: 'max-w-none focus:outline-none min-h-[200px]',
                  },
              },
              onUpdate: ({ editor: instance }) => {
                  emit('update:modelValue', instance.getMarkdown());
                  refreshState(instance);
              },
              onSelectionUpdate: ({ editor: instance }) => refreshState(instance),
              onFocus: () => emit('focus'),
              onBlur: () => emit('blur'),
          });

function lowlightExtension(): ReturnType<typeof CodeBlockLowlight.configure> {
    return CodeBlockLowlight.configure({ lowlight });
}

watch(
    () => props.disabled,
    (disabled) => {
        editor?.setEditable(!disabled);
    },
    { immediate: true },
);

watch(
    () => props.modelValue,
    (value) => {
        if (editor !== null && editor.getMarkdown() !== value) {
            editor.commands.setContent(value, { contentType: 'markdown' });
        }
    },
);

onBeforeUnmount(() => {
    editor?.destroy();
});

function toggleMark(type: string): void {
    const chain = editor?.chain().focus();

    switch (type) {
        case 'bold':
            chain?.toggleBold().run();
            break;
        case 'italic':
            chain?.toggleItalic().run();
            break;
        case 'code':
            chain?.toggleCode().run();
            break;
        case 'heading':
            chain?.toggleHeading({ level: 2 }).run();
            break;
        case 'blockquote':
            chain?.toggleBlockquote().run();
            break;
        case 'bulletList':
            chain?.toggleBulletList().run();
            break;
        case 'orderedList':
            chain?.toggleOrderedList().run();
            break;
    }
}

function setCodeLanguage(value: string | string[]): void {
    editor
        ?.chain()
        .focus()
        .updateAttributes('codeBlock', { language: String(value) })
        .run();
}

function setCallout(value: string | string[]): void {
    const type = CALLOUT_TYPES.includes(value as CalloutType) ? (value as CalloutType) : 'warning';

    editor?.chain().focus().toggleNode('callout', 'paragraph', { type }).run();
}

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

const calloutOptions = computed(() =>
    CALLOUT_TYPES.map((type) => ({
        value: type,
        label: t(`admin.editor.callout_${type}`),
    })),
);

const activeButtonClass = 'bg-slate-200 dark:bg-slate-700';
const idleButtonClass =
    'text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-slate-700';
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
      :class="[
        {
          'border-danger-300 dark:border-danger-700': props.error !== null,
          'opacity-45 pointer-events-none': props.disabled,
        },
      ]"
      class="border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden bg-white dark:bg-surface-dark-secondary focus-within:border-primary-500 transition-all"
      :data-testid="props.test_id"
    >
      <div
        v-if="!props.disabled"
        class="flex items-center gap-1 px-3 py-2 border-b border-slate-100 dark:border-slate-700 bg-slate-50 dark:bg-slate-900/50 flex-wrap"
        role="toolbar"
        :aria-label="t('admin.editor.toolbar')"
      >
        <button
          type="button"
          :class="[`${idleButtonClass} font-bold`, { [activeButtonClass]: activeMarks.bold }]"
          class="select-none w-8 h-8 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.bold')"
          :aria-pressed="activeMarks.bold"
          :data-testid="`${props.test_id}-bold`"
          @click="toggleMark('bold')"
        >
          B
        </button>
        <button
          type="button"
          :class="[`${idleButtonClass} italic`, { [activeButtonClass]: activeMarks.italic }]"
          class="select-none w-8 h-8 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.italic')"
          :aria-pressed="activeMarks.italic"
          :data-testid="`${props.test_id}-italic`"
          @click="toggleMark('italic')"
        >
          I
        </button>
        <button
          type="button"
          :class="[`${idleButtonClass} font-mono`, { [activeButtonClass]: activeMarks.code }]"
          class="select-none w-8 h-8 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.code')"
          :aria-pressed="activeMarks.code"
          :data-testid="`${props.test_id}-code`"
          @click="toggleMark('code')"
        >
          &lt;/&gt;
        </button>
        <div class="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-1" />
        <button
          type="button"
          :class="[idleButtonClass, { [activeButtonClass]: activeMarks.heading }]"
          class="select-none w-8 h-8 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.heading')"
          :aria-pressed="activeMarks.heading"
          :data-testid="`${props.test_id}-heading`"
          @click="toggleMark('heading')"
        >
          H
        </button>
        <button
          type="button"
          :class="[idleButtonClass, { [activeButtonClass]: activeMarks.blockquote }]"
          class="select-none w-8 h-8 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.blockquote')"
          :aria-pressed="activeMarks.blockquote"
          :data-testid="`${props.test_id}-blockquote`"
          @click="toggleMark('blockquote')"
        >
          <Icon
            class="w-4 h-4"
            icon="heroicons:chat-bubble-oval-left-ellipsis"
            aria-hidden="true"
          />
        </button>
        <button
          type="button"
          :class="[idleButtonClass, { [activeButtonClass]: activeMarks.bulletList }]"
          class="select-none w-8 h-8 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.bullet_list')"
          :aria-pressed="activeMarks.bulletList"
          :data-testid="`${props.test_id}-bullet-list`"
          @click="toggleMark('bulletList')"
        >
          <Icon
            class="w-4 h-4"
            icon="heroicons:list-bullet"
            aria-hidden="true"
          />
        </button>
        <button
          type="button"
          :class="[idleButtonClass, { [activeButtonClass]: activeMarks.orderedList }]"
          class="select-none w-8 h-8 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.ordered_list')"
          :aria-pressed="activeMarks.orderedList"
          :data-testid="`${props.test_id}-ordered-list`"
          @click="toggleMark('orderedList')"
        >
          <Icon
            class="w-4 h-4"
            icon="heroicons:numbered-list"
            aria-hidden="true"
          />
        </button>
        <div class="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-1" />
        <button
          type="button"
          :class="idleButtonClass"
          class="select-none w-8 h-8 inline-flex items-center justify-center text-sm rounded transition-colors"
          :aria-label="t('admin.editor.insert_image')"
          :data-testid="`${props.test_id}-image`"
          @click="onImageClick"
        >
          <Icon
            class="w-4 h-4"
            icon="heroicons:photo"
            aria-hidden="true"
          />
        </button>
        <input
          ref="imageInputEl"
          type="file"
          accept="image/*"
          class="hidden"
          :data-testid="`${props.test_id}-image-input`"
          @change="onImageChange"
        >
        <div class="w-px h-5 bg-slate-200 dark:bg-slate-700 mx-1" />
        <button
          type="button"
          :class="[idleButtonClass, { [activeButtonClass]: isCalloutActive }]"
          class="select-none px-2 h-8 inline-flex items-center justify-center text-xs font-medium rounded transition-colors"
          :aria-label="t('admin.editor.callout')"
          :aria-pressed="isCalloutActive"
          :data-testid="`${props.test_id}-callout`"
          @click="setCallout('warning')"
        >
          [!]
        </button>
        <AdminBaseDropdown
          v-if="isCalloutActive"
          :model-value="activeCalloutType"
          :options="calloutOptions"
          :test_id="`${props.test_id}-callout-type`"
          @update:model-value="setCallout"
        />
        <AdminBaseDropdown
          v-if="isCodeBlockActive"
          :model-value="activeCodeLanguage"
          :options="CODE_LANGUAGES"
          :test_id="`${props.test_id}-code-language`"
          @update:model-value="setCodeLanguage"
        />
      </div>
      <div
        class="relative"
        :aria-invalid="props.error !== null || undefined"
        :data-testid="`${props.test_id}-content`"
      >
        <EditorContent
          v-if="editor !== null"
          :editor="editor"
          class="px-4 py-3 text-sm text-slate-800 dark:text-slate-200 leading-relaxed"
        />
        <p
          v-if="isEmpty && !props.disabled"
          class="absolute top-0 left-0 px-4 py-3 text-sm text-slate-400 dark:text-slate-600 pointer-events-none"
        >
          {{ placeholderText }}
        </p>
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
