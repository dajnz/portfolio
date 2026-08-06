import type { RequestPayload } from '@inertiajs/core';
import { router } from '@inertiajs/vue3';
import { type Ref, reactive, ref, toRaw } from 'vue';

import { useI18n } from './useI18n';
import { useToast } from './useToast';
import { useUnsavedChanges } from './useUnsavedChanges';

export interface FormHandlerOptions {
	/** Affects the HTTP method: create → POST, edit → PUT. */
	mode?: 'create' | 'edit';
	/** Where to navigate on success (server flash then shows the success toast). */
	redirectUrl?: string;
	/** Transform function applied to the form data before submit. */
	transform?: (data: Record<string, unknown>) => Record<string, unknown>;
}

export interface FormHandler {
	form: Record<string, unknown>;
	errors: Ref<Record<string, string>>;
	isSubmitting: Ref<boolean>;
	isDirty: Ref<boolean>;
	submit: () => Promise<void>;
	reset: () => void;
}

function cloneForm<T extends Record<string, unknown>>(value: T): T {
	return JSON.parse(JSON.stringify(value)) as T;
}

/**
 * Shared admin form state management (admin-section-components.md:1598-1618).
 * Handles 422 (populates `errors`), 5xx (error toast), success (clears the
 * unsaved-changes guard, then redirects or resets). Integrates
 * `useUnsavedChanges` automatically — the guard is removed on success.
 */
export function useFormHandler(
	initialValues: Record<string, unknown>,
	submitUrl: string,
	options: FormHandlerOptions = {},
): FormHandler {
	const form = reactive(cloneForm(initialValues)) as Record<string, unknown>;
	const errors = ref<Record<string, string>>({});
	const isSubmitting = ref(false);
	const toast = useToast();
	const unsavedChanges = useUnsavedChanges(form, initialValues);
	const failedToSaveMessage = useI18n().t('admin.toasts.failed_to_save');

	const submitVisit = options.mode === 'edit' ? router.put : router.post;

	function reset(): void {
		Object.assign(form, cloneForm(initialValues));
	}

	async function submit(): Promise<void> {
		if (isSubmitting.value) {
			return;
		}

		isSubmitting.value = true;
		errors.value = {};

		// Form values are app-typed; the Inertia payload contract only
		// requires a serializable object.
		const data = (
			options.transform ? options.transform(toRaw(form)) : toRaw(form)
		) as RequestPayload;

		// Inertia 3 visit helpers are fire-and-forget (void) — the
		// callbacks below are the only response channel.
		submitVisit(submitUrl, data, {
			preserveScroll: true,
			onSuccess: () => {
				unsavedChanges.clear();

				if (options.redirectUrl) {
					router.visit(options.redirectUrl);
				} else {
					reset();
				}
			},
			onError: (serverErrors) => {
				errors.value = { ...serverErrors };
			},
			onHttpException: (response) => {
				if (response.status >= 500) {
					toast.show(failedToSaveMessage, { variant: 'error' });
				}
			},
		});

		isSubmitting.value = false;
	}

	return {
		form,
		errors,
		isSubmitting,
		isDirty: unsavedChanges.isDirty,
		submit,
		reset,
	};
}
