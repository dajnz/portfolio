// BackupListPage — the encryption-keys section state (BackupListPage.vue
// is the orchestrator). Owns the key list + its API refresh, the
// show-once generation flow (confirmation dialog → generated key modal)
// and the guarded key deletion; the page only wires the returned refs
// and handlers into EncryptionKeyList / the dialogs.

import type { EncryptionKeyEntry, GeneratedKeyResponse } from '@backup/Admin/Types/backup';
import type { EncryptionKeyListItem } from '@backup-admin/EncryptionKeyList.vue';
import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { type ComputedRef, computed, type Ref, ref } from 'vue';

export function useEncryptionKeys(): {
    keys: Ref<EncryptionKeyListItem[]>;
    activeKeyId: ComputedRef<number | null>;
    generateConfirmOpen: Ref<boolean>;
    isGenerating: Ref<boolean>;
    generatedKey: Ref<{ name: string; key_value: string } | null>;
    keyModalOpen: Ref<boolean>;
    deleteKeyTarget: Ref<number | null>;
    isDeletingKey: Ref<boolean>;
    loadKeys: () => Promise<void>;
    openGenerateConfirm: () => void;
    dismissGenerateConfirm: () => void;
    confirmGenerate: () => Promise<void>;
    requestDeleteKey: (keyId: number) => void;
    dismissDeleteKey: () => void;
    confirmDeleteKey: () => Promise<void>;
} {
    const { t } = useI18n();
    const toast = useToast();
    const api = useJsonApi();

    const keys = ref<EncryptionKeyListItem[]>([]);
    const activeKeyId = computed(() => keys.value.find((key) => key.is_active)?.id ?? null);

    const generateConfirmOpen = ref(false);
    const isGenerating = ref(false);
    const generatedKey = ref<{ name: string; key_value: string } | null>(null);
    const keyModalOpen = ref(false);
    const deleteKeyTarget = ref<number | null>(null);
    const isDeletingKey = ref(false);

    async function loadKeys(): Promise<void> {
        try {
            const response = await api.get<{ data: EncryptionKeyEntry[] }>(
                '/admin/backups/encryption-keys',
            );
            keys.value = response.data.map((key) => ({
                id: key.id,
                name: key.name,
                created_at: key.created_at,
                is_active: key.is_active,
                backup_count: key.backups_count,
            }));
        } catch {
            // The section renders empty on failure — the generate action
            // still works.
        }
    }

    function nextKeyName(): string {
        if (keys.value.length === 0) {
            return 'initial-key';
        }

        const year = new Date().getFullYear();
        const base = `rotated-${year}`;

        if (!keys.value.some((key) => key.name === base)) {
            return base;
        }

        let suffix = 2;
        while (keys.value.some((key) => key.name === `${base}-${suffix}`)) {
            suffix += 1;
        }

        return `${base}-${suffix}`;
    }

    function openGenerateConfirm(): void {
        if (isGenerating.value) {
            return;
        }

        generateConfirmOpen.value = true;
    }

    function dismissGenerateConfirm(): void {
        generateConfirmOpen.value = false;
    }

    async function confirmGenerate(): Promise<void> {
        if (isGenerating.value) {
            return;
        }

        isGenerating.value = true;

        try {
            const response = await api.post<GeneratedKeyResponse>(
                '/admin/backups/encryption-keys',
                {
                    json: { name: nextKeyName() },
                },
            );

            generatedKey.value = {
                name: response.data.name,
                key_value: response.data.key_value,
            };
            generateConfirmOpen.value = false;
            keyModalOpen.value = true;
            await loadKeys();
        } catch {
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        } finally {
            isGenerating.value = false;
        }
    }

    function requestDeleteKey(keyId: number): void {
        deleteKeyTarget.value = keyId;
    }

    function dismissDeleteKey(): void {
        deleteKeyTarget.value = null;
    }

    async function confirmDeleteKey(): Promise<void> {
        if (deleteKeyTarget.value === null || isDeletingKey.value) {
            return;
        }

        isDeletingKey.value = true;

        try {
            await api.del(`/admin/backups/encryption-keys/${deleteKeyTarget.value}`);
            deleteKeyTarget.value = null;
            await loadKeys();
        } catch {
            toast.show(t('admin.toasts.failed_to_save'), { variant: 'error' });
        } finally {
            isDeletingKey.value = false;
        }
    }

    return {
        keys,
        activeKeyId,
        generateConfirmOpen,
        isGenerating,
        generatedKey,
        keyModalOpen,
        deleteKeyTarget,
        isDeletingKey,
        loadKeys,
        openGenerateConfirm,
        dismissGenerateConfirm,
        confirmGenerate,
        requestDeleteKey,
        dismissDeleteKey,
        confirmDeleteKey,
    };
}
