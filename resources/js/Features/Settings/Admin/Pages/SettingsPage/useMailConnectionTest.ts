import { useI18n } from '@shared-cross/Composables/useI18n';
import { useJsonApi } from '@shared-cross/Composables/useJsonApi';
import { useToast } from '@shared-cross/Composables/useToast';
import { type Ref, ref } from 'vue';

import type { SettingsPayload } from './types';

interface MailTestResponse {
    data: { ok: boolean; status: 'ok' | 'unauthorized' | 'unreachable' };
}

export interface UseMailConnectionTestApi {
    isTesting: Ref<boolean>;
    testMail: () => Promise<void>;
}

export function useMailConnectionTest(
    mailPayload: () => SettingsPayload,
): UseMailConnectionTestApi {
    const { t } = useI18n();
    const toast = useToast();
    const { post } = useJsonApi();

    const isTesting = ref(false);

    async function testMail(): Promise<void> {
        if (isTesting.value) {
            return;
        }

        isTesting.value = true;

        try {
            const data = await post<MailTestResponse>('/admin/settings/test-mail', {
                json: { settings: mailPayload() },
            });
            const result = data.data;

            if (result.ok) {
                toast.show(t('admin.settings.test_connection_ok'));
            } else {
                toast.show(t(`admin.settings.test_connection_${result.status}`), {
                    variant: 'error',
                });
            }
        } catch {
            toast.show(t('admin.settings.test_connection_unreachable'), { variant: 'error' });
        } finally {
            isTesting.value = false;
        }
    }

    return { isTesting, testMail };
}
