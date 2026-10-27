<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Providers;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Settings\Infra\Mail\ApiMailTransport;
use App\Features\Shared\Infra\Services\PublicPageCache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Throwable;

/**
 * Merges the DB mail settings into `config('mail.*')` at boot so queued
 * mailables and HTTP requests always see the admin-configured values, and
 * registers the `api` mail transport: mail is delivered via a
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
    /**
     * Only a positive answer is memoized: the table is created after provider
     * boot in tests, and a cached `false` would hide it from `configureMail()`
     * re-runs. In a migrated deployment the probe then runs once per process
     * instead of on every request.
     */
    private bool $settingsTableExists = false;

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

        $settings = $this->app->make(PublicPageCache::class)->remember(
            'mail-config',
            fn (): array => $this->readMailSettings(),
        );

        $endpoint = $settings['endpoint'];

        if ($endpoint !== null && $endpoint !== '') {
            $token = $this->decryptToken($settings['token'])
                ?? Config::get('mail.mailers.api.token', null);

            Config::set('mail.default', 'api');
            Config::set('mail.mailers.api', array_merge(Config::get('mail.mailers.api', []), [
                'endpoint' => $endpoint,
                'token' => $token,
            ]));
        }

        Config::set('mail.from.address', $settings['from_address']);
        Config::set('mail.from.name', $settings['from_name']);
    }

    /**
     * @return array{endpoint: mixed, token: mixed, from_address: mixed, from_name: mixed}
     */
    private function readMailSettings(): array
    {
        $repository = $this->app->make(SettingRepositoryInterface::class);

        return [
            'endpoint' => $repository->value(SettingName::MailApiEndpoint, Config::get('mail.mailers.api.endpoint', '')),
            'token' => $repository->value(SettingName::MailApiToken),
            'from_address' => $repository->value(SettingName::MailFromAddress, Config::get('mail.from.address')),
            'from_name' => $repository->value(SettingName::MailFromName, Config::get('mail.from.name')),
        ];
    }

    private function settingsTableExists(): bool
    {
        if ($this->settingsTableExists) {
            return true;
        }

        try {
            return $this->settingsTableExists = Schema::hasTable('settings');
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
