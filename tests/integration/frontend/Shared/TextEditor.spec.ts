import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import TextEditor from '@shared-admin/TextEditor.vue';

function mountEditor(props = {}) {
    return mount(TextEditor, {
        props: { modelValue: '', ...props },
    });
}

// Tiptap v3's EditorContent moves the ProseMirror view DOM into the
// container inside a watchEffect + nextTick, so the contenteditable
// only exists after a few ticks past mount.
async function waitForEditable(wrapper) {
    await vi.waitFor(() => {
        expect(wrapper.get('[contenteditable="true"]').exists()).toBe(true);
    });
}

describe('TextEditor', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('renders the label', () => {
        const wrapper = mountEditor({ label: 'Post body' });

        expect(wrapper.text()).toContain('Post body');
    });

    it('shows the placeholder when the content is empty', () => {
        const wrapper = mountEditor();

        expect(wrapper.text()).toContain('Start writing...');
    });

    it('renders the initial markdown content into the editor', async () => {
        const wrapper = mountEditor({ modelValue: '# Welcome\n\nSome **bold** text' });

        await waitForEditable(wrapper);
        const editable = wrapper.get('[contenteditable="true"]');
        expect(editable.find('h1').exists()).toBe(true);
        expect(editable.find('strong').exists()).toBe(true);
    });

    it('shows the error message with role alert', () => {
        const wrapper = mountEditor({ error: 'Body is required' });

        expect(wrapper.get('[data-testid="text-editor-error"]').attributes('role')).toBe('alert');
        expect(wrapper.get('[data-testid="text-editor-error"]').text()).toBe('Body is required');
    });

    it('shows the help text when there is no error', () => {
        const wrapper = mountEditor({ help: 'Supports Markdown' });

        expect(wrapper.text()).toContain('Supports Markdown');
    });

    it('shows the skeleton instead of the editor when is_loading is true', () => {
        const wrapper = mountEditor({ is_loading: true });

        expect(wrapper.find('[data-testid="text-editor-skeleton"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor"]').exists()).toBe(false);
    });

    it('renders the toolbar with Bold, Italic and Code buttons', () => {
        const wrapper = mountEditor();

        expect(wrapper.get('[data-testid="text-editor-bold"]').attributes('aria-label')).toBe('Bold');
        expect(wrapper.get('[data-testid="text-editor-bold"]').text()).toBe('B');
        expect(wrapper.get('[data-testid="text-editor-italic"]').attributes('aria-label')).toBe('Italic');
        expect(wrapper.get('[data-testid="text-editor-italic"]').text()).toBe('I');
        expect(wrapper.get('[data-testid="text-editor-code"]').attributes('aria-label')).toBe('Code');
        expect(wrapper.get('[data-testid="text-editor-code"]').text()).toContain('</>');
    });

    it('renders the remaining toolbar controls', () => {
        const wrapper = mountEditor();

        expect(wrapper.get('[data-testid="text-editor-heading"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="text-editor-blockquote"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="text-editor-bullet-list"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="text-editor-ordered-list"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="text-editor-callout"]').exists()).toBe(true);
        expect(wrapper.get('[data-testid="text-editor-image"]').attributes('aria-label')).toBe(
            'Insert image',
        );
        expect(wrapper.get('[data-testid="text-editor-image-input"]').attributes('accept')).toBe(
            'image/*',
        );
    });

    it('emits image-upload with the chosen file', async () => {
        const wrapper = mountEditor();
        const file = new File(['binary'], 'photo.png', { type: 'image/png' });
        const input = wrapper.get('[data-testid="text-editor-image-input"]');

        Object.defineProperty(input.element, 'files', { value: [file], configurable: true });
        await input.trigger('change');

        expect(wrapper.emitted('image-upload')).toEqual([[file]]);
    });

    it('emits update:modelValue with markdown on editor changes', async () => {
        // Direct DOM text input relies on ProseMirror's MutationObserver +
        // composition pipeline, which happy-dom delivers asynchronously and
        // races under load. A toolbar command drives a real editor
        // transaction deterministically: toggling a bullet list wraps the
        // paragraph and serializes it back to markdown.
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-bullet-list"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(
            wrapper
                .emitted('update:modelValue')
                ?.some(([value]) => value.includes('- Hello world')),
        ).toBe(true);
    });

    it('emits focus and blur from the editor', async () => {
        const wrapper = mountEditor();
        await waitForEditable(wrapper);
        const editable = wrapper.get('[contenteditable="true"]');

        await editable.trigger('focus');
        expect(wrapper.emitted('focus')).toBeDefined();

        await editable.trigger('blur');
        expect(wrapper.emitted('blur')).toBeDefined();
    });

    it('hides the toolbar and dims the editor when disabled', () => {
        const wrapper = mountEditor({ disabled: true });

        expect(wrapper.get('[data-testid="text-editor"]').classes()).toContain('opacity-45');
        expect(wrapper.get('[data-testid="text-editor"]').classes()).toContain('pointer-events-none');
        expect(wrapper.find('[role="toolbar"]').exists()).toBe(false);
        expect(wrapper.text()).not.toContain('Start writing...');
    });
});
