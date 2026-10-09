import { useI18n } from './useI18n';

export interface AdminSidebarGroupItem {
    label: string;
    route: string;
    icon: string;
}

export interface AdminSidebarGroup {
    id: string;
    label: string;
    icon: string;
    items: Array<AdminSidebarGroupItem>;
}

export interface AdminMenuApi {
    menuGroups: () => Array<AdminSidebarGroup>;
}

/**
 * The admin sidebar's menu groups — Content / Insights / System with
 * their nav items; every admin page passes the result to
 * `AdminPageLayout`'s `menu_groups` prop.
 */
export function useAdminMenu(): AdminMenuApi {
    const { t } = useI18n();

    function menuGroups(): Array<AdminSidebarGroup> {
        return [
            {
                id: 'content',
                label: t('admin.nav.group_content'),
                icon: 'heroicons:list-bullet',
                items: [
                    { label: t('admin.nav.about'), route: '/admin/about', icon: 'heroicons:user' },
                    {
                        label: t('admin.nav.resume'),
                        route: '/admin/resume',
                        icon: 'heroicons:document-text',
                    },
                    {
                        label: t('admin.nav.skills'),
                        route: '/admin/skills',
                        icon: 'heroicons:bolt',
                    },
                    {
                        label: t('admin.nav.projects'),
                        route: '/admin/projects',
                        icon: 'heroicons:cube',
                    },
                    {
                        label: t('admin.nav.experience'),
                        route: '/admin/experience',
                        icon: 'heroicons:briefcase',
                    },
                    {
                        label: t('admin.nav.blog'),
                        route: '/admin/blog',
                        icon: 'heroicons:book-open',
                    },
                    { label: t('admin.nav.tags'), route: '/admin/tags', icon: 'heroicons:tag' },
                    {
                        label: t('admin.nav.contacts'),
                        route: '/admin/contacts',
                        icon: 'heroicons:inbox',
                    },
                ],
            },
            {
                id: 'insights',
                label: t('admin.nav.group_insights'),
                icon: 'heroicons:arrow-trending-up',
                items: [
                    {
                        label: t('admin.nav.analytics'),
                        route: '/admin/analytics',
                        icon: 'heroicons:arrow-trending-up',
                    },
                    {
                        label: t('admin.nav.feedback'),
                        route: '/admin/feedback',
                        icon: 'heroicons:chat-bubble-left-right',
                    },
                ],
            },
            {
                id: 'system',
                label: t('admin.nav.group_system'),
                icon: 'heroicons:cog-6-tooth',
                items: [
                    {
                        label: t('admin.nav.settings'),
                        route: '/admin/settings',
                        icon: 'heroicons:cog-6-tooth',
                    },
                    {
                        label: t('admin.nav.backups'),
                        route: '/admin/backups',
                        icon: 'heroicons:archive-box',
                    },
                    {
                        label: t('admin.nav.profile'),
                        route: '/admin/profile',
                        icon: 'heroicons:user-circle',
                    },
                    {
                        label: t('admin.nav.horizon'),
                        route: '/horizon',
                        icon: 'heroicons:queue-list',
                    },
                ],
            },
        ];
    }

    return { menuGroups };
}
