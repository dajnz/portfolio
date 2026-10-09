import { router } from '@inertiajs/vue3';
import isEqual from 'lodash-es/isEqual';
import { getCurrentScope, isRef, onScopeDispose, type Ref, ref, toRaw, watch } from 'vue';

import { useI18n } from './useI18n';

export interface UnsavedChangesGuard {
    isDirty: Ref<boolean>;
    /** Removes both guards — called on successful form submission. */
    clear: () => void;
    /** Skips the prompt for the next Inertia visit — called right before
     *  the form's own submit, which would otherwise trip the navigation
     *  guard on a dirty form. */
    suppressNextVisit: () => void;
    /** Replaces the comparison baseline and re-syncs — for forms that
     *  save in parts without a full re-render (SettingsPage per-tab
     *  saves: only the saved tab's keys become the new baseline, the
     *  other tabs' edits stay armed). */
    rebase: (values: Record<string, unknown>) => void;
}

/**
 * Unsaved-changes guard for admin forms: registers a
 * `window.beforeunload` listener and an Inertia `before` navigation
 * guard while the form differs from its initial snapshot, and prompts
 * the user (`common.unsaved_changes`). `clear()` removes the guards —
 * called on successful submission.
 *
 * The dirty check uses lodash-es `isEqual` (per-method import, tree-shaken
 * into the bundle) — deep equality across plain data, nested objects and
 * arrays without hand-rolled recursion.
 */
export function useUnsavedChanges(
    formRef: Ref<Record<string, unknown>> | Record<string, unknown>,
    initialValues: Record<string, unknown>,
): UnsavedChangesGuard {
    const form = isRef(formRef) ? formRef : ref(formRef);
    const isDirty = ref(false);
    const promptMessage = useI18n().t('common.unsaved_changes');
    let suppressVisit = false;
    let baseline = initialValues;

    function sync(): void {
        isDirty.value = !isEqual(toRaw(form.value), baseline);
    }

    const onBeforeUnload = (event: BeforeUnloadEvent): void => {
        if (!isDirty.value) {
            return;
        }

        event.preventDefault();
        event.returnValue = '';
    };

    const onInertiaBefore = (event: { preventDefault: () => void }): void => {
        if (suppressVisit) {
            suppressVisit = false;
            return;
        }

        if (!isDirty.value) {
            return;
        }

        if (!window.confirm(promptMessage)) {
            event.preventDefault();
        }
    };

    const onVisitFinish = (): void => {
        // Safety net: a suppressed visit that never navigated (e.g. a 422
        // response) must not leave the flag armed for the next navigation.
        suppressVisit = false;
    };

    window.addEventListener('beforeunload', onBeforeUnload);
    const unsubscribeBefore = router.on('before', onInertiaBefore);
    const unsubscribeFinish = router.on('finish', onVisitFinish);

    function removeGuards(): void {
        window.removeEventListener('beforeunload', onBeforeUnload);
        unsubscribeBefore();
        unsubscribeFinish();
    }

    function clear(): void {
        removeGuards();
        isDirty.value = false;
    }

    function suppressNextVisit(): void {
        suppressVisit = true;
    }

    function rebase(values: Record<string, unknown>): void {
        baseline = values;
        sync();
    }

    watch(form, sync, { deep: true });
    sync();

    if (getCurrentScope()) {
        onScopeDispose(removeGuards);
    }

    return { isDirty, clear, suppressNextVisit, rebase };
}
