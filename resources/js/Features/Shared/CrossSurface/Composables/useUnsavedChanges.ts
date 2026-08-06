import { router } from '@inertiajs/vue3';
import isEqual from 'lodash-es/isEqual';
import { getCurrentScope, isRef, onScopeDispose, type Ref, ref, toRaw, watch } from 'vue';

import { useI18n } from './useI18n';

export interface UnsavedChangesGuard {
    isDirty: Ref<boolean>;
    /** Removes both guards — called on successful form submission (spec §4). */
    clear: () => void;
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
        if (!isDirty.value) {
            return;
        }

        if (!window.confirm(promptMessage)) {
            event.preventDefault();
        }
    };

    window.addEventListener('beforeunload', onBeforeUnload);
    const unsubscribe = router.on('before', onInertiaBefore);

    function removeGuards(): void {
        window.removeEventListener('beforeunload', onBeforeUnload);
        unsubscribe();
    }

    function clear(): void {
        removeGuards();
        isDirty.value = false;
    }

    watch(form, sync, { deep: true });
    sync();

    if (getCurrentScope()) {
        onScopeDispose(removeGuards);
    }

    return { isDirty, clear };
}
