<!-- ProjectMediaSection — the Media card of the project form: the
     preview DropzoneUpload with per-locale alt fields and the
     reorderable screenshots gallery (compact rows by default, expand on
     click, sticky expand/collapse-all header). Owns the uploads
     (useJsonApi + toast) and the gallery UI state; the page
     orchestrator receives the updated values via emits. -->
<script setup lang="ts">
import { Icon } from '@iconify/vue';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminBaseTextarea from '@shared-admin/AdminBaseTextarea.vue';
import DropzoneUpload, { type UploadedFile } from '@shared-admin/DropzoneUpload.vue';
import SortableList from '@shared-cross/Components/SortableList.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { randomUuid } from '@shared-cross/Utils/randomUuid';
import { ref } from 'vue';

// A type alias (not an interface) — SortableList's `modelValue` is
// `Record<string, unknown>[]` and type aliases receive the implicit
// index signature interfaces lack.
export type ScreenshotRow = {
    uid: string;
    id?: number;
    image: string | null;
    alt_text: Record<string, string>;
    title: Record<string, string>;
    description: Record<string, string>;
};

const props = withDefaults(
    defineProps<{
        preview_image?: string | null;
        preview_image_alt: Record<string, string>;
        screenshots: ScreenshotRow[];
        locale: string;
        errors: Record<string, string>;
        disabled: boolean;
        test_id?: string;
    }>(),
    {
        preview_image: null,
        test_id: 'project-media-section',
    },
);

const emit = defineEmits<{
    'update:preview_image': [path: string | null];
    'update:preview_image_alt': [locale: string, value: string];
    'update:screenshots': [rows: ScreenshotRow[]];
    'remove:preview': [];
}>();

const { t } = useI18n();
const toast = useToast();
const { post: jsonPost } = useJsonApi();

const isUploading = ref(false);

// Collapsed-by-default screenshots (user request 2026-08-12): the
// expanded-uid set; a drag-end click must not toggle a row, so header
// clicks are suppressed briefly after a drag starts.
const expandedScreenshots = ref<Set<string>>(new Set());
let suppressScreenshotHeaderClickUntil = 0;

function fieldError(field: string): string | null {
    return props.errors[`${field}.${props.locale}`] ?? props.errors[field] ?? null;
}

function previewFileFor(image: string | null): UploadedFile | null {
    if (image === null) {
        return null;
    }

    return {
        file_path: image,
        file_name: image.split('/').pop() ?? image,
    };
}

async function uploadImage(file: File, entityType: string): Promise<string | null> {
    try {
        const body = new FormData();
        body.append('file', file);
        body.append('entity_type', entityType);

        const data = await jsonPost<{ data: { path: string; name: string } }>('/api/upload', {
            formData: body,
        });

        return data.data.path;
    } catch {
        toast.show(t('admin.toasts.upload_failed'), { variant: 'error' });

        return null;
    }
}

async function uploadPreview(file: File): Promise<void> {
    if (isUploading.value) {
        return;
    }

    isUploading.value = true;

    try {
        const path = await uploadImage(file, 'projects');

        if (path !== null) {
            emit('update:preview_image', path);
        }
    } finally {
        isUploading.value = false;
    }
}

function removePreview(): void {
    emit('remove:preview');
}

async function uploadScreenshot(row: ScreenshotRow, file: File): Promise<void> {
    const path = await uploadImage(file, 'projects');

    if (path === null) {
        return;
    }

    setScreenshotImage(row, path);
}

function setScreenshotImage(row: ScreenshotRow, path: string | null): void {
    emit(
        'update:screenshots',
        props.screenshots.map((item) => (item.uid === row.uid ? { ...item, image: path } : item)),
    );
}

function setScreenshotAlt(row: ScreenshotRow, value: string): void {
    updateScreenshotRow(row, { alt_text: { ...row.alt_text, [props.locale]: value } });
}

function setScreenshotTitle(row: ScreenshotRow, value: string): void {
    updateScreenshotRow(row, { title: { ...row.title, [props.locale]: value } });
}

function setScreenshotDescription(row: ScreenshotRow, value: string): void {
    updateScreenshotRow(row, { description: { ...row.description, [props.locale]: value } });
}

function updateScreenshotRow(row: ScreenshotRow, patch: Partial<ScreenshotRow>): void {
    emit(
        'update:screenshots',
        props.screenshots.map((item) => (item.uid === row.uid ? { ...item, ...patch } : item)),
    );
}

function addScreenshot(): void {
    const row = {
        uid: randomUuid(),
        image: null,
        alt_text: emptyLocaleMap(),
        title: emptyLocaleMap(),
        description: emptyLocaleMap(),
    };

    emit('update:screenshots', [...props.screenshots, row]);

    // A fresh screenshot opens expanded (the existing ones stay
    // collapsed by default — user request 2026-08-12).
    expandedScreenshots.value = new Set(expandedScreenshots.value).add(row.uid);
}

function removeScreenshot(index: number): void {
    emit(
        'update:screenshots',
        props.screenshots.filter((_, rowIndex) => rowIndex !== index),
    );
}

function emptyLocaleMap(): Record<string, string> {
    return Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, '']));
}

// ── Screenshot collapse (compact rows by default, expand on click) ──

function isScreenshotExpanded(uid: string): boolean {
    return expandedScreenshots.value.has(uid);
}

function expandScreenshot(uid: string): void {
    expandedScreenshots.value = new Set(expandedScreenshots.value).add(uid);
}

function collapseScreenshot(uid: string): void {
    const next = new Set(expandedScreenshots.value);
    next.delete(uid);
    expandedScreenshots.value = next;
}

function expandAllScreenshots(): void {
    expandedScreenshots.value = new Set(props.screenshots.map((row) => row.uid));
}

function collapseAllScreenshots(): void {
    expandedScreenshots.value = new Set();
}

// A drag ends with a click on the drop point — a click straight after a
// drag must not collapse the row under the mouse. The 150 ms window
// covers the dragstart→mouseup gap.
function onScreenshotDragStart(): void {
    suppressScreenshotHeaderClickUntil = Date.now() + 150;
}

function onScreenshotHeaderClick(uid: string): void {
    if (Date.now() < suppressScreenshotHeaderClickUntil) {
        return;
    }

    collapseScreenshot(uid);
}
</script>

<template>
  <AdminBaseCard :test_id="`${props.test_id}-section-media`">
    <h2 class="mb-4 text-lg font-medium text-slate-600 dark:text-slate-400">
      {{ t('admin.projects.media') }}
    </h2>
    <div class="space-y-4">
      <DropzoneUpload
        :model-value="previewFileFor(props.preview_image)"
        :label="t('admin.projects.preview_image')"
        accept="image/*"
        :is_loading="isUploading"
        :error="fieldError('preview_image')"
        :test_id="`${props.test_id}-preview-dropzone`"
        @update:model-value="(file) => emit('update:preview_image', file?.file_path ?? null)"
        @upload="uploadPreview"
        @remove="removePreview"
      >
        <div
          v-for="code in SUPPORTED_LOCALES"
          :key="`preview-alt-${code}`"
          class="w-full"
        >
          <AdminBaseInput
            :model-value="props.preview_image_alt[code] ?? ''"
            :label="`${t('admin.projects.alt_text')} [${code}]`"
            :error="fieldError('preview_image_alt')"
            :disabled="props.disabled"
            :maxlength="500"
            :test_id="`${props.test_id}-preview-alt-${code}`"
            @update:model-value="(value) => emit('update:preview_image_alt', code, value)"
          />
        </div>
      </DropzoneUpload>

      <!-- Screenshots gallery — reorderable via SortableList,
           collapsed by default with per-row expand on click and
           the sticky expand/collapse-all header (user request
           2026-08-12). -->
      <div :data-testid="`${props.test_id}-screenshots`">
        <div
          class="sticky top-[130px] z-30 mb-1.5 flex items-center justify-between bg-white py-1 dark:bg-surface-dark-secondary"
          :data-testid="`${props.test_id}-screenshots-header`"
        >
          <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ t('admin.projects.screenshots') }}
          </span>
          <div class="flex items-center gap-1">
            <AdminBaseButton
              type="button"
              variant="ghost"
              size="sm"
              :disabled="props.disabled"
              :test_id="`${props.test_id}-expand-all`"
              @click="expandAllScreenshots"
            >
              {{ t('admin.projects.expand_all') }}
            </AdminBaseButton>
            <AdminBaseButton
              type="button"
              variant="ghost"
              size="sm"
              :disabled="props.disabled"
              :test_id="`${props.test_id}-collapse-all`"
              @click="collapseAllScreenshots"
            >
              {{ t('admin.projects.collapse_all') }}
            </AdminBaseButton>
          </div>
        </div>
        <SortableList
          :model-value="props.screenshots"
          item_key="uid"
          handle=".screenshot-handle"
          :disabled="props.disabled"
          :classes="{
            container: 'space-y-3',
          }"
          :test_id="`${props.test_id}-screenshots-sortable`"
          @update:model-value="(rows) => emit('update:screenshots', rows as unknown as ScreenshotRow[])"
          @drag-start="onScreenshotDragStart"
        >
          <template #default="{ item, index }">
            <div
              class="rounded-xl border border-slate-200 dark:border-slate-700"
              :data-testid="`${props.test_id}-screenshot-${index}`"
            >
              <!-- Compact row — a click (except on the drag knob
                   or the delete button) expands the screenshot -->
              <div
                v-if="!isScreenshotExpanded((item as unknown as ScreenshotRow).uid)"
                class="flex cursor-pointer items-center gap-3 p-3"
                :data-testid="`${props.test_id}-screenshot-${index}-collapsed`"
                @click="expandScreenshot((item as unknown as ScreenshotRow).uid)"
              >
                <span
                  class="screenshot-handle cursor-grab text-sm text-slate-300 dark:text-slate-600"
                  aria-hidden="true"
                  @click.stop
                >
                  ⠿
                </span>
                <img
                  v-if="(item as unknown as ScreenshotRow).image !== null"
                  :src="`/assets/${(item as unknown as ScreenshotRow).image}`"
                  alt=""
                  class="h-10 w-16 rounded-md border border-slate-200 object-cover dark:border-slate-700"
                >
                <span
                  v-else
                  class="inline-flex h-10 w-16 items-center justify-center rounded-md border border-dashed border-slate-200 text-slate-300 dark:border-slate-700 dark:text-slate-600"
                  aria-hidden="true"
                >
                  <Icon
                    icon="heroicons:photo"
                    class="h-5 w-5"
                  />
                </span>
                <span
                  class="flex-1 truncate text-sm font-medium text-slate-700 dark:text-slate-200"
                  :data-testid="`${props.test_id}-screenshot-${index}-compact-title`"
                >
                  {{
                    (item as unknown as ScreenshotRow).title[props.locale]?.trim() !== ''
                      ? (item as unknown as ScreenshotRow).title[props.locale]
                      : t('admin.projects.screenshot_untitled')
                  }}
                </span>
                <button
                  type="button"
                  class="select-none inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-950 dark:hover:text-danger-400"
                  :aria-label="t('admin.projects.remove_screenshot')"
                  :data-testid="`${props.test_id}-screenshot-${index}-remove`"
                  @click.stop="removeScreenshot(index)"
                >
                  <svg
                    class="icon"
                    viewBox="0 0 24 24"
                    style="width: 1em; height: 1em"
                    aria-hidden="true"
                  >
                    <line
                      x1="18"
                      y1="6"
                      x2="6"
                      y2="18"
                    />
                    <line
                      x1="6"
                      y1="6"
                      x2="18"
                      y2="18"
                    />
                  </svg>
                </button>
              </div>
              <!-- Expanded — a small header row collapses the
                   screenshot on click; the delete button lives in
                   this header, aligned right (user request
                   2026-08-12). -->
              <div v-else>
                <div
                  class="flex cursor-pointer items-center gap-2 border-b border-slate-100 px-3 py-1.5 dark:border-slate-700"
                  :data-testid="`${props.test_id}-screenshot-${index}-expanded`"
                  @click="onScreenshotHeaderClick((item as unknown as ScreenshotRow).uid)"
                >
                  <span
                    class="screenshot-handle cursor-grab text-sm text-slate-300 dark:text-slate-600"
                    aria-hidden="true"
                    @click.stop
                  >
                    ⠿
                  </span>
                  <span class="flex-1 text-xs text-slate-400 dark:text-slate-500">
                    {{ t('admin.projects.click_to_collapse') }}
                  </span>
                  <button
                    type="button"
                    class="select-none inline-flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 transition-colors hover:bg-danger-50 hover:text-danger-600 dark:hover:bg-danger-950 dark:hover:text-danger-400"
                    :aria-label="t('admin.projects.remove_screenshot')"
                    :data-testid="`${props.test_id}-screenshot-${index}-remove-expanded`"
                    @click.stop="removeScreenshot(index)"
                  >
                    <svg
                      class="icon"
                      viewBox="0 0 24 24"
                      style="width: 1em; height: 1em"
                      aria-hidden="true"
                    >
                      <line
                        x1="18"
                        y1="6"
                        x2="6"
                        y2="18"
                      />
                      <line
                        x1="6"
                        y1="6"
                        x2="18"
                        y2="18"
                      />
                    </svg>
                  </button>
                </div>
                <div class="flex items-start gap-3 p-4">
                  <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="sm:col-span-2">
                      <DropzoneUpload
                        :model-value="previewFileFor((item as unknown as ScreenshotRow).image)"
                        :label="t('admin.projects.screenshot_image')"
                        accept="image/*"
                        :is_loading="isUploading"
                        :test_id="`${props.test_id}-screenshot-${index}-dropzone`"
                        @update:model-value="
                          setScreenshotImage(
                            item as unknown as ScreenshotRow,
                            $event?.file_path ?? null,
                          )
                        "
                        @upload="uploadScreenshot(item as unknown as ScreenshotRow, $event)"
                      />
                    </div>
                    <div>
                      <AdminBaseInput
                        :model-value="(item as unknown as ScreenshotRow).title[props.locale] ?? ''"
                        :label="`${t('common.title')} [${props.locale}]`"
                        :disabled="props.disabled"
                        :maxlength="200"
                        :test_id="`${props.test_id}-screenshot-${index}-title-${props.locale}`"
                        @update:model-value="
                          setScreenshotTitle(item as unknown as ScreenshotRow, $event)
                        "
                      />
                    </div>
                    <div>
                      <AdminBaseInput
                        :model-value="(item as unknown as ScreenshotRow).alt_text[props.locale] ?? ''"
                        :label="`${t('admin.projects.alt_text')} [${props.locale}]`"
                        :disabled="props.disabled"
                        :maxlength="500"
                        :test_id="`${props.test_id}-screenshot-${index}-alt-${props.locale}`"
                        @update:model-value="
                          setScreenshotAlt(item as unknown as ScreenshotRow, $event)
                        "
                      />
                    </div>
                    <div class="sm:col-span-2">
                      <AdminBaseTextarea
                        :model-value="(item as unknown as ScreenshotRow).description[props.locale] ?? ''"
                        :label="`${t('common.description')} [${props.locale}]`"
                        :disabled="props.disabled"
                        :maxlength="500"
                        :rows="2"
                        :test_id="`${props.test_id}-screenshot-${index}-description-${props.locale}`"
                        @update:model-value="
                          setScreenshotDescription(item as unknown as ScreenshotRow, $event)
                        "
                      />
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>
        </SortableList>
        <AdminBaseButton
          type="button"
          variant="secondary"
          size="sm"
          icon="heroicons:plus"
          icon_position="left"
          :disabled="props.disabled"
          :test_id="`${props.test_id}-add-screenshot`"
          class="mt-3"
          @click="addScreenshot"
        >
          {{ t('admin.projects.add_screenshot') }}
        </AdminBaseButton>
      </div>
    </div>
  </AdminBaseCard>
</template>
