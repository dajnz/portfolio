/**
 * SettingsPage — the tab identifiers, the form value shape and the grouped
 * settings payload sent to PUT /admin/settings.
 */

export type TabId = 'general' | 'mail' | 'flags' | 'analytics';

export interface SettingsTab {
    id: TabId;
    label_key: string;
}

export interface SettingsFormValues {
    site_maintenance_mode: boolean;
    supported_locales: string[];
    items_per_page: string;
    projects_per_page: string;
    articles_per_page: string;
    experience_items_per_page: string;
    trash_retention_days: string;
    backup_max_count: string;
    backup_upload_max_size: string;
    image_max_dimension: string;
    mail_api_endpoint: string;
    mail_api_token: string;
    mail_from_address: string;
    mail_from_name: string;
    email_on_feedback: boolean;
    analytics_email_enabled: boolean;
    analytics_email_recipient: string;
}

export type SettingsValue = string | number | boolean | string[];

export type SettingsPayload = Record<string, SettingsValue>;
