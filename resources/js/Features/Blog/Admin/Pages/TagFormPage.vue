<!-- TagFormPage — the create/edit article-tag form: LocaleTabs-bound Name (required, max 100), shared Slug (auto-generated from the EN name, editable, slug-regex validated, unique across article_tags), Save (no Cancel — the "Back to tags list" link replaces it). POST /admin/tags on create, PUT on edit; useUnsavedChanges guards the form. -->
<script setup lang="ts">
import type { TagEditData } from '@blog/Admin/Types/article';
import { router, usePage } from '@inertiajs/vue3';
import AdminBaseButton from '@shared-admin/AdminBaseButton.vue';
import AdminBaseCard from '@shared-admin/AdminBaseCard.vue';
import AdminBaseInput from '@shared-admin/AdminBaseInput.vue';
import AdminPageLayout from '@shared-admin/AdminPageLayout.vue';
import AdminStickyActionBar from '@shared-admin/AdminStickyActionBar.vue';
import LocaleTabs from '@shared-admin/LocaleTabs.vue';
import { useAdminMenu } from '@shared-cross/Composables/useAdminMenu';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useToast } from '@shared-cross/Composables/useToast';
import { useUnsavedChanges } from '@shared-cross/Composables/useUnsavedChanges';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { localesWithErrors } from '@shared-cross/Utils/localeErrors';
import { localeOptionsFromCodes } from '@shared-cross/Utils/localeOptions';
import { computed, reactive, ref, watch } from 'vue';

interface TagFormValues {
    slug: string;
    names: Record<string, string>;
}

const props = withDefaults(
    defineProps<{
        tag?: TagEditData | null;
        test_id?: string;
    }>(),
    {
        tag: null,
        test_id: 'tag-form-page',
    },
);

const { t } = useI18n();
const toast = useToast();
const page = usePage<{ locale?: string; supported_locales?: string[] }>();
const { menuGroups } = useAdminMenu();

const activeLocale = ref('en');
const isSaving = ref(false);
const errors = ref<Record<string, string>>({});
const slugTouched = ref(props.tag !== null && props.tag !== undefined);

const locales = computed(() => localeOptionsFromCodes(page.props?.supported_locales));

const isEdit = computed(() => props.tag !== null && props.tag !== undefined);

function emptyLocaleMap(): Record<string, string> {
    return Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, '']));
}

function buildForm(tag: TagEditData | null | undefined): TagFormValues {
    return {
        slug: tag?.slug ?? '',
        names: { ...emptyLocaleMap(), ...(tag?.names ?? {}) },
    };
}

const form = reactive<TagFormValues>(buildForm(props.tag));
const initialSnapshot = JSON.parse(JSON.stringify(form)) as TagFormValues;

const warnedLocales = computed(() =>
    SUPPORTED_LOCALES.filter((code) => (form.names[code] ?? '').trim() === ''),
);

const unsavedChanges = useUnsavedChanges(
    form as unknown as Record<string, unknown>,
    initialSnapshot as unknown as Record<string, unknown>,
);

watch(
    () => props.tag,
    (next) => {
        const fresh = buildForm(next);
        Object.assign(form, fresh);
        Object.assign(initialSnapshot, fresh);
        unsavedChanges.clear();
    },
);

function slugify(value: string): string {
    return value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

function onNameChange(locale: string, value: string): void {
    form.names[locale] = value;

    // Auto-generate the slug from the EN name while the field is still
    // untouched (create mode). Once edited, the manual override wins
    // (the backend keeps a submitted slug that differs from the stored
    // one).
    if (locale === 'en' && !slugTouched.value) {
        form.slug = slugify(value);
    }
}

function onSlugChange(value: string): void {
    form.slug = slugify(value);
    slugTouched.value = true;
}

function fieldError(field: string): string | null {
    return errors.value[`${field}.${activeLocale.value}`] ?? errors.value[field] ?? null;
}

function save(): void {
    if (isSaving.value) {
        return;
    }

    isSaving.value = true;
    errors.value = {};

    // The guard must not prompt for the form's own submit.
    unsavedChanges.suppressNextVisit();

    const payload = {
        slug: form.slug,
        name: form.names,
    };

    const options = {
        preserveScroll: true,
        onSuccess: () => {
            isSaving.value = false;
            errors.value = {};
            unsavedChanges.clear();
        },
        onError: (serverErrors: Record<string, string>) => {
            isSaving.value = false;
            errors.value = { ...serverErrors };

            for (const locale of localesWithErrors(serverErrors)) {
                toast.show(
                    t('admin.toasts.translation_incomplete', {
                        locale: t(`admin.locales.${locale}`),
                    }),
                    { variant: 'error' },
                );
            }
        },
    };

    if (isEdit.value) {
        router.put(`/admin/tags/${props.tag?.id}`, payload, options);
    } else {
        router.post('/admin/tags', payload, options);
    }
}
</script>

<template>
  <AdminPageLayout
    :menu_groups="menuGroups()"
    :active_route="'/admin/tags'"
    :locale_props="{
      active_locale: page.props?.locale ?? 'en',
      supported_locales: localeOptionsFromCodes(page.props?.supported_locales),
    }"
    :data-testid="`${props.test_id}-layout`"
  >
    <section
      class="mx-auto max-w-lg"
      :data-testid="props.test_id"
    >
      <AdminStickyActionBar :test_id="`${props.test_id}-sticky-bar`">
        <AdminBaseButton
          tag="a"
          href="/admin/tags"
          variant="ghost"
          icon="heroicons:arrow-left"
          icon_position="left"
          :test_id="`${props.test_id}-back-btn`"
        >
          {{ t('admin.tags.back_to_list') }}
        </AdminBaseButton>
        <LocaleTabs
          v-model="activeLocale"
          :locales="locales"
          :warned_locales="warnedLocales"
          :test_id="`${props.test_id}-locale-tabs`"
        />
        <div class="flex items-center gap-3">
          <AdminBaseButton
            type="submit"
            form="tag-form"
            variant="primary"
            :loading="isSaving"
            :test_id="`${props.test_id}-save-btn`"
          >
            {{ t('common.save') }}
          </AdminBaseButton>
        </div>
      </AdminStickyActionBar>

      <h1 class="mb-6 mt-6 text-2xl font-bold text-slate-900 dark:text-white">
        {{
          isEdit ? t('admin.tags.edit') : t('admin.tags.create')
        }}
      </h1>

      <form
        id="tag-form"
        novalidate
        :data-testid="`${props.test_id}-form`"
        @submit.prevent="save"
      >
        <AdminBaseCard class="space-y-4">
          <AdminBaseInput
            :model-value="form.names[activeLocale] ?? ''"
            :label="`${t('admin.tags.name')} [${activeLocale}]`"
            required
            :maxlength="100"
            :error="fieldError('name')"
            :disabled="isSaving"
            :test_id="`${props.test_id}-name-${activeLocale}`"
            @update:model-value="(value) => onNameChange(activeLocale, value)"
          />
          <AdminBaseInput
            :model-value="form.slug"
            :label="t('admin.tags.slug')"
            required
            :maxlength="100"
            :error="errors.slug ?? null"
            :help="t('admin.tags.slug_hint')"
            :disabled="isSaving"
            :test_id="`${props.test_id}-slug`"
            @update:model-value="onSlugChange"
          />
        </AdminBaseCard>
      </form>
    </section>
  </AdminPageLayout>
</template>
