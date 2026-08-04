<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Settings;

use App\Features\Settings\App\UseCases\UpdateSettingsUseCase;
use App\Features\Settings\Infra\Providers\SettingsMailConfigProvider;
use Illuminate\Support\Facades\Schema;

describe('SettingsMailConfigProvider', function (): void {
    it('merges DB mail settings into config(mail) and decrypts the password', function (): void {
        $this->seed(\Database\Seeders\SettingsSeeder::class);

        app(UpdateSettingsUseCase::class)->update([
            'mail_driver' => 'smtp',
            'mail_host' => 'smtp.example.com',
            'mail_port' => 587,
            'mail_encryption' => 'tls',
            'mail_username' => 'mailer',
            'mail_password' => 'super-secret',
            'mail_from_address' => 'noreply@example.com',
            'mail_from_name' => 'Portfolio',
        ]);

        (new SettingsMailConfigProvider($this->app))->configureMail();

        expect(config('mail.default'))->toBe('smtp')
            ->and(config('mail.mailers.smtp.host'))->toBe('smtp.example.com')
            ->and(config('mail.mailers.smtp.port'))->toBe(587)
            ->and(config('mail.mailers.smtp.encryption'))->toBe('tls')
            ->and(config('mail.mailers.smtp.username'))->toBe('mailer')
            ->and(config('mail.mailers.smtp.password'))->toBe('super-secret')
            ->and(config('mail.from.address'))->toBe('noreply@example.com')
            ->and(config('mail.from.name'))->toBe('Portfolio');
    });

    it('skips the merge without touching config when the settings table is missing', function (): void {
        $defaultDefault = config('mail.default');
        $defaultHost = config('mail.mailers.smtp.host');

        Schema::drop('settings');

        (new SettingsMailConfigProvider($this->app))->configureMail();

        expect(config('mail.default'))->toBe($defaultDefault)
            ->and(config('mail.mailers.smtp.host'))->toBe($defaultHost);
    });
});
