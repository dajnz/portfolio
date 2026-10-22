<script setup lang="ts">
import type { IconifyJSON } from '@iconify/types';
import { addCollection, Icon } from '@iconify/vue';
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCheckbox from '@shared-admin/AdminBaseCheckbox.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminEmptyState from '@shared-admin/AdminEmptyState.vue';
import AdminIconPicker from '@shared-admin/AdminIconPicker.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
import ConfirmationDialog from '@shared-admin/ConfirmationDialog.vue';
import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import PaginationControls from '@shared-admin/PaginationControls.vue';
import SortableList from '@shared-cross/Components/SortableList/SortableList.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useReorder } from '@shared-cross/Composables/useReorder';
import { useToast } from '@shared-cross/Composables/useToast';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import {
    type Cursor,
    type CursorNavigationPayload,
    cursorNavigationParams,
} from '@shared-cross/Utils/cursorNavigationParams';
import { localesWithErrors } from '@shared-cross/Utils/localeErrors';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, reactive, ref, watch } from 'vue';

export interface ContactRow extends Record<string, unknown> {
    id: number;
    name: string;
    /** Per-locale names — required to PUT the include-in-resume toggle back
     *  (name is required per locale). */
    names?: Record<string, string>;
    value: string;
    icon: string | null;
    url: string | null;
    include_in_resume: boolean;
    sort_order: number;
}

interface ListMeta {
    total: number;
    per_page: number;
    current_page: number;
    sort_column: string;
    sort_direction: 'asc' | 'desc';
    cursors: { first: Cursor; last: Cursor; page: number };
}

interface DraftContact {
    name: Record<string, string>;
    value: string;
    icon: string | null;
    url: string;
    include_in_resume: boolean;
}

const props = withDefaults(
    defineProps<{
        contacts?: ContactRow[];
        meta?: ListMeta;
        test_id?: string;
    }>(),
    {
        contacts: () => [],
        meta: undefined,
        test_id: 'contacts-list-page',
    },
);

const { t } = useI18n();
const { menuGroups } = useAdminMenu();
const { move, canMoveUp, canMoveDown } = useReorder();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();

const rows = ref<Array<Record<string, unknown>>>([...props.contacts]);
const editingId = ref<number | 'new' | null>(null);
const editingLocale = ref('en');
const deleteTarget = ref<ContactRow | null>(null);
const isSaving = ref(false);
const isDeleting = ref(false);
const errors = ref<Record<string, string>>({});

const draft = reactive<DraftContact>(emptyDraft());

function emptyDraft(): DraftContact {
    return {
        name: Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, ''])),
        value: '',
        icon: null,
        url: '',
        include_in_resume: false,
    };
}

// The contact icon cells render `simple-icons:*` names via the Iconify
// `Icon` component — the collection must be registered offline (no runtime
// calls to the Iconify API) or the component fetches it on
// demand. Started at module scope so the import runs before the first
// render (the contact rows are the first simple-icons consumers); the
// picker's own set loading is separate.
let simpleIconsRegistered = false;

async function ensureSimpleIcons(): Promise<void> {
    if (simpleIconsRegistered) {
        return;
    }

    try {
        const module = await import('@/iconSubsets/simple-icons.json');
        const json = (module as { default: IconifyJSON }).default;

        addCollection(json);
        simpleIconsRegistered = true;
    } catch {
        // Iconify falls back to its runtime fetch on an unregistered name.
    }
}

void ensureSimpleIcons();

watch(
    () => props.contacts,
    (next) => {
        rows.value = [...next];
        if (editingId.value === 'new') {
            // A server re-render (save success) closes the inline editor.
            editingId.value = null;
        }
    },
);

// The reorderable list must render in display order: the backend defaults
// to id desc, so the page requests `sort_order` asc — normalizing the
// first render when the controller served the default.
if (
    props.meta !== undefined &&
    (props.meta.sort_column !== 'sort_order' || props.meta.sort_direction !== 'asc')
) {
    router.get(
        '/admin/contacts',
        { sort_column: 'sort_order', sort_direction: 'asc' },
        { only: ['contacts', 'meta'], preserveState: true, preserveScroll: true },
    );
}

// The per-locale editing tabs only offer the ACTIVE locales (the
// shared `supported_locales` prop — disabled locales are not editable).
const locales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

const hasRows = computed(() => rows.value.length > 0);

// Locales whose required name translation is empty — their tab gets the
// warning marker (proactive feedback before the save round-trip).
const warnedLocales = computed(() =>
    SUPPORTED_LOCALES.filter((code) => (draft.name[code] ?? '').trim() === ''),
);

function startCreate(): void {
    Object.assign(draft, emptyDraft());
    errors.value = {};
    editingId.value = 'new';
    editingLocale.value = 'en';
}

function startEdit(row: ContactRow): void {
    Object.assign(draft, {
        name: Object.fromEntries(
            SUPPORTED_LOCALES.map((code) => [code, row.names?.[code] ?? row.name ?? '']),
        ),
        value: row.value ?? '',
        icon: row.icon ?? null,
        url: row.url ?? '',
        include_in_resume: row.include_in_resume,
    });
    errors.value = {};
    editingId.value = row.id;
    editingLocale.value = 'en';
}

function cancelEdit(): void {
    editingId.value = null;
    errors.value = {};
}

// Clicking anywhere on a viewing row starts inline editing — except the
// drag knob, the include-in-resume checkbox and the delete button (they
// stop propagation). Clicking ANOTHER row while one is open switches the
// editor to it; clicks inside the open editor are ignored (the save /
// cancel buttons stop propagation so their synchronous state reset cannot
// be reverted by the bubbled row click).
function onRowClick(item: Record<string, unknown>): void {
    const id = Number(item.id);

    if (editingId.value === id) {
        return;
    }

    startEdit(item as unknown as ContactRow);
}

function save(): void {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;
    errors.value = {};

    const payload = {
        value: draft.value,
        icon: draft.icon,
        url: draft.url === '' ? null : draft.url,
        include_in_resume: draft.include_in_resume,
        name: draft.name,
        sort_order: 0,
    };

    const done = {
        preserveScroll: true,
        onSuccess: () => {
            isSaving.value = false;
            editingId.value = null;
            errors.value = {};
        },
        onError: (serverErrors: Record<string, string>) => {
            isSaving.value = false;
            errors.value = { ...serverErrors };

            // Per-locale validation errors are only visible under the
            // active tab's input — surface the missing translations as
            // toasts so the save never fails silently.
            for (const locale of localesWithErrors(serverErrors)) {
                toast.show(
                    t('admin.toasts.translation_incomplete', {
                        locale: t(`admin.locales.${locale}`),
                    }),
                    {
                        variant: 'error',
                    },
                );
            }
        },
    };

    if (editingId.value === 'new') {
        router.post('/admin/contacts', payload, done);
    } else if (typeof editingId.value === 'number') {
        router.put(`/admin/contacts/${editingId.value}`, payload, done);
    }
}

function requestDelete(row: ContactRow): void {
    deleteTarget.value = row;
}

function confirmDelete(): void {
    if (deleteTarget.value === null || isDeleting.value) {
        return;
    }

    isDeleting.value = true;

    router.delete(`/admin/contacts/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            isDeleting.value = false;
            deleteTarget.value = null;
        },
    });
}

function toggleResume(item: ContactRow, includeInResume: boolean): void {
    router.put(
        `/admin/contacts/${item.id}`,
        {
            value: item.value,
            icon: item.icon,
            url: item.url,
            include_in_resume: includeInResume,
            name: item.names ?? { en: item.name, ru: item.name },
            sort_order: item.sort_order,
        },
        { preserveScroll: true },
    );
}

function reorder(items: Array<Record<string, unknown>>): void {
    const ids = items.map((item) => Number(item.id));

    router.post(
        '/admin/contacts/reorder',
        { ids },
        {
            preserveScroll: true,
            onError: () => {
                // Roll back to the server order on failure — the props
                // still hold the last confirmed order.
                rows.value = [...props.contacts];
            },
        },
    );
}

// Keyboard alternative to drag reordering: move the row by one position
// and persist the new order optimistically (the server rollback path is
// shared with drag).
function moveContact(index: number, direction: -1 | 1): void {
    const next = move(rows.value, index, direction);

    if (next === null) {
        return;
    }

    rows.value = next;
    reorder(next);
}

function navigate(payload: CursorNavigationPayload): void {
    router.get(
        '/admin/contacts',
        {
            ...cursorNavigationParams(payload, props.meta?.cursors),
            sort_column: 'sort_order',
            sort_direction: 'asc',
        },
        {
            only: ['contacts', 'meta'],
            preserveState: true,
            preserveScroll: true,
        },
    );
}

function draftName(locale: string): string {
    return draft.name[locale] ?? '';
}

function setDraftName(locale: string, value: string): void {
    draft.name[locale] = value;
}

function nameError(): string | null {
    return errors.value[`name.${editingLocale.value}`] ?? errors.value.name ?? null;
}

function isEditing(rowId: number): boolean {
    return editingId.value === rowId;
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/contacts'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-3xl"
      :data-testid="props.test_id"
    >
      <h1 class="mb-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{ t('admin.contacts.title') }}
      </h1>

      <!-- Sticky header controls: the Add Contact button stays visible
           while scrolling. -->
      <AdminStickyActionBar :test_id="`${props.test_id}-sticky-bar`">
        <AdminBaseButton
          variant="primary"
          icon="heroicons:plus"
          icon_position="left"
          :test_id="`${props.test_id}-add-btn`"
          @click="startCreate"
        >
          {{ t('admin.contacts.add') }}
        </AdminBaseButton>
      </AdminStickyActionBar>

      <template v-if="hasRows || editingId === 'new'">
        <div
          v-if="editingId === 'new'"
          class="mb-3 flex flex-col gap-3 rounded-lg border border-slate-200 bg-white p-4 shadow-card dark:border-slate-700 dark:bg-surface-dark-secondary cursor-pointer sm:flex-row sm:items-center"
          :data-testid="`${props.test_id}-row-new`"
        >
          <span
            class="hidden cursor-grab text-sm text-slate-300 sm:block dark:text-slate-600"
            aria-hidden="true"
          >⠿</span>
          <AdminIconPicker
            v-model="draft.icon"
            icon_set="technical"
            :placeholder="t('admin.icon_picker.select_placeholder')"
            :test_id="`${props.test_id}-icon-picker-new`"
          />
          <div class="min-w-0 flex-1 space-y-2">
            <LocaleTabs
              v-model="editingLocale"
              :locales="locales"
              :warned_locales="warnedLocales"
              :test_id="`${props.test_id}-name-tabs-new`"
            />
            <AdminBaseInput
              :model-value="draftName(editingLocale)"
              :label="t('admin.contacts.name')"
              :error="nameError()"
              :disabled="isSaving"
              :maxlength="100"
              :test_id="`${props.test_id}-name-new-${editingLocale}`"
              @update:model-value="setDraftName(editingLocale, $event)"
            />
            <AdminBaseInput
              v-model="draft.value"
              :label="t('admin.contacts.value')"
              :error="errors.value ?? null"
              :disabled="isSaving"
              :maxlength="255"
              :test_id="`${props.test_id}-value-new`"
            />
            <AdminBaseInput
              v-model="draft.url"
              type="url"
              :label="t('admin.contacts.url')"
              :error="errors.url ?? null"
              :disabled="isSaving"
              :maxlength="500"
              :test_id="`${props.test_id}-url-new`"
            />
          </div>
          <div class="flex items-center justify-between gap-2 sm:flex-col sm:items-end sm:justify-start">
            <AdminBaseButton
              type="button"
              size="sm"
              variant="primary"
              class="w-28 justify-center"
              :loading="isSaving"
              :test_id="`${props.test_id}-save-new`"
              @click="save"
            >
              {{ t('common.save') }}
            </AdminBaseButton>
            <AdminBaseButton
              type="button"
              size="sm"
              variant="ghost"
              class="w-28 justify-center"
              :disabled="isSaving"
              :test_id="`${props.test_id}-cancel-new`"
              @click="cancelEdit"
            >
              {{ t('common.cancel') }}
            </AdminBaseButton>
          </div>
        </div>

        <SortableList
          v-model="rows"
          item_key="id"
          :handle="'.contact-row-handle'"
          :classes="{
            container: 'divide-y divide-slate-100 rounded-lg border border-slate-200 bg-white shadow-card dark:divide-slate-700 dark:border-slate-700 dark:bg-surface-dark-secondary',
            item: 'px-4 py-3 hover:bg-slate-100 hover:dark:bg-surface-dark-tertiary',
          }"
          :test_id="`${props.test_id}-sortable`"
          @drag-end="reorder"
        >
          <template #default="{ item, index }">
            <div
              class="flex gap-3"
              :class="{
                'cursor-pointer': editingId === null,
                'flex-col sm:flex-row sm:items-center': isEditing(Number(item.id)),
                'items-center': !isEditing(Number(item.id)),
              }"
              :data-testid="`${props.test_id}-row-${Number(item.id)}`"
              @click="onRowClick(item)"
            >
              <span
                class="contact-row-handle hidden cursor-grab select-none text-sm text-slate-300 sm:block dark:text-slate-600"
                aria-hidden="true"
                @click.stop
              >⠿</span>

              <template v-if="isEditing(Number(item.id))">
                <AdminIconPicker
                  v-model="draft.icon"
                  icon_set="technical"
                  :placeholder="t('admin.icon_picker.select_placeholder')"
                  :test_id="`${props.test_id}-icon-picker-${Number(item.id)}`"
                />
                <div class="min-w-0 flex-1 space-y-2">
                  <div class="flex items-center justify-between gap-2">
                    <LocaleTabs
                      v-model="editingLocale"
                      :locales="locales"
                      :warned_locales="warnedLocales"
                      :test_id="`${props.test_id}-name-tabs-${Number(item.id)}`"
                    />
                    <span
                      class="sm:hidden"
                      @click.stop
                    >
                      <AdminBaseButton
                        variant="icon-outline-danger"
                        icon="heroicons:trash"
                        :aria-label="`${t('common.delete')} ${(item.name as string) ?? ''}`"
                        :test_id="`${props.test_id}-delete-edit-mobile-${Number(item.id)}`"
                        @click="requestDelete(item as unknown as ContactRow)"
                      />
                    </span>
                  </div>
                  <AdminBaseInput
                    :model-value="draftName(editingLocale)"
                    :label="t('admin.contacts.name')"
                    :error="nameError()"
                    :disabled="isSaving"
                    :maxlength="100"
                    :test_id="`${props.test_id}-name-${Number(item.id)}-${editingLocale}`"
                    @update:model-value="setDraftName(editingLocale, $event)"
                  />
                  <AdminBaseInput
                    v-model="draft.value"
                    :label="t('admin.contacts.value')"
                    :error="errors.value ?? null"
                    :disabled="isSaving"
                    :maxlength="255"
                    :test_id="`${props.test_id}-value-${Number(item.id)}`"
                  />
                  <AdminBaseInput
                    v-model="draft.url"
                    type="url"
                    :label="t('admin.contacts.url')"
                    :error="errors.url ?? null"
                    :disabled="isSaving"
                    :maxlength="500"
                    :test_id="`${props.test_id}-url-${Number(item.id)}`"
                  />
                </div>
                <div class="flex items-center justify-between gap-2 sm:flex-col sm:items-end sm:justify-start">
                  <span @click.stop>
                    <AdminBaseButton
                      type="button"
                      size="sm"
                      variant="primary"
                      class="w-28 justify-center"
                      :loading="isSaving"
                      :test_id="`${props.test_id}-save-${Number(item.id)}`"
                      @click="save"
                    >
                      {{ t('common.save') }}
                    </AdminBaseButton>
                  </span>
                  <span @click.stop>
                    <AdminBaseButton
                      type="button"
                      size="sm"
                      variant="ghost"
                      class="w-28 justify-center"
                      :disabled="isSaving"
                      :test_id="`${props.test_id}-cancel-${Number(item.id)}`"
                      @click="cancelEdit"
                    >
                      {{ t('common.cancel') }}
                    </AdminBaseButton>
                  </span>
                  <span
                    class="hidden sm:block sm:self-center"
                    @click.stop
                  >
                    <AdminBaseButton
                      variant="icon-outline-danger"
                      icon="heroicons:trash"
                      :aria-label="`${t('common.delete')} ${(item.name as string) ?? ''}`"
                      :test_id="`${props.test_id}-delete-edit-${Number(item.id)}`"
                      @click="requestDelete(item as unknown as ContactRow)"
                    />
                  </span>
                </div>
              </template>

              <template v-else>
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                  <Icon
                    class="w-[1.1em] h-[1.1em]"
                    :icon="(item.icon as string | null) ?? 'heroicons:link'"
                    aria-hidden="true"
                  />
                </span>
                <div class="min-w-0 flex-1">
                  <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">
                    {{ (item.name as string) ?? '' }}
                  </p>
                  <p class="truncate font-mono text-xs text-slate-400 dark:text-slate-500">
                    {{ item.value as string }}
                  </p>
                </div>
                <span @click.stop>
                  <AdminBaseCheckbox
                    :model-value="Boolean(item.include_in_resume)"
                    :label="t('admin.contacts.include_in_resume')"
                    :test_id="`${props.test_id}-resume-${Number(item.id)}`"
                    @update:model-value="toggleResume(item as unknown as ContactRow, $event)"
                  />
                </span>
                <span
                  class="flex shrink-0 items-center gap-1"
                  @click.stop
                >
                  <AdminBaseButton
                    variant="icon-only"
                    icon="heroicons:chevron-up"
                    :disabled="!canMoveUp(index)"
                    :aria-disabled="!canMoveUp(index)"
                    :aria-label="t('common.move_item_up', { item_name: (item.name as string) ?? '' })"
                    :test_id="`${props.test_id}-move-up-${Number(item.id)}`"
                    @click="moveContact(index, -1)"
                  />
                  <AdminBaseButton
                    variant="icon-only"
                    icon="heroicons:chevron-down"
                    :disabled="!canMoveDown(index, rows.length)"
                    :aria-disabled="!canMoveDown(index, rows.length)"
                    :aria-label="t('common.move_item_down', { item_name: (item.name as string) ?? '' })"
                    :test_id="`${props.test_id}-move-down-${Number(item.id)}`"
                    @click="moveContact(index, 1)"
                  />
                </span>
                <span
                  class="hidden sm:block"
                  @click.stop
                >
                  <AdminBaseButton
                    variant="icon-outline-danger"
                    icon="heroicons:trash"
                    :aria-label="`${t('common.delete')} ${(item.name as string) ?? ''}`"
                    :test_id="`${props.test_id}-delete-${Number(item.id)}`"
                    @click="requestDelete(item as unknown as ContactRow)"
                  />
                </span>
              </template>
              <span class="sr-only">{{ index + 1 }}</span>
            </div>
          </template>
        </SortableList>

        <PaginationControls
          v-if="props.meta !== undefined"
          :total="props.meta.total"
          :per_page="props.meta.per_page"
          :current_page="props.meta.current_page"
          :cursors="props.meta.cursors"
          :sort_column="props.meta.sort_column"
          :sort_direction="props.meta.sort_direction"
          :test_id="`${props.test_id}-pagination`"
          @navigate="navigate"
        />
      </template>

      <AdminEmptyState
        v-else
        mode="empty"
        :message="t('admin.contacts.empty_state')"
        :action_label="t('admin.contacts.add')"
        :test_id="`${props.test_id}-empty`"
        @action="startCreate"
      />

      <ConfirmationDialog
        :model-value="deleteTarget !== null"
        :title="t('common.confirm_delete')"
        :confirm_label="t('common.delete')"
        :is_loading="isDeleting"
        :test_id="`${props.test_id}-delete-dialog`"
        @update:model-value="(open) => {
          if (!open) deleteTarget = null;
        }"
        @confirm="confirmDelete"
      />
    </section>
  </AdminPageLayout>
</template>
