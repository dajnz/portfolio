import DropzoneUpload from '@shared-admin/DropzoneUpload.vue';
import { mount } from '@vue/test-utils';
import { createPinia, setActivePinia } from 'pinia';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';

const UPLOADED = { file_path: 'uploads/photo.png', file_name: 'photo.png' };

function mountDropzone(props: Record<string, unknown> = {}) {
    return mount(DropzoneUpload, {
        props: { modelValue: null, ...props },
    });
}

function inputElement(wrapper: ReturnType<typeof mountDropzone>): HTMLInputElement {
    return wrapper.get('[data-testid="dropzone-input"]').element as HTMLInputElement;
}

async function selectFile(wrapper: ReturnType<typeof mountDropzone>, file: File): Promise<void> {
    const input = inputElement(wrapper);
    // happy-dom has no HTMLInputElement.files setter and its DragEvent
    // constructor drops `dataTransfer` — define both directly on the
    // element/event instead.
    Object.defineProperty(input, 'files', { value: [file], configurable: true });
    input.dispatchEvent(new Event('change'));
    await nextTick();
}

describe('DropzoneUpload', () => {
    beforeEach(() => {
        setActivePinia(createPinia());
    });

    it('shows the drag and drop prompt with the label and help text', () => {
        const wrapper = mountDropzone({ label: 'Photo', help: 'Max 3 MB.' });

        expect(wrapper.text()).toContain('Drag and drop or click to browse');
        expect(wrapper.text()).toContain('Photo');
        expect(wrapper.text()).toContain('Max 3 MB.');
    });

    it('opens the file picker when the zone is clicked', async () => {
        const wrapper = mountDropzone();
        const clickSpy = vi
            .spyOn(inputElement(wrapper), 'click')
            .mockImplementation(() => undefined);

        await wrapper.get('[data-testid="dropzone"]').trigger('click');

        expect(clickSpy).toHaveBeenCalledTimes(1);
    });

    it('emits the selected file from the file input change', async () => {
        const wrapper = mountDropzone();
        const file = new File(['photo'], 'photo.png', { type: 'image/png' });

        await selectFile(wrapper, file);

        expect(wrapper.emitted('upload')).toEqual([[file]]);
    });

    it('rejects files larger than the max size with an error message', async () => {
        const wrapper = mountDropzone({ max_size: 10 });
        const file = new File([new Uint8Array(11)], 'huge.png', { type: 'image/png' });

        await selectFile(wrapper, file);

        expect(wrapper.emitted('upload')).toBeUndefined();
        expect(wrapper.get('[role="alert"]').text()).toBe('File is too large.');
    });

    it('emits the dropped file from a drop event', async () => {
        const wrapper = mountDropzone();
        const file = new File(['photo'], 'dropped.png', { type: 'image/png' });
        const transfer = new DataTransfer();
        transfer.items.add(file);
        const dropEvent = new DragEvent('drop', { bubbles: true, cancelable: true });
        Object.defineProperty(dropEvent, 'dataTransfer', { value: transfer, configurable: true });

        await wrapper.get('[data-testid="dropzone"]').element.dispatchEvent(dropEvent);

        expect(wrapper.emitted('upload')).toEqual([[file]]);
    });

    it('shows the preview image and file name for an uploaded image', () => {
        const wrapper = mountDropzone({ modelValue: UPLOADED });

        const preview = wrapper.get('[data-testid="dropzone-preview"]');

        expect(preview.attributes('src')).toBe('/assets/uploads/photo.png');
        expect(preview.attributes('alt')).toBe('photo.png');
        expect(wrapper.text()).toContain('photo.png');
    });

    it('removes the uploaded file and emits update:modelValue null', async () => {
        const wrapper = mountDropzone({ modelValue: UPLOADED });

        await wrapper.get('[data-testid="dropzone-remove"]').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([[null]]);
        expect(wrapper.emitted('remove')).toHaveLength(1);
    });

    it('opens the file picker from the replace button', async () => {
        const wrapper = mountDropzone({ modelValue: UPLOADED });
        const clickSpy = vi
            .spyOn(inputElement(wrapper), 'click')
            .mockImplementation(() => undefined);

        await wrapper.get('[data-testid="dropzone-replace"]').trigger('click');

        expect(clickSpy).toHaveBeenCalledTimes(1);
    });

    it('shows the uploading progress bar while loading', () => {
        const wrapper = mountDropzone({ is_loading: true });

        const bar = wrapper.get('[role="progressbar"]');

        expect(wrapper.text()).toContain('Uploading...');
        expect(bar.attributes('aria-valuenow')).toBeUndefined();
    });

    it('does not open the file picker while loading', async () => {
        const wrapper = mountDropzone({ is_loading: true });
        const clickSpy = vi
            .spyOn(inputElement(wrapper), 'click')
            .mockImplementation(() => undefined);

        await wrapper.get('[data-testid="dropzone"]').trigger('click');

        expect(clickSpy).not.toHaveBeenCalled();
    });
});
