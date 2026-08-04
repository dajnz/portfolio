<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;

/**
 * Inserts every predefined settings key (database.md) with its default
 * value. The type of each key is fixed by `SettingName` — the seeder only
 * supplies values. Idempotent: re-running refreshes values.
 * `mail_password` is stored encrypted like any other write path.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $repository = app(SettingRepositoryInterface::class);

        $mailer = config('mail.mailers.smtp', []);

        $defaults = [
            [SettingName::SiteMaintenanceMode, false],
            [SettingName::SupportedLocales, config('portfolio.supported_locales')],
            [SettingName::ItemsPerPage, config('portfolio.items_per_page')],
            [SettingName::ProjectsPerPage, config('portfolio.projects_per_page')],
            [SettingName::ArticlesPerPage, config('portfolio.articles_per_page')],
            [SettingName::ExperienceItemsPerPage, config('portfolio.experience_items_per_page')],
            [SettingName::AnalyticsReportFrequency, config('analytics.report_frequency')],
            [SettingName::AnalyticsEmailEnabled, false],
            [SettingName::AnalyticsEmailRecipient, ''],
            [SettingName::EmailOnFeedback, true],
            [SettingName::MailDriver, config('mail.default')],
            [SettingName::MailHost, $mailer['host'] ?? ''],
            [SettingName::MailPort, $mailer['port'] ?? 0],
            [SettingName::MailEncryption, env('MAIL_ENCRYPTION') ?: ''],
            [SettingName::MailUsername, $mailer['username'] ?? ''],
            [SettingName::MailPassword, $this->encryptPassword($mailer['password'] ?? '')],
            [SettingName::MailFromAddress, config('mail.from.address')],
            [SettingName::MailFromName, config('mail.from.name')],
            [SettingName::BackupMaxCount, config('backup.max_count')],
            [SettingName::BackupUploadMaxSize, 102400],
            [SettingName::TrashRetentionDays, 30],
        ];

        foreach ($defaults as [$name, $value]) {
            $repository->saveValue($name, $value);
        }
    }

    private function encryptPassword(string $password): string
    {
        return $password === '' ? '' : Crypt::encryptString($password);
    }
}
