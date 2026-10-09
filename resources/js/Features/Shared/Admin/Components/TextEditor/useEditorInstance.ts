/**
 * The TextEditor's Tiptap instance composable: owns the editor lifecycle
 * (creation with the feature extensions, the onUpdate / onSelectionUpdate /
 * onFocus / onBlur wiring, the editable and content syncs, destruction),
 * the active-state tracking (the pressed marks/blocks the toolbar renders,
 * refreshed on every editor update and selection change), the placeholder
 * emptiness (`isDocumentEmpty` — only a doc with a single empty paragraph is
 * "empty", so a freshly inserted empty table / callout / code block keeps the
 * placeholder hidden) and the raw/WYSIWYG mode switch (the raw textarea is the
 * modelValue source of truth while visible; switching back re-parses it into
 * the editor).
 */
import type { Editor as CoreEditor } from '@tiptap/core';
import { isNodeEmpty } from '@tiptap/core';
import CodeBlockLowlight from '@tiptap/extension-code-block-lowlight';
import Image from '@tiptap/extension-image';
import Subscript from '@tiptap/extension-subscript';
import Superscript from '@tiptap/extension-superscript';
import { Table, TableCell, TableHeader, TableRow } from '@tiptap/extension-table';
import { TextStyle } from '@tiptap/extension-text-style';
import { Markdown } from '@tiptap/markdown';
import StarterKit from '@tiptap/starter-kit';
import { Editor as TiptapEditor } from '@tiptap/vue-3';
import { common, createLowlight } from 'lowlight';
import {
    nextTick,
    onBeforeUnmount,
    type Ref,
    reactive,
    ref,
    type ShallowRef,
    shallowRef,
    watch,
} from 'vue';

import { Callout, type CalloutType } from '../../EditorExtensions/Callout';

/** The toolbar's active-state snapshot — the reactive object the
 *  toolbar binds for its pressed states. */
export interface EditorActiveState {
    activeMarks: Record<string, boolean>;
    isCodeBlockActive: boolean;
    activeCodeLanguage: string;
    isCalloutActive: boolean;
    activeCalloutType: CalloutType;
    activeHeadingLevel: number;
    isRawMode: boolean;
}

export interface UseEditorInstanceOptions {
    /** The modelValue getter — watched for external content changes. */
    content: () => string;
    /** The editable state (the `disabled` prop inverted) — watched
     *  and pushed into `setEditable`. */
    editable: () => boolean;
    /** The editor's markdown output (skipped while the raw textarea
     *  owns the value — setEditable re-fires onUpdate on every
     *  `disabled` flip, which would clobber the raw value). */
    onUpdate: (markdown: string) => void;
    onFocus: () => void;
    onBlur: () => void;
    /** The raw textarea's input — the parent emits it as modelValue. */
    onRawInput: (value: string) => void;
    /** Runs before every mode switch (the parent closes the popup). */
    onBeforeModeSwitch: () => void;
}

export interface UseEditorInstanceApi {
    editor: ShallowRef<TiptapEditor | null>;
    state: EditorActiveState;
    /** Whether the doc holds nothing at all (the placeholder rule). */
    isEmpty: Ref<boolean>;
    rawValue: Ref<string>;
    toggleMode: () => void;
    onRawInput: (event: Event) => void;
    setContent: (markdown: string) => void;
    setEditable: (editable: boolean) => void;
    destroy: () => void;
}

// The subscript/superscript marks serialize to `<sub>`/`<sup>` HTML
// (CommonMark has no native syntax) — the public content pipeline
// claims those tag pairs as structured nodes, so the round trip
// survives the safe-mode renderer.
const SubscriptWithMarkdown = Subscript.extend({
    renderMarkdown: (node, helpers) => `<sub>${helpers.renderChildren(node)}</sub>`,
});

const SuperscriptWithMarkdown = Superscript.extend({
    renderMarkdown: (node, helpers) => `<sup>${helpers.renderChildren(node)}</sup>`,
});

const lowlight = createLowlight(common);

// The placeholder must show ONLY when the document holds nothing at
// all — Tiptap's `editor.isEmpty` (via `isNodeEmpty`) recurses into
// children, so a freshly inserted empty table / callout / code block
// still counts as "empty" and the placeholder reappears on blur.
// The doc is truly empty only when it contains a single empty
// paragraph (or no blocks at all).
function isDocumentEmpty(instance: CoreEditor): boolean {
    const doc = instance.state.doc;

    if (doc.content.childCount === 0) {
        return true;
    }

    if (doc.content.childCount > 1) {
        return false;
    }

    const firstChild = doc.firstChild;

    return (
        firstChild !== null &&
        firstChild.type === instance.schema.nodes.paragraph &&
        isNodeEmpty(firstChild)
    );
}

function lowlightExtension(): ReturnType<typeof CodeBlockLowlight.configure> {
    return CodeBlockLowlight.configure({ lowlight });
}

export function useEditorInstance(options: UseEditorInstanceOptions): UseEditorInstanceApi {
    const editor = shallowRef<TiptapEditor | null>(null);
    const rawValue = ref('');
    const isEmpty = ref(true);

    const state = reactive<EditorActiveState>({
        activeMarks: {},
        isCodeBlockActive: false,
        activeCodeLanguage: '',
        isCalloutActive: false,
        activeCalloutType: 'warning',
        activeHeadingLevel: 0,
        isRawMode: false,
    });

    function refreshState(instance: CoreEditor): void {
        state.activeMarks = {
            bold: instance.isActive('bold'),
            italic: instance.isActive('italic'),
            underline: instance.isActive('underline'),
            strike: instance.isActive('strike'),
            subscript: instance.isActive('subscript'),
            superscript: instance.isActive('superscript'),
            code: instance.isActive('code'),
            link: instance.isActive('link'),
        };
        state.isCodeBlockActive = instance.isActive('codeBlock');
        state.activeCodeLanguage = state.isCodeBlockActive
            ? String(instance.getAttributes('codeBlock').language ?? '')
            : '';
        state.isCalloutActive = instance.isActive('callout');
        state.activeCalloutType = state.isCalloutActive
            ? ((instance.getAttributes('callout').type as CalloutType) ?? 'warning')
            : 'warning';
        state.activeHeadingLevel = instance.isActive('heading')
            ? Number(instance.getAttributes('heading').level ?? 0)
            : 0;
        isEmpty.value = isDocumentEmpty(instance);
    }

    editor.value =
        typeof window === 'undefined'
            ? null
            : new TiptapEditor({
                  extensions: [
                      StarterKit.configure({
                          codeBlock: false,
                          // Links must not hijack clicks while editing.
                          link: { openOnClick: false },
                      }),
                      lowlightExtension(),
                      Image.configure({
                          resize: {
                              enabled: true,
                              minWidth: 80,
                              minHeight: 80,
                              alwaysPreserveAspectRatio: true,
                              directions: ['bottom-right', 'bottom-left', 'top-right', 'top-left'],
                          },
                      }),
                      Callout,
                      SubscriptWithMarkdown,
                      SuperscriptWithMarkdown,
                      TextStyle,
                      Table.configure({ resizable: true }),
                      TableRow,
                      TableCell,
                      TableHeader,
                      Markdown,
                  ],
                  content: options.content(),
                  contentType: 'markdown',
                  editorProps: {
                      attributes: {
                          class: 'max-w-none focus:outline-none min-h-[200px]',
                      },
                  },
                  onUpdate: ({ editor: instance }) => {
                      // setEditable fires onUpdate on every `disabled`
                      // flip (Tiptap dispatches an editable transaction
                      // and re-runs the update callbacks), re-emitting
                      // the CURRENT WYSIWYG doc — which in raw mode is
                      // stale (the editor isn't even mounted; the
                      // textarea owns the value). Emitting it would
                      // clobber the form's content on submit (isSaving
                      // disables the editor).
                      if (!state.isRawMode) {
                          options.onUpdate(instance.getMarkdown());
                      }
                      refreshState(instance);
                  },
                  onSelectionUpdate: ({ editor: instance }) => refreshState(instance),
                  onFocus: () => options.onFocus(),
                  onBlur: () => {
                      // The popup is NOT closed here: focusing the
                      // popup's own input (e.g. the link URL field)
                      // blurs the editor first, which would instantly
                      // close the just-opened popup. The outside-click
                      // capture listener covers clicks away from the
                      // popup (incl. clicking back into the content).
                      options.onBlur();
                  },
              });

    function setContent(markdown: string): void {
        editor.value?.commands.setContent(markdown, { contentType: 'markdown' });
    }

    function setEditable(editable: boolean): void {
        editor.value?.setEditable(editable);
    }

    function destroy(): void {
        editor.value?.destroy();
        editor.value = null;
    }

    // External content changes: the raw textarea mirrors them, the
    // WYSIWYG view re-parses them (only when they actually differ —
    // the editor's own output must not bounce back).
    watch(options.content, (value) => {
        if (state.isRawMode) {
            rawValue.value = value;
            return;
        }

        if (editor.value !== null && editor.value.getMarkdown() !== value) {
            setContent(value);
        }
    });

    watch(
        options.editable,
        (editable) => {
            setEditable(editable);
        },
        { immediate: true },
    );

    function toggleMode(): void {
        options.onBeforeModeSwitch();

        if (state.isRawMode) {
            state.isRawMode = false;
            nextTick(() => {
                setContent(rawValue.value);
            });
            return;
        }

        rawValue.value = editor.value?.getMarkdown() ?? options.content();
        state.isRawMode = true;
    }

    function onRawInput(event: Event): void {
        rawValue.value = (event.target as HTMLTextAreaElement).value;
        options.onRawInput(rawValue.value);
    }

    onBeforeUnmount(destroy);

    return {
        editor,
        state,
        isEmpty,
        rawValue,
        toggleMode,
        onRawInput,
        setContent,
        setEditable,
        destroy,
    };
}
