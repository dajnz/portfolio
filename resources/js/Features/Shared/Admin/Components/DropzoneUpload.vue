<!-- DropzoneUpload (admin-section-components.md:166-193) — drag-and-drop file upload targeting /api/upload. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { ref } from 'vue';

import ProgressBar from './ProgressBar.vue';

export interface UploadedFile {
    file_path: string;
    file_name: string;
}

const props = withDefaults(
    defineProps<{
        modelValue: UploadedFile | null;
        accept?: string;
        max_size?: number;
        label?: string;
        error?: string | null;
        required?: boolean;
        help?: string;
        is_loading?: boolean;
        test_id?: string;
    }>(),
    {
        accept: 'image/*',
        max_size: 3145728,
        label: undefined,
        error: null,
        required: false,
        help: undefined,
        is_loading: false,
        test_id: 'dropzone',
    },
);

const emit = defineEmits<{
    'update:modelValue': [value: UploadedFile | null];
    upload: [file: File];
    remove: [];
}>();

const { t } = useI18n();

const isDragging = ref(false);
const sizeError = ref<string | null>(null);
const inputEl = ref<HTMLInputElement | null>(null);

function handleFile(file: File | undefined): void {
    if (file === undefined) {
        return;
    }

    if (file.size > props.max_size) {
        sizeError.value = t('admin.upload.file_too_large');
        return;
    }

    sizeError.value = null;
    emit('upload', file);
}

function onDrop(event: DragEvent): void {
    isDragging.value = false;
    handleFile(event.dataTransfer?.files[0]);
}

function openPicker(): void {
    inputEl.value?.click();
}

function removeFile(): void {
    emit('update:modelValue', null);
    emit('remove');
}

function isImage(): boolean {
    return (
        props.modelValue !== null &&
        props.modelValue.file_path.match(/\.(png|jpe?g|gif|webp|svg|avif)$/i) !== null
    );
}

function previewUrl(): string {
    return `/assets/${props.modelValue?.file_path ?? ''}`;
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
    <div
      :data-testid="props.test_id"
      :class="[
        {
          'border-danger-300 dark:border-danger-700': sizeError !== null || props.error !== null,
          'border-primary-500 bg-primary-50/30 dark:bg-primary-950/30': isDragging,
          'cursor-pointer': !props.is_loading,
        },
      ]"
      class="border-2 border-dashed border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center bg-slate-50 dark:bg-surface-dark-secondary hover:border-primary-400 dark:hover:border-primary-600 hover:bg-primary-50/30 dark:hover:bg-primary-950/30 transition-all"
      @click="props.is_loading ? undefined : openPicker()"
      @dragover.prevent="isDragging = true"
      @dragleave="isDragging = false"
      @drop.prevent="onDrop"
    >
      <input
        ref="inputEl"
        type="file"
        class="hidden"
        :accept="props.accept"
        :data-testid="`${props.test_id}-input`"
        @change="handleFile(($event.target as HTMLInputElement).files?.[0])"
      >
      <template v-if="props.is_loading">
        <div class="text-2xl mb-2 text-slate-300 dark:text-slate-600">
          <Icon
            class="w-8 h-8 mx-auto"
            icon="heroicons:arrow-path"
            aria-hidden="true"
          />
        </div>
        <ProgressBar
          :value="null"
          :label="t('admin.upload.uploading')"
        />
      </template>
      <template v-else-if="props.modelValue !== null">
        <div class="flex flex-col items-center gap-3">
          <img
            v-if="isImage()"
            class="max-h-32 mx-auto rounded-lg"
            :src="previewUrl()"
            :alt="props.modelValue.file_name"
            :data-testid="`${props.test_id}-preview`"
          >
          <span
            v-else
            class="text-4xl text-slate-300 dark:text-slate-600"
            aria-hidden="true"
          >
            <Icon
              class="w-10 h-10 mx-auto"
              icon="heroicons:document-text"
            />
          </span>
          <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">
            {{ props.modelValue.file_name }}
          </p>
          <div class="flex items-center justify-center gap-2">
            <button
              type="button"
              class="h-8 px-3 text-xs font-semibold rounded-md bg-white dark:bg-surface-dark-secondary border border-slate-200 dark:border-slate-700 text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors"
              :data-testid="`${props.test_id}-replace`"
              @click.stop="openPicker()"
            >
              {{ t('admin.upload.replace') }}
            </button>
            <button
              type="button"
              class="h-8 px-3 text-xs font-semibold rounded-md bg-danger-600 hover:bg-danger-700 text-white transition-colors"
              :aria-label="t('admin.upload.remove')"
              :data-testid="`${props.test_id}-remove`"
              @click.stop="removeFile()"
            >
              {{ t('admin.upload.remove') }}
            </button>
          </div>
          <slot />
        </div>
      </template>
      <template v-else>
        <div class="text-2xl mb-2 text-slate-300 dark:text-slate-600">
          <Icon
            class="w-8 h-8 mx-auto"
            icon="heroicons:arrow-up-tray"
            aria-hidden="true"
          />
        </div>
        <p class="text-sm text-slate-500 dark:text-slate-400 font-medium">
          {{ t('admin.upload.drag_drop_or_browse') }}
        </p>
        <p
          v-if="props.help"
          class="text-xs text-slate-400 dark:text-slate-500 mt-1"
        >
          {{ props.help }}
        </p>
      </template>
    </div>
    <p
      v-if="sizeError !== null"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
    >
      {{ sizeError }}
    </p>
    <p
      v-else-if="props.error !== null"
      class="mt-1.5 text-xs text-danger-600 dark:text-danger-400 font-medium"
      role="alert"
    >
      {{ props.error }}
    </p>
  </div>
</template>
