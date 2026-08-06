/**
 * Tiptap markdown-extension typing (3.29.x): the `markdown` key on Node
 * configs is a runtime contract of @tiptap/markdown, but the shipped
 * `NodeConfig` type does not declare it. The augmentation restores the
 * type for the local callout extension (TextEditor.vue).
 */
import type { MarkdownExtensionSpec } from '@tiptap/core';

declare module '@tiptap/core' {
    interface NodeConfig {
        markdown?: MarkdownExtensionSpec;
    }
}
