<?php

declare(strict_types=1);

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\ValueObjects\TotpSecret;

function makeAdmin(array $overrides = []): Admin
{
    return new Admin(
        id: $overrides['id'] ?? 1,
        name: $overrides['name'] ?? 'Admin',
        email: $overrides['email'] ?? 'admin@example.com',
        login: $overrides['login'] ?? 'admin',
        passwordHash: $overrides['passwordHash'] ?? 'bcrypt-hash',
        githubId: $overrides['githubId'] ?? null,
        githubToken: $overrides['githubToken'] ?? null,
        sessionVersion: $overrides['sessionVersion'] ?? 0,
        timedOtp: $overrides['timedOtp'] ?? AdminTimedOtp::disabled(),
    );
}

describe('Admin entity', function (): void {
    it('exposes its identity and profile fields via getters', function (): void {
        $admin = makeAdmin([
            'id' => 7,
            'name' => 'Portfolio Owner',
            'email' => 'owner@example.com',
            'login' => 'owner',
            'sessionVersion' => 3,
        ]);

        expect($admin->toId())->toBe(7)
            ->and($admin->toName())->toBe('Portfolio Owner')
            ->and($admin->toEmail())->toBe('owner@example.com')
            ->and($admin->toLogin())->toBe('owner')
            ->and($admin->toPasswordHash())->toBe('bcrypt-hash')
            ->and($admin->toSessionVersion())->toBe(3);
    });

    it('supports identity-based equality', function (): void {
        expect(makeAdmin(['id' => 1])->equals(makeAdmin(['id' => 1])))->toBeTrue()
            ->and(makeAdmin(['id' => 1])->equals(makeAdmin(['id' => 2])))->toBeFalse();
    });

    it('checks the github link by id', function (): void {
        $admin = makeAdmin(['githubId' => '12345', 'githubToken' => 'tok']);

        expect($admin->hasGithubId('12345'))->toBeTrue()
            ->and($admin->hasGithubId('999'))->toBeFalse()
            ->and($admin->isGithubLinked())->toBeTrue()
            ->and(makeAdmin()->isGithubLinked())->toBeFalse()
            ->and(makeAdmin()->toGithubId())->toBeNull()
            ->and($admin->toGithubToken())->toBe('tok');
    });

    it('checks the session version', function (): void {
        $admin = makeAdmin(['sessionVersion' => 3]);

        expect($admin->sessionVersionMatches(3))->toBeTrue()
            ->and($admin->sessionVersionMatches(4))->toBeFalse();
    });

    it('updates credentials to a new instance', function (): void {
        $updated = makeAdmin()->updateCredentials('New Name', 'new@example.com', 'newlogin');

        expect($updated->toName())->toBe('New Name')
            ->and($updated->toEmail())->toBe('new@example.com')
            ->and($updated->toLogin())->toBe('newlogin')
            ->and($updated->toId())->toBe(1)
            ->and($updated->toSessionVersion())->toBe(0)
            ->and(makeAdmin()->toName())->toBe('Admin');
    });

    it('links and unlinks github returning new instances', function (): void {
        $linked = makeAdmin()->withGithubLink('12345', 'tok');

        expect($linked->isGithubLinked())->toBeTrue()
            ->and($linked->toGithubToken())->toBe('tok')
            ->and(makeAdmin()->isGithubLinked())->toBeFalse();

        $unlinked = $linked->withoutGithubLink();

        expect($unlinked->isGithubLinked())->toBeFalse()
            ->and($unlinked->toGithubToken())->toBeNull();
    });

    it('updates the session version and the embedded otp posture', function (): void {
        $otp = AdminTimedOtp::enabled(new TotpSecret('JBSWY3DPEHPK3PXP'), [], 0, null);

        $admin = makeAdmin()->withSessionVersion(5)->withTimedOtp($otp);

        expect($admin->toSessionVersion())->toBe(5)
            ->and($admin->toTimedOtp()->isEnabled())->toBeTrue()
            ->and(makeAdmin()->toTimedOtp()->isEnabled())->toBeFalse();
    });

    it('derives is_oauth_primary from the github id and a missing password hash', function (): void {
        expect(makeAdmin(['githubId' => '12345'])->isOauthPrimary())->toBeFalse()
            ->and(makeAdmin()->isOauthPrimary())->toBeFalse();
    });

    it('rejects empty identity fields and a malformed email', function (): void {
        expect(fn () => makeAdmin(['name' => '']))->toThrow(InvalidArgumentException::class);
        expect(fn () => makeAdmin(['email' => '']))->toThrow(InvalidArgumentException::class);
        expect(fn () => makeAdmin(['login' => '']))->toThrow(InvalidArgumentException::class);
        expect(fn () => makeAdmin(['email' => 'not-an-email']))->toThrow(InvalidArgumentException::class);
    });

    it('rejects an empty password hash and a negative session version', function (): void {
        expect(fn () => makeAdmin(['passwordHash' => '']))->toThrow(InvalidArgumentException::class);
        expect(fn () => makeAdmin(['sessionVersion' => -1]))->toThrow(InvalidArgumentException::class);
    });
});
