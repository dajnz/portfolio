import { type Ref, ref } from 'vue';

import type { SettingsTab, TabId } from './types';

export const SETTINGS_TABS: SettingsTab[] = [
    { id: 'general', label_key: 'admin.settings.general' },
    { id: 'mail', label_key: 'admin.settings.mail' },
    { id: 'flags', label_key: 'admin.settings.feature_flags' },
    { id: 'analytics', label_key: 'admin.settings.analytics' },
];

const TAB_STORAGE_KEY = 'admin_settings_active_tab';

export interface UseSettingsTabsApi {
    activeTab: Ref<TabId>;
    selectTab: (tab: TabId) => void;
}

// The last visited tab is remembered per browser (localStorage, same
// pattern as the admin sidebar collapse); the first tab is the fallback
// when nothing is stored yet or the stored value is not a known tab.
function loadStoredTab(): TabId {
    if (typeof window === 'undefined') {
        return 'general';
    }

    const stored = window.localStorage.getItem(TAB_STORAGE_KEY);

    return SETTINGS_TABS.some((tab) => tab.id === stored) ? (stored as TabId) : 'general';
}

export function useSettingsTabs(): UseSettingsTabsApi {
    const activeTab = ref<TabId>(loadStoredTab());

    function selectTab(tab: TabId): void {
        activeTab.value = tab;

        if (typeof window !== 'undefined') {
            window.localStorage.setItem(TAB_STORAGE_KEY, tab);
        }
    }

    return { activeTab, selectTab };
}
