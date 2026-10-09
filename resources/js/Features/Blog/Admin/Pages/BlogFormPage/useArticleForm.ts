/**
 * The blog form's state composable (2026-08-14 user request — the
 * BlogFormPage complex-component split): owns the reactive form (the
 * per-locale maps + slug + lead image + unified tags + related ids +
 * toggles), the ADR-0006 slug auto-generation, the save round trip (POST /
 * PUT with the guard suppression and the per-locale error toasts) and
 * the /api/upload flows (lead image + the editor's inline image).
 * `useUnsavedChanges` guards the form against accidental navigation.
 */

import type { ArticleEditData } from '@blog/Admin/Types/article';
import { router } from '@inertiajs/vue3';

import type { TextEditorExposed } from '@shared-admin/TextEditor/TextEditor.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { useUnsavedChanges } from '@shared-cross/Composables/useUnsavedChanges';
import { SUPPORTED_LOCALES } from '@shared-cross/Stores/localeStore';
import { localesWithErrors } from '@shared-cross/Utils/localeErrors';
import { type ComputedRef, computed, type Ref, reactive, ref, watch } from 'vue';

export interface BlogFormValues {
    slug: string;
    title: Record<string, string>;
    short_description: Record<string, string>;
    content: Record<string, string>;
    lead_image: string | null;
    lead_image_alt: Record<string, string>;
    // The unified picker pills — split into `tags` / `skill_tags` at
    // submit (admin-section-components.md:680, the form-mode rule).
    tags: Array<{ id: number; name: string; type: 'tag' | 'skill' }>;
    related_article_ids: number[];
    is_featured: boolean;
    is_published: boolean;
}

export interface UseArticleFormOptions {
    /** The page prop getter — re-read on every save/prop refresh. */
    article: () => ArticleEditData | null | undefined;
    /** The active locale tab (the error keys are per-locale). */
    activeLocale: () => string;
    /** The TextEditor ref — the inline image lands at the cursor. */
    editorRef: Ref<TextEditorExposed | null>;
}

export interface UseArticleFormApi {
    form: BlogFormValues;
    errors: Ref<Record<string, string>>;
    isSaving: Ref<boolean>;
    isUploadingImage: Ref<boolean>;
    isEdit: ComputedRef<boolean>;
    warnedLocales: ComputedRef<string[]>;
    unsavedChanges: ReturnType<typeof useUnsavedChanges>;
    fieldError: (field: string) => string | null;
    nestedFieldError: (field: string) => string | null;
    onTitleChange: (locale: string, value: string) => void;
    onSlugChange: (value: string) => void;
    setLocaleValue: (locale: string, record: Record<string, string>, value: string) => void;
    onRelatedArticlesChange: (value: string | string[]) => void;
    uploadLeadImage: (file: File) => Promise<void>;
    removeLeadImage: () => void;
    onEditorImageUpload: (file: File) => Promise<void>;
    save: () => void;
}

function emptyLocaleMap(): Record<string, string> {
    return Object.fromEntries(SUPPORTED_LOCALES.map((code) => [code, '']));
}

function nullableLocaleMap(
    values: Record<string, string | null> | undefined,
): Record<string, string> {
    const normalized = { ...emptyLocaleMap() };

    for (const [locale, value] of Object.entries(values ?? {})) {
        normalized[locale] = value ?? '';
    }

    return normalized;
}

function buildForm(article: ArticleEditData | null | undefined): BlogFormValues {
    return {
        slug: article?.slug ?? '',
        title: { ...emptyLocaleMap(), ...(article?.title ?? {}) },
        short_description: { ...emptyLocaleMap(), ...(article?.short_description ?? {}) },
        content: { ...emptyLocaleMap(), ...(article?.content ?? {}) },
        lead_image: article?.lead_image ?? null,
        lead_image_alt: nullableLocaleMap(article?.lead_image_alt),
        tags: [
            ...(article?.tags ?? []).map((tag) => ({
                id: tag.id,
                name: tag.name,
                type: 'tag' as const,
            })),
            ...(article?.skill_tags ?? []).map((skill) => ({
                id: skill.id,
                name: skill.name,
                type: 'skill' as const,
            })),
        ],
        related_article_ids: article?.related_article_ids ?? [],
        is_featured: article?.is_featured ?? false,
        is_published: article?.is_published ?? false,
    };
}

export function useArticleForm(options: UseArticleFormOptions): UseArticleFormApi {
    const { t } = useI18n();
    const toast = useToast();
    const { post: jsonPost } = useJsonApi();

    const isSaving = ref(false);
    const isUploadingImage = ref(false);
    const errors = ref<Record<string, string>>({});
    const slugTouched = ref(options.article() !== null && options.article() !== undefined);

    const form = reactive<BlogFormValues>(buildForm(options.article()));

    const isEdit = computed(() => options.article() !== null && options.article() !== undefined);

    const warnedLocales = computed(() =>
        SUPPORTED_LOCALES.filter((code) => {
            const titleMissing = (form.title[code] ?? '').trim() === '';
            const shortMissing = (form.short_description[code] ?? '').trim() === '';
            const contentMissing = (form.content[code] ?? '').trim() === '';

            return titleMissing || shortMissing || contentMissing;
        }),
    );

    // The guard's baseline is a DEEP COPY of the SAME built form (the
    // projects/experience precedent — a second buildForm() call would
    // regenerate nothing here but the copy keeps the JSON-safe baseline).
    const initialSnapshot = JSON.parse(JSON.stringify(form)) as BlogFormValues;

    const unsavedChanges = useUnsavedChanges(
        form as unknown as Record<string, unknown>,
        initialSnapshot as unknown as Record<string, unknown>,
    );

    // Server re-renders refresh the props after every save; rebuild the
    // form from the fresh article and re-arm the guard on a new baseline.
    watch(
        () => options.article(),
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

    function onTitleChange(locale: string, value: string): void {
        form.title[locale] = value;

        // Auto-generate the slug from the EN title while the field is
        // still untouched (create mode). Once edited, the manual
        // override wins (ADR-0006 — the backend keeps a submitted slug that
        // differs from the stored one and regenerates the auto value
        // with a suffix).
        if (locale === 'en' && !slugTouched.value) {
            form.slug = slugify(value);
        }
    }

    function onSlugChange(value: string): void {
        form.slug = slugify(value);
        slugTouched.value = true;
    }

    function setLocaleValue(locale: string, record: Record<string, string>, value: string): void {
        record[locale] = value;
    }

    function fieldError(field: string): string | null {
        return errors.value[`${field}.${options.activeLocale()}`] ?? errors.value[field] ?? null;
    }

    // The array-field validation errors arrive keyed per item
    // (`related_articles.0`, `tags.2`, `skill_tags.0` — Laravel's
    // dotted index keys), while the field's `error` prop binds the bare
    // key. Fall back to the first `field.N` entry so nested errors are
    // actually visible (the silent-save-failure report, 2026-08-13).
    function nestedFieldError(field: string): string | null {
        return (
            errors.value[field] ??
            Object.entries(errors.value).find(([key]) => key.startsWith(`${field}.`))?.[1] ??
            null
        );
    }

    function onRelatedArticlesChange(value: string | string[]): void {
        form.related_article_ids = (Array.isArray(value) ? value : [value])
            .filter((id) => id !== '')
            .map(Number);
    }

    async function uploadLeadImage(file: File): Promise<void> {
        if (isUploadingImage.value) {
            return;
        }

        isUploadingImage.value = true;

        try {
            const body = new FormData();
            body.append('file', file);
            body.append('entity_type', 'blog');

            const data = await jsonPost<{ data: { path: string } }>('/api/upload', {
                formData: body,
            });
            form.lead_image = data.data.path;
        } catch {
            toast.show(t('admin.toasts.upload_failed'), { variant: 'error' });
        } finally {
            isUploadingImage.value = false;
        }
    }

    function removeLeadImage(): void {
        form.lead_image = null;
        form.lead_image_alt = emptyLocaleMap();
    }

    async function onEditorImageUpload(file: File): Promise<void> {
        if (isUploadingImage.value) {
            return;
        }

        isUploadingImage.value = true;

        try {
            const body = new FormData();
            body.append('file', file);
            body.append('entity_type', 'blog');

            const data = await jsonPost<{ data: { path: string } }>('/api/upload', {
                formData: body,
            });
            options.editorRef.value?.insertImage(`/assets/${data.data.path}`);
        } catch {
            toast.show(t('admin.toasts.upload_failed'), { variant: 'error' });
        } finally {
            isUploadingImage.value = false;
        }
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
            title: form.title,
            short_description: form.short_description,
            content: form.content,
            lead_image: form.lead_image,
            lead_image_alt: form.lead_image_alt,
            is_featured: form.is_featured,
            is_published: form.is_published,
            tags: form.tags.filter((tag) => tag.type === 'tag').map((tag) => tag.id),
            skill_tags: form.tags.filter((tag) => tag.type === 'skill').map((tag) => tag.id),
            related_articles: form.related_article_ids,
        };

        const visitOptions = {
            preserveScroll: true,
            onSuccess: () => {
                isSaving.value = false;
                errors.value = {};
                unsavedChanges.clear();
            },
            onError: (serverErrors: Record<string, string>) => {
                isSaving.value = false;
                errors.value = { ...serverErrors };

                // Per-locale validation errors are only visible under
                // the active tab's input — surface the missing
                // translations as toasts so the save never fails
                // silently.
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
            router.put(`/admin/blog/${options.article()?.id}`, payload, visitOptions);
        } else {
            router.post('/admin/blog', payload, visitOptions);
        }
    }

    return {
        form,
        errors,
        isSaving,
        isUploadingImage,
        isEdit,
        warnedLocales,
        unsavedChanges,
        fieldError,
        nestedFieldError,
        onTitleChange,
        onSlugChange,
        setLocaleValue,
        onRelatedArticlesChange,
        uploadLeadImage,
        removeLeadImage,
        onEditorImageUpload,
        save,
    };
}
