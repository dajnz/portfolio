/**
 * The TextEditor's command composable: every editor command the
 * toolbar/popup can trigger (marks, lists, block formatting, the
 * callout wrap/lift, tables, images, links) plus the click handlers
 * that combine commands with the popup state machine (the code-block
 * and callout button state machines, and the popup apply
 * handlers). The active link URL for the popup's prefilled input.
 * The parent (TextEditor.vue) owns the editor instance, the popup
 * state and the mode; this composable translates UI events into
 * editor transactions.
 */

import { CALLOUT_TYPES, type CalloutType } from '@shared-admin-extensions/Callout';
import type { Editor as CoreEditor } from '@tiptap/core';
import { type ComputedRef, computed } from 'vue';
import type { EditorActiveState } from './useEditorInstance';
import type { UseEditorPopupApi } from './useEditorPopup';

export interface UseEditorCommandsOptions {
    editor: () => CoreEditor | null;
    /** The active-state snapshot (useEditorInstance). */
    state: EditorActiveState;
    /** The popup state machine (useEditorPopup). */
    popup: UseEditorPopupApi;
    /** The blog config uses the selectable H1–H6 popup; the projects
     *  config keeps the one-click H2 toggle. */
    selectableHeading: () => boolean;
}

export interface UseEditorCommandsApi {
    /** The active link URL — the popup's link row prefills from it
     *  (the Remove button shows while a link is active). */
    linkHref: ComputedRef<string>;
    toggleMark: (type: string) => void;
    toggleBlockquote: () => void;
    toggleCodeBlock: () => void;
    toggleList: (type: 'bulletList' | 'orderedList') => void;
    setCodeLanguage: (value: string) => void;
    setHeading: (value: string) => void;
    setCallout: (value: string) => void;
    insertTable: (rows?: number, cols?: number) => void;
    insertImage: (url: string) => void;
    onHeadingClick: (event: MouseEvent) => void;
    onListClick: (type: 'bulletList' | 'orderedList') => void;
    onMarkClick: (type: string) => void;
    onCodeBlockClick: (event: MouseEvent) => void;
    onCalloutClick: (event: MouseEvent) => void;
    onTableClick: (event: MouseEvent) => void;
    onLinkClick: (event: MouseEvent) => void;
    applyHeading: (value: string) => void;
    applyLink: (href: string) => void;
    removeLink: () => void;
    applyTable: (rows: number, cols: number) => void;
    switchCalloutType: (value: string) => void;
}

export function useEditorCommands(options: UseEditorCommandsOptions): UseEditorCommandsApi {
    const linkHref = computed<string>(() => {
        const instance = options.editor();

        return instance?.isActive('link') === true
            ? String(instance?.getAttributes('link').href ?? '')
            : '';
    });

    function toggleMark(type: string): void {
        const chain = options.editor()?.chain().focus();

        switch (type) {
            case 'bold':
                chain?.toggleBold().run();
                break;
            case 'italic':
                chain?.toggleItalic().run();
                break;
            case 'underline':
                chain?.toggleUnderline().run();
                break;
            case 'strike':
                chain?.toggleStrike().run();
                break;
            case 'subscript':
                chain?.toggleSubscript().run();
                break;
            case 'superscript':
                chain?.toggleSuperscript().run();
                break;
            case 'code':
                chain?.toggleCode().run();
                break;
        }
    }

    function toggleBlockquote(): void {
        options.editor()?.chain().focus().toggleBlockquote().run();
    }

    function toggleCodeBlock(): void {
        options.editor()?.chain().focus().toggleCodeBlock().run();
    }

    function toggleList(type: 'bulletList' | 'orderedList'): void {
        const chain = options.editor()?.chain().focus();

        if (type === 'bulletList') {
            chain?.toggleBulletList().run();
        } else {
            chain?.toggleOrderedList().run();
        }
    }

    function setCodeLanguage(value: string): void {
        options.editor()?.chain().focus().updateAttributes('codeBlock', { language: value }).run();
    }

    function setHeading(value: string): void {
        const level = Number(value);

        if (!Number.isInteger(level) || level < 1 || level > 6) {
            options.editor()?.chain().focus().setParagraph().run();
            return;
        }

        options
            .editor()
            ?.chain()
            .focus()
            .toggleHeading({ level: level as 1 | 2 | 3 | 4 | 5 | 6 })
            .run();
    }

    // The callout toggle: the Tiptap 3 `toggleNode` fallback to
    // `setNode` refuses non-text-block nodes with the "Currently
    // 'setNode()' only supports text block nodes" warning — a WRAP for
    // activation (the paragraph becomes callout[paragraph]) and a LIFT
    // for deactivation; switching the type on an active callout
    // updates the attribute in place instead of nesting.
    function setCallout(value: string): void {
        const type = CALLOUT_TYPES.includes(value as CalloutType)
            ? (value as CalloutType)
            : 'warning';
        const instance = options.editor();

        if (instance === null) {
            return;
        }

        if (instance.isActive('callout')) {
            if (instance.getAttributes('callout').type === type) {
                instance.chain().focus().lift('callout').run();
            } else {
                instance.chain().focus().updateAttributes('callout', { type }).run();
            }
            return;
        }

        instance.chain().focus().wrapIn('callout', { type }).run();
    }

    function insertTable(rows = 3, cols = 3): void {
        options.editor()?.chain().focus().insertTable({ rows, cols, withHeaderRow: true }).run();
    }

    // The parent's side of the image contract: the parent uploads the
    // emitted File and calls back with the RustFS path — `insertImage`
    // inserts it at the cursor and serializes to the `![alt](url)`
    // Markdown.
    function insertImage(url: string): void {
        options.editor()?.chain().focus().setImage({ src: url }).run();
    }

    function onHeadingClick(event: MouseEvent): void {
        if (options.selectableHeading()) {
            options.popup.toggle('heading', event);
            return;
        }

        // The single H button toggles H2 (the projects config).
        options.editor()?.chain().focus().toggleHeading({ level: 2 }).run();
    }

    function onListClick(type: 'bulletList' | 'orderedList'): void {
        toggleList(type);
    }

    function onMarkClick(type: string): void {
        toggleMark(type);
    }

    // The code-block button state machine: an inactive block is
    // toggled on AND the language popup opens; an active block reopens
    // the popup; a second click while the popup is open toggles the
    // block off. Same rule for the callout button + type popup. Both
    // forward the click event to the popup toggle — WITHOUT it the
    // fixed popup keeps its default (8, 52) anchor and lands at the
    // viewport top-left instead of under the button.
    function onCodeBlockClick(event: MouseEvent): void {
        if (options.state.isCodeBlockActive) {
            if (options.popup.mode.value === 'language') {
                toggleCodeBlock();
                options.popup.close();
            } else {
                options.popup.toggle('language', event);
            }
            return;
        }

        toggleCodeBlock();
        options.popup.toggle('language', event);
    }

    function onCalloutClick(event: MouseEvent): void {
        if (options.state.isCalloutActive) {
            if (options.popup.mode.value === 'callout') {
                setCallout(options.state.activeCalloutType);
                options.popup.close();
            } else {
                options.popup.toggle('callout', event);
            }
            return;
        }

        setCallout('warning');
        options.popup.toggle('callout', event);
    }

    function onTableClick(event: MouseEvent): void {
        options.popup.toggle('table', event);
    }

    function onLinkClick(event: MouseEvent): void {
        options.popup.toggle('link', event);
    }

    function applyHeading(value: string): void {
        setHeading(value);
        options.popup.close();
    }

    function applyLink(href: string): void {
        options.editor()?.chain().focus().extendMarkRange('link').setLink({ href }).run();
        options.popup.close();
    }

    function removeLink(): void {
        options.editor()?.chain().focus().extendMarkRange('link').unsetLink().run();
        options.popup.close();
    }

    function applyTable(rows: number, cols: number): void {
        insertTable(rows, cols);
        options.popup.close();
    }

    // The popup's type list: switching only — the highlighted (active)
    // type click must NOT lift the callout (the [!] button owns the
    // toggle-off).
    function switchCalloutType(value: string): void {
        const type = CALLOUT_TYPES.includes(value as CalloutType)
            ? (value as CalloutType)
            : 'warning';

        if (options.state.isCalloutActive && options.state.activeCalloutType !== type) {
            options.editor()?.chain().focus().updateAttributes('callout', { type }).run();
        }
    }

    return {
        linkHref,
        toggleMark,
        toggleBlockquote,
        toggleCodeBlock,
        toggleList,
        setCodeLanguage,
        setHeading,
        setCallout,
        insertTable,
        insertImage,
        onHeadingClick,
        onListClick,
        onMarkClick,
        onCodeBlockClick,
        onCalloutClick,
        onTableClick,
        onLinkClick,
        applyHeading,
        applyLink,
        removeLink,
        applyTable,
        switchCalloutType,
    };
}
