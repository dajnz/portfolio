<?php

declare(strict_types=1);

namespace App\Features\Settings\Domain\Enums;

/**
 * The strictly defined settings key names (database.md "Predefined keys").
 * A setting name carries its allowed `SettingType` — the mapping is
 * immutable, so a `Setting` can never change its value type.
 */
enum SettingName: string
{
    case SiteMaintenanceMode = 'site_maintenance_mode';
    case SupportedLocales = 'supported_locales';
    case ItemsPerPage = 'items_per_page';
    case ProjectsPerPage = 'projects_per_page';
    case ArticlesPerPage = 'articles_per_page';
    case ExperienceItemsPerPage = 'experience_items_per_page';
    case AnalyticsEmailEnabled = 'analytics_email_enabled';
    case AnalyticsEmailRecipient = 'analytics_email_recipient';
    case EmailOnFeedback = 'email_on_feedback';
    case MailApiEndpoint = 'mail_api_endpoint';
    case MailApiToken = 'mail_api_token';
    case MailFromAddress = 'mail_from_address';
    case MailFromName = 'mail_from_name';
    case BackupMaxCount = 'backup_max_count';
    case BackupUploadMaxSize = 'backup_upload_max_size';
    case TrashRetentionDays = 'trash_retention_days';
    case ImageMaxDimension = 'image_max_dimension';

    public function type(): SettingType
    {
        return match ($this) {
            self::SiteMaintenanceMode,
            self::AnalyticsEmailEnabled,
            self::EmailOnFeedback => SettingType::Boolean,
            self::SupportedLocales => SettingType::Json,
            self::ItemsPerPage,
            self::ProjectsPerPage,
            self::ArticlesPerPage,
            self::ExperienceItemsPerPage,
            self::BackupMaxCount,
            self::BackupUploadMaxSize,
            self::TrashRetentionDays,
            self::ImageMaxDimension => SettingType::Integer,
            self::AnalyticsEmailRecipient,
            self::MailApiEndpoint,
            self::MailApiToken,
            self::MailFromAddress,
            self::MailFromName => SettingType::String,
        };
    }
}
