<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Providers;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Merges the DB mail settings into `config('mail.*')` at boot so queued
 * mailables and HTTP requests always see the admin-configured SMTP values.
 *
 * Skips silently when the `settings` table does not exist yet (pre-
 * migration) or the DB is unavailable; the committed `config('mail.*')`
 * defaults then apply. `mail_password` is decrypted on read.
 */
final class SettingsMailConfigProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->configureMail();
    }

    /**
     * Public so tests can re-run the merge after seeding settings rows
     * (the table is absent when providers boot inside the test app).
     */
    public function configureMail(): void
    {
        if (! $this->settingsTableExists()) {
            return;
        }

        $repository = $this->app->make(SettingRepositoryInterface::class);

        $mailer = config('mail.mailers.smtp', []);

        config()->set('mail.default', $repository->value(SettingName::MailDriver, config('mail.default')));
        config()->set('mail.mailers.smtp', array_merge($mailer, [
            'host' => $repository->value(SettingName::MailHost, $mailer['host'] ?? null),
            'port' => $repository->value(SettingName::MailPort, $mailer['port'] ?? null),
            'username' => $repository->value(SettingName::MailUsername, $mailer['username'] ?? null),
            'password' => $this->decryptPassword($repository->value(SettingName::MailPassword)) ?? $mailer['password'] ?? null,
            'encryption' => $repository->value(SettingName::MailEncryption, $mailer['encryption'] ?? null),
        ]));
        config()->set('mail.from.address', $repository->value(SettingName::MailFromAddress, config('mail.from.address')));
        config()->set('mail.from.name', $repository->value(SettingName::MailFromName, config('mail.from.name')));
    }

    private function settingsTableExists(): bool
    {
        try {
            return Schema::hasTable('settings');
        } catch (Throwable) {
            return false;
        }
    }

    private function decryptPassword(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Crypt::decryptString($value);
        } catch (Throwable) {
            return $value;
        }
    }
}
