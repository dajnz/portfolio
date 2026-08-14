import { mount, type VueWrapper } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

import TextEditor, {
    BLOG_EDITOR_BUTTONS,
    PROJECT_EDITOR_BUTTONS,
    type TextEditorButton,
} from '@shared-admin/TextEditor/TextEditor.vue';

interface TextEditorProps {
    modelValue?: string;
    label?: string;
    error?: string | null;
    help?: string;
    placeholder?: string;
    disabled?: boolean;
    is_loading?: boolean;
    selectable_heading?: boolean;
    buttons?: TextEditorButton[];
}

let wrapper: VueWrapper | undefined;

function mountEditor(props: TextEditorProps = {}): VueWrapper {
    wrapper = mount(TextEditor, {
        props: { modelValue: '', ...props },
    });

    return wrapper;
}

// Tiptap v3's EditorContent moves the ProseMirror view DOM into the
// container inside a watchEffect + nextTick, so the contenteditable
// only exists after a few ticks past mount.
async function waitForEditable(activeWrapper: VueWrapper): Promise<void> {
    await vi.waitFor(() => {
        expect(activeWrapper.find('[contenteditable="true"]').exists()).toBe(true);
    });
}

describe('TextEditor', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    afterEach(() => {
        // Unmounting destroys the Tiptap editor. prosemirror-view schedules
        // a 20 ms focus-check timer on every focus event; without the
        // destroy (which nulls `docView`, short-circuiting that timer) it
        // can fire after vitest tears the happy-dom window down and crash
        // with "document is not defined".
        wrapper?.unmount();
        wrapper = undefined;
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
        expect(wrapper.get('[data-testid="text-editor-code"]').find('svg').exists()).toBe(true);
    });

    it('renders the remaining toolbar controls', () => {
        const wrapper = mountEditor();

        expect(wrapper.find('[data-testid="text-editor-heading"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-blockquote"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-bullet-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-ordered-list"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-code-block"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-callout"]').exists()).toBe(true);
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

    it('exposes the per-surface button configs', () => {
        // The projects config keeps the shared formatting but drops the
        // blog-only controls (image upload, tables, callouts — B231).
        expect(PROJECT_EDITOR_BUTTONS).toEqual([
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
        ]);
        expect(BLOG_EDITOR_BUTTONS).toEqual([
            ...PROJECT_EDITOR_BUTTONS.filter((button) => button !== 'link'),
            'image',
            'table',
            'callout',
            'link',
        ]);
    });

    it('renders the new B231 toolbar controls (underline/strike/sub/sup/table/link)', () => {
        const wrapper = mountEditor();

        expect(wrapper.get('[data-testid="text-editor-underline"]').attributes('aria-label')).toBe(
            'Underline',
        );
        expect(wrapper.get('[data-testid="text-editor-strike"]').attributes('aria-label')).toBe(
            'Strikethrough',
        );
        expect(wrapper.get('[data-testid="text-editor-subscript"]').attributes('aria-label')).toBe(
            'Subscript',
        );
        expect(
            wrapper.get('[data-testid="text-editor-superscript"]').attributes('aria-label'),
        ).toBe('Superscript');
        expect(wrapper.get('[data-testid="text-editor-table"]').attributes('aria-label')).toBe(
            'Insert table',
        );
        expect(wrapper.get('[data-testid="text-editor-link"]').attributes('aria-label')).toBe(
            'Link',
        );
    });

    it('opens the H1–H6 heading picker popup on the blog config', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world', selectable_heading: true });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-heading"]').trigger('click');

        const popup = wrapper.get('[data-testid="text-editor-popup"]');
        expect(popup.text()).toContain('Paragraph');
        expect(popup.text()).toContain('Heading 6');

        // Apply H3 (index 3 — 0 is Paragraph, 1–6 are the levels).
        await wrapper.get('[data-testid="text-editor-heading-option-3"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 30));

        expect(wrapper.get('[contenteditable="true"]').find('h3').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-popup"]').exists()).toBe(false);
    });

    it('round-trips the underline and strikethrough marks to markdown', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);
        const editable = wrapper.get('[contenteditable="true"]');

        // Mark toggles apply to the SELECTION — select the whole text
        // first (a collapsed cursor only arms the typing state).
        await editable.trigger('keydown', { key: 'a', ctrlKey: true });
        await wrapper.get('[data-testid="text-editor-underline"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(
            wrapper
                .emitted('update:modelValue')
                ?.some(([value]) => String(value).includes('++Hello world++')),
        ).toBe(true);

        await editable.trigger('keydown', { key: 'a', ctrlKey: true });
        await wrapper.get('[data-testid="text-editor-strike"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(
            wrapper
                .emitted('update:modelValue')
                ?.some(([value]) => String(value).includes('~~++Hello world++~~')),
        ).toBe(true);
    });

    it('serializes subscript and superscript as <sub>/<sup> HTML', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);
        const editable = wrapper.get('[contenteditable="true"]');

        await editable.trigger('keydown', { key: 'a', ctrlKey: true });
        await wrapper.get('[data-testid="text-editor-subscript"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(
            wrapper
                .emitted('update:modelValue')
                ?.some(([value]) => String(value).includes('<sub>Hello world</sub>')),
        ).toBe(true);

        await editable.trigger('keydown', { key: 'a', ctrlKey: true });
        await wrapper.get('[data-testid="text-editor-superscript"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(
            wrapper
                .emitted('update:modelValue')
                ?.some(([value]) => String(value).includes('<sup>Hello world</sup>')),
        ).toBe(true);
    });

    it('inserts a GFM table via the table-size grid popup', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-table"]').trigger('click');
        expect(wrapper.find('[data-testid="text-editor-table-grid"]').exists()).toBe(true);

        // Hover the 2×3 cell (row 2, col 3 = cell 9) — the label follows.
        await wrapper.get('[data-testid="text-editor-table-cell-9"]').trigger('mouseenter');
        expect(wrapper.get('[data-testid="text-editor-table-size-label"]').text()).toBe('2 × 3');

        await wrapper.get('[data-testid="text-editor-table-cell-9"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 30));

        const editable = wrapper.get('[contenteditable="true"]');
        expect(editable.find('table').exists()).toBe(true);
        // 2 rows total — the header row (th) + one body row.
        expect(editable.findAll('tr')).toHaveLength(2);
        expect(editable.find('th').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-popup"]').exists()).toBe(false);
    });

    it('sets a hyperlink on the selection via the link popup', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        // Select the text first — the link applies to the selection.
        await wrapper.get('[contenteditable="true"]').trigger('keydown', { key: 'a', ctrlKey: true });
        await wrapper.get('[data-testid="text-editor-link"]').trigger('click');
        expect(wrapper.find('[data-testid="text-editor-link-input"]').exists()).toBe(true);

        await wrapper.get('[data-testid="text-editor-link-input"]').setValue('https://example.com');
        await wrapper.get('[data-testid="text-editor-link-apply"]').trigger('click');

        expect(wrapper.find('[data-testid="text-editor-link-input"]').exists()).toBe(false);

        const editable = wrapper.get('[contenteditable="true"]');
        expect(editable.find('a[href="https://example.com"]').exists()).toBe(true);
    });

    it('keeps one config popup open at a time (switching buttons closes the previous)', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world', selectable_heading: true });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-heading"]').trigger('click');
        expect(wrapper.find('[data-testid="text-editor-heading-option-0"]').exists()).toBe(true);

        await wrapper.get('[data-testid="text-editor-link"]').trigger('click');
        expect(wrapper.find('[data-testid="text-editor-heading-option-0"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="text-editor-link-input"]').exists()).toBe(true);
    });

    it('closes the popup on an outside click and on Escape', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-table"]').trigger('click');
        expect(wrapper.find('[data-testid="text-editor-popup"]').exists()).toBe(true);

        document.body.click();
        await nextTick();
        expect(wrapper.find('[data-testid="text-editor-popup"]').exists()).toBe(false);

        await wrapper.get('[data-testid="text-editor-table"]').trigger('click');
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
        await nextTick();
        expect(wrapper.find('[data-testid="text-editor-popup"]').exists()).toBe(false);
    });

    it('shows the language list popup with the code block and switches the language', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-code-block"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 30));

        // The popup opens together with the block activation.
        const popup = wrapper.get('[data-testid="text-editor-popup"]');
        expect(popup.text()).toContain('none');
        // Anchored under the clicked button (fixed; happy-dom zero rects
        // → bottom 0 + 4px = top 4px) — the code-block popup previously
        // opened without the click event and landed at (8, 52), the
        // viewport top-left (2026-08-14 review find).
        expect(popup.attributes('style')).toContain('left: 8px');
        expect(popup.attributes('style')).toContain('top: 4px');
        // The thin themed scrollbar replaces the default one that hid the
        // popup's rounded right border (2026-08-14 review find).
        expect(popup.find('.thin-scrollbar').exists()).toBe(true);

        // php = index 3 in CODE_LANGUAGES (none, javascript, typescript, php).
        await wrapper.get('[data-testid="text-editor-language-option-3"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 30));

        expect(
            wrapper
                .emitted('update:modelValue')
                ?.some(([value]) => String(value).includes('```php')),
        ).toBe(true);
    });

    it('switches the callout type from the popup and lifts the callout on a second button click', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-callout"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 30));
        expect(
            wrapper.get('[contenteditable="true"]').find('[data-callout="warning"]').exists(),
        ).toBe(true);
        // The popup anchors to the CLICKED BUTTON's viewport rect (fixed)
        // — happy-dom rects are all zeros, so bottom 0 + 4px = top 4px;
        // a missing click event keeps the default (8, 52) anchor instead
        // (2026-08-14 review find — the callout/code popups never
        // forwarded the event and landed at the viewport top-left).
        expect(wrapper.get('[data-testid="text-editor-popup"]').attributes('style')).toContain(
            'left: 8px',
        );
        expect(wrapper.get('[data-testid="text-editor-popup"]').attributes('style')).toContain(
            'top: 4px',
        );

        // tip = index 1 in CALLOUT_TYPES (warning, tip, important, tradeoff).
        await wrapper.get('[data-testid="text-editor-callout-option-1"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 30));
        expect(wrapper.get('[contenteditable="true"]').find('[data-callout="tip"]').exists()).toBe(
            true,
        );

        // The second click on [!] while the popup is open lifts the callout.
        await wrapper.get('[data-testid="text-editor-callout"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 30));
        expect(wrapper.get('[contenteditable="true"]').find('[data-callout]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="text-editor-popup"]').exists()).toBe(false);
    });

    it('renders the callout node view with the translated label, icon and style-guide frame', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-callout"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 30));

        const callout = wrapper.get('[contenteditable="true"]').find('[data-callout="warning"]');
        // The label row: translated type name + the per-type icon.
        expect(callout.text()).toContain('Warning');
        expect(callout.find('svg').exists()).toBe(true);
        // The style-guide frame (style-guide.html #callouts): full radius,
        // 4px left border, --space-lg padding (2026-08-14 review find).
        expect(callout.classes()).toContain('rounded-[0.625rem]');
        expect(callout.classes()).toContain('border-l-4');
        expect(callout.classes()).toContain('px-6');
    });

    it('round-trips a callout through raw markdown (review find — the nested markdown config never reached the serializer)', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-callout"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(wrapper.get('[contenteditable="true"]').find('[data-callout]').exists()).toBe(true);

        // The WYSIWYG callout serializes to the `> [!TYPE]` marker syntax.
        await wrapper.get('[data-testid="text-editor-mode-toggle"]').trigger('click');
        await nextTick();
        const raw = wrapper.get('[data-testid="text-editor-raw"]');
        expect((raw.element as HTMLTextAreaElement).value).toContain('> [!WARNING]');
        expect((raw.element as HTMLTextAreaElement).value).toContain('> Hello world');

        // And back — the marker syntax re-parses into the callout node.
        await wrapper.get('[data-testid="text-editor-mode-toggle"]').trigger('click');
        await waitForEditable(wrapper);
        expect(wrapper.get('[contenteditable="true"]').find('[data-callout]').exists()).toBe(true);
    });

    it('keeps the placeholder hidden for an empty TABLE on blur (the block is content, not "nothing")', async () => {
        const wrapper = mountEditor();
        await waitForEditable(wrapper);
        const editable = wrapper.get('[contenteditable="true"]');

        await wrapper.get('[data-testid="text-editor-table"]').trigger('click');
        await wrapper.get('[data-testid="text-editor-table-cell-1"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        await editable.trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(wrapper.find('[data-testid="text-editor-placeholder"]').exists()).toBe(false);
    });

    it('keeps the placeholder hidden for an empty CALLOUT on blur', async () => {
        const wrapper = mountEditor();
        await waitForEditable(wrapper);
        const editable = wrapper.get('[contenteditable="true"]');

        await wrapper.get('[data-testid="text-editor-callout"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        await editable.trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(wrapper.find('[data-testid="text-editor-placeholder"]').exists()).toBe(false);
    });

    it('keeps the placeholder hidden for an empty CODE BLOCK on blur', async () => {
        const wrapper = mountEditor();
        await waitForEditable(wrapper);
        const editable = wrapper.get('[contenteditable="true"]');

        await wrapper.get('[data-testid="text-editor-code-block"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        await editable.trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(wrapper.find('[data-testid="text-editor-placeholder"]').exists()).toBe(false);

        // Deleting everything back to a single paragraph restores it.
        await editable.trigger('keydown', { key: 'a', ctrlKey: true });
        await editable.trigger('keydown', { key: 'Backspace' });
        await new Promise((resolve) => setTimeout(resolve, 20));
        await editable.trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(wrapper.find('[data-testid="text-editor-placeholder"]').exists()).toBe(true);
    });

    it('toggles a callout without the setNode warning (wrapIn/lift)', async () => {
        const consoleWarn = vi.spyOn(console, 'warn').mockImplementation(() => {});
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-callout"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(wrapper.get('[contenteditable="true"]').find('[data-callout]').exists()).toBe(true);
        expect(consoleWarn).not.toHaveBeenCalledWith(
            expect.stringContaining('only supports text block nodes'),
        );

        // Toggling off lifts the callout back to a paragraph.
        await wrapper.get('[data-testid="text-editor-callout"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));
        expect(wrapper.get('[contenteditable="true"]').find('[data-callout]').exists()).toBe(false);

        consoleWarn.mockRestore();
    });

    it('hides the placeholder on focus and restores it on blur while empty (B231)', async () => {
        const wrapper = mountEditor();
        await waitForEditable(wrapper);
        const editable = wrapper.get('[contenteditable="true"]');

        expect(wrapper.find('[data-testid="text-editor-placeholder"]').exists()).toBe(true);

        await editable.trigger('focus');
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(wrapper.find('[data-testid="text-editor-placeholder"]').exists()).toBe(false);

        await editable.trigger('blur');
        await new Promise((resolve) => setTimeout(resolve, 0));
        expect(wrapper.find('[data-testid="text-editor-placeholder"]').exists()).toBe(true);
    });

    it('renders only the buttons from the buttons prop', () => {
        const wrapper = mountEditor({ buttons: ['bold', 'image'] });

        expect(wrapper.find('[data-testid="text-editor-bold"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-image"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="text-editor-italic"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="text-editor-heading"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="text-editor-callout"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="text-editor-image-input"]').exists()).toBe(true);
    });

    it('renders the projects config without image upload or callouts', () => {
        const wrapper = mountEditor({ buttons: PROJECT_EDITOR_BUTTONS });

        expect(wrapper.find('[data-testid="text-editor-image"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="text-editor-image-input"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="text-editor-callout"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="text-editor-heading"]').exists()).toBe(true);
    });

    it('sets translated titles on the toolbar buttons', () => {
        const wrapper = mountEditor();

        expect(wrapper.get('[data-testid="text-editor-bold"]').attributes('title')).toBe('Bold');
        expect(wrapper.get('[data-testid="text-editor-italic"]').attributes('title')).toBe('Italic');
        expect(wrapper.get('[data-testid="text-editor-code"]').attributes('title')).toBe('Code');
        expect(wrapper.get('[data-testid="text-editor-heading"]').attributes('title')).toBe(
            'Heading',
        );
        expect(wrapper.get('[data-testid="text-editor-blockquote"]').attributes('title')).toBe(
            'Blockquote',
        );
        expect(wrapper.get('[data-testid="text-editor-bullet-list"]').attributes('title')).toBe(
            'Bullet list',
        );
        expect(wrapper.get('[data-testid="text-editor-ordered-list"]').attributes('title')).toBe(
            'Ordered list',
        );
        expect(wrapper.get('[data-testid="text-editor-image"]').attributes('title')).toBe(
            'Insert image',
        );
        expect(wrapper.get('[data-testid="text-editor-callout"]').attributes('title')).toBe(
            'Callout',
        );
    });

    it('switches to raw markdown and back, round-tripping the content', async () => {
        const wrapper = mountEditor({ modelValue: '# Title\n\nSome **bold** text' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-mode-toggle"]').trigger('click');

        const raw = wrapper.get('[data-testid="text-editor-raw"]');
        expect(raw.element.tagName).toBe('TEXTAREA');
        expect((raw.element as HTMLTextAreaElement).value).toContain('Some **bold** text');
        // The WYSIWYG surface is gone in raw mode.
        expect(wrapper.find('[contenteditable="true"]').exists()).toBe(false);

        // Editing the raw text emits markdown back to the parent.
        (raw.element as HTMLTextAreaElement).value = '# Title\n\n- one\n- two';
        await raw.trigger('input');
        expect(
            wrapper
                .emitted('update:modelValue')
                ?.some(([value]) => String(value).includes('- one')),
        ).toBe(true);

        // Switching back re-parses the raw markdown into the editor.
        await wrapper.get('[data-testid="text-editor-mode-toggle"]').trigger('click');
        await waitForEditable(wrapper);
        expect(wrapper.find('[data-testid="text-editor-raw"]').exists()).toBe(false);
        expect(wrapper.find('[contenteditable="true"]').element.innerHTML).toContain('<ul>');
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
                ?.some(([value]) => String(value).includes('- Hello world')),
        ).toBe(true);
    });

    it('emits fenced code block markdown when the code-block button is used', async () => {
        const wrapper = mountEditor({ modelValue: 'Hello world' });
        await waitForEditable(wrapper);

        await wrapper.get('[data-testid="text-editor-code-block"]').trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 20));

        expect(
            wrapper
                .emitted('update:modelValue')
                ?.some(([value]) => String(value).includes('```')),
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
