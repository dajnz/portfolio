<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Providers;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Settings\Infra\Mail\ApiMailTransport;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Merges the DB mail settings into `config('mail.*')` at boot so queued
 * mailables and HTTP requests always see the admin-configured values, and
 * registers the `api` mail transport (B125): mail is delivered via a
 * 3rd-party REST API — a single bearer token + custom endpoint.
 *
 * The default mailer switches to `api` only when a DB `mail_api_endpoint`
 * is configured — otherwise the env-driven default applies (e.g. `smtp`/
 * mailpit in dev, `log` in tests). Skips silently when the `settings`
 * table does not exist yet (pre-migration) or the DB is unavailable; the
 * committed `config('mail.*')` defaults then apply. `mail_api_token` is
 * decrypted on read.
 */
final class SettingsMailConfigProvider extends ServiceProvider
{
    public function boot(): void
    {
        Mail::extend('api', static fn (array $config): ApiMailTransport => new ApiMailTransport($config));

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

        $endpoint = $repository->value(SettingName::MailApiEndpoint, config('mail.mailers.api.endpoint', ''));

        if ($endpoint !== null && $endpoint !== '') {
            $token = $this->decryptToken($repository->value(SettingName::MailApiToken))
                ?? config('mail.mailers.api.token', null);

            config()->set('mail.default', 'api');
            config()->set('mail.mailers.api', array_merge(config('mail.mailers.api', []), [
                'endpoint' => $endpoint,
                'token' => $token,
            ]));
        }

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

    private function decryptToken(?string $value): ?string
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
