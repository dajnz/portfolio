<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;

/**
 * Inserts every predefined settings key with its default value. The type
 * of each key is fixed by `SettingName` — the seeder only supplies
 * values. Idempotent: re-running refreshes values. `mail_api_token` is
 * stored encrypted like any other write path.
 */
class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        $repository = App::make(SettingRepositoryInterface::class);

        $apiMailer = Config::get('mail.mailers.api', []);

        $defaults = [
            [SettingName::SiteMaintenanceMode, false],
            [SettingName::SupportedLocales, Config::get('portfolio.supported_locales')],
            [SettingName::ItemsPerPage, Config::get('portfolio.items_per_page')],
            [SettingName::ProjectsPerPage, Config::get('portfolio.projects_per_page')],
            [SettingName::ArticlesPerPage, Config::get('portfolio.articles_per_page')],
            [SettingName::ExperienceItemsPerPage, Config::get('portfolio.experience_items_per_page')],
            [SettingName::AnalyticsEmailEnabled, false],
            [SettingName::AnalyticsEmailRecipient, ''],
            [SettingName::EmailOnFeedback, true],
            [SettingName::MailApiEndpoint, $apiMailer['endpoint'] ?? ''],
            [SettingName::MailApiToken, $this->encryptSecret($apiMailer['token'] ?? '')],
            [SettingName::MailFromAddress, Config::get('mail.from.address')],
            [SettingName::MailFromName, Config::get('mail.from.name')],
            [SettingName::BackupMaxCount, Config::get('backup.max_count')],
            [SettingName::BackupUploadMaxSize, 102400],
            [SettingName::TrashRetentionDays, 30],
            [SettingName::ImageMaxDimension, Config::get('portfolio.image_max_dimension')],
        ];

        foreach ($defaults as [$name, $value]) {
            $repository->saveValue($name, $value);
        }
    }

    private function encryptSecret(string $secret): string
    {
        return $secret === '' ? '' : Crypt::encryptString($secret);
    }
}
