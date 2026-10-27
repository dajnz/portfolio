<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use App\Features\Auth\Infra\Models\AdminModel;
use App\Features\Auth\Infra\Services\PasswordGenerator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('admin:create command', function (): void {
    it('creates the admin in non-interactive mode with explicit flags', function (): void {
        $this->artisan('admin:create', [
            '--login' => 'admin',
            '--email' => 'admin@example.com',
            '--password' => 'ExplicitPassword123!',
        ])->assertSuccessful();

        $admin = AdminModel::query()->firstOrFail();
        expect($admin->login)->toBe('admin')
            ->and($admin->email)->toBe('admin@example.com')
            ->and(Hash::check('ExplicitPassword123!', $admin->password))->toBeTrue()
            ->and($admin->totp_enabled)->toBeFalse()
            ->and($admin->session_version)->toBe(0);
    });

    it('fails without --force when an admin already exists', function (): void {
        $this->createAdmin();

        $this->artisan('admin:create', [
            '--login' => 'newlogin',
            '--email' => 'new@example.com',
            '--password' => 'ExplicitPassword123!',
        ])->assertExitCode(1);
    });

    it('overwrites login/email with --force and preserves the password and security state', function (): void {
        $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);
        $password = $admin->password;

        $this->artisan('admin:create', [
            '--force' => true,
            '--login' => 'newlogin',
            '--email' => 'new@example.com',
            '--password' => 'IgnoredPassword123!',
        ])->assertSuccessful();

        $fresh = $admin->fresh();
        expect($fresh->login)->toBe('newlogin')
            ->and($fresh->email)->toBe('new@example.com')
            ->and($fresh->password)->toBe($password)
            ->and($fresh->totp_enabled)->toBeTrue()
            ->and($fresh->session_version)->toBe(0);
    });

    it('lists the missing flags in non-interactive mode', function (): void {
        $this->artisan('admin:create', ['--no-interaction' => true])
            ->expectsOutputToContain('Missing required options in non-interactive mode: --login, --email, --password.')
            ->assertExitCode(1);
    });

    it('generates a password in non-interactive mode with --generate', function (): void {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        $this->artisan('admin:create', [
            '--login' => 'admin',
            '--email' => 'admin@example.com',
            '--generate' => true,
            '--no-interaction' => true,
        ])->expectsOutputToContain('Admin account created')->assertSuccessful();

        $admin = AdminModel::query()->firstOrFail();
        expect($admin->login)->toBe('admin')
            ->and($admin->email)->toBe('admin@example.com')
            ->and(Hash::check('', $admin->password))->toBeFalse()
            ->and(strlen($admin->password))->toBe(60);
    });

    it('generates a non-compromised random password when omitted interactively', function (): void {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        $this->artisan('admin:create')
            ->expectsQuestion('Login', 'admin')
            ->expectsQuestion('Email', 'admin@example.com')
            ->expectsOutputToContain('Admin account created')
            ->assertSuccessful();

        $admin = AdminModel::query()->firstOrFail();
        expect(Hash::check('', $admin->password))->toBeFalse()
            ->and(strlen($admin->password))->toBe(60);
    });
});

describe('admin:reset-password command', function (): void {
    it('resets the password, TOTP lockout and session version in scripted mode', function (): void {
        $admin = $this->createAdmin([
            'totp_failed_attempts' => 9,
            'totp_locked_until' => now()->addHours(1),
            'session_version' => 4,
        ]);

        $this->artisan('admin:reset-password', [
            '--login' => $admin->login,
            '--password' => 'NewExplicitPassword456!',
        ])->assertSuccessful();

        $fresh = $admin->fresh();
        expect(Hash::check('NewExplicitPassword456!', $fresh->password))->toBeTrue()
            ->and($fresh->totp_failed_attempts)->toBe(0)
            ->and($fresh->totp_locked_until)->toBeNull()
            ->and($fresh->session_version)->toBe(5);
    });

    it('resets the sole admin when no login is given (confirming the prompt)', function (): void {
        $admin = $this->createAdmin(['session_version' => 2]);

        $this->artisan('admin:reset-password', ['--password' => 'NewExplicitPassword456!'])
            ->expectsConfirmation('Reset the admin password? This clears the TOTP lockout and logs out all sessions.', 'yes')
            ->assertSuccessful();

        expect($admin->fresh()->session_version)->toBe(3);
    });

    it('fails when no admin exists', function (): void {
        $this->artisan('admin:reset-password', ['--login' => 'nobody', '--password' => 'NewExplicitPassword456!'])
            ->assertExitCode(1);
    });

    it('asks for confirmation in interactive mode without explicit flags', function (): void {
        $this->createAdmin();

        $this->artisan('admin:reset-password')
            ->expectsConfirmation('Reset the admin password? This clears the TOTP lockout and logs out all sessions.', false)
            ->assertSuccessful();
    });
});

describe('PasswordGenerator', function (): void {
    it('returns a 24-char password that passes the password rules', function (): void {
        Http::fake(['api.pwnedpasswords.com/*' => Http::response('')]);

        $password = app(PasswordGenerator::class)->generate();

        expect(strlen($password))->toBe(24)
            ->and(validator(['password' => $password], ['password' => ['min:12', 'regex:/[A-Z]/', 'regex:/[a-z]/', 'regex:/[0-9]/']])->passes())->toBeTrue();
    });

    it('throws after 10 attempts when every candidate is compromised', function (): void {
        $this->app->instance(
            \Illuminate\Contracts\Validation\UncompromisedVerifier::class,
            new class () implements \Illuminate\Contracts\Validation\UncompromisedVerifier {
                public function verify($data): bool
                {
                    return false;
                }
            },
        );

        expect(fn () => app(PasswordGenerator::class)->generate())
            ->toThrow(\RuntimeException::class, 'Unable to generate a non-compromised password after 10 attempts');
    });
});
