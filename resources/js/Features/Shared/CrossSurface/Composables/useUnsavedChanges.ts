import { router } from '@inertiajs/vue3';
import isEqual from 'lodash-es/isEqual';
import { getCurrentScope, isRef, onScopeDispose, type Ref, ref, toRaw, watch } from 'vue';

import { useI18n } from './useI18n';

export interface UnsavedChangesGuard {
    isDirty: Ref<boolean>;
    /** Removes both guards — called on successful form submission (spec §4). */
    clear: () => void;
    /** Skips the prompt for the next Inertia visit — called right before
     *  the form's own submit, which would otherwise trip the navigation
     *  guard on a dirty form (4.2 deviation: admin-section-components
     *  .md:551 only documents the guard, not the submit bypass). */
    suppressNextVisit: () => void;
}

/**
 * Unsaved-changes guard for admin forms (admin-section-components.md:537-553):
 * registers a `window.beforeunload` listener and an Inertia `before`
 * navigation guard while the form differs from its initial snapshot, and
 * prompts the user (`common.unsaved_changes`). `clear()` removes the
 * guards — called on successful submission.
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

    function sync(): void {
        isDirty.value = !isEqual(toRaw(form.value), initialValues);
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

    watch(form, sync, { deep: true });
    sync();

    if (getCurrentScope()) {
        onScopeDispose(removeGuards);
    }

    return { isDirty, clear, suppressNextVisit };
}
