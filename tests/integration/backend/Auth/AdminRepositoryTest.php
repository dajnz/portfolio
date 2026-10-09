<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Auth;

use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\Domain\ValueObjects\RecoveryCode;
use App\Features\Auth\Domain\ValueObjects\TotpSecret;
use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

describe('EloquentAdminRepository', function (): void {
    it('finds the admin by id, login, email and github id', function (): void {
        $admin = $this->createAdmin([
            'login' => 'owner',
            'email' => 'owner@example.com',
            'github_id' => '424242',
        ]);

        $repository = app(AdminRepositoryInterface::class);

        expect($repository->findById($admin->id))->not->toBeNull()
            ->and($repository->findFirst()->toId())->toBe((int) $admin->id)
            ->and($repository->findByLoginOrEmail('owner')->toId())->toBe((int) $admin->id)
            ->and($repository->findByLoginOrEmail('owner@example.com')->toId())->toBe((int) $admin->id)
            ->and($repository->findByGithubId('424242')->toId())->toBe((int) $admin->id)
            ->and($repository->findByLoginOrEmail('nope'))->toBeNull()
            ->and($repository->findById(999))->toBeNull();
    });

    it('creates an admin with a bcrypt-hashed password', function (): void {
        $repository = app(AdminRepositoryInterface::class);

        $admin = $repository->create('Admin', 'a@b.c', 'admin', 'PlainSecret123!');

        expect($admin->toId())->toBeGreaterThan(0)
            ->and($admin->toLogin())->toBe('admin')
            ->and(Hash::check('PlainSecret123!', AdminModel::query()->findOrFail($admin->toId())->password))->toBeTrue();
    });

    it('syncs credentials and the github link via save(), encrypting the token', function (): void {
        $repository = app(AdminRepositoryInterface::class);
        $admin = $this->createAdmin();

        $updated = $repository->findById((int) $admin->id)
            ->updateCredentials('New Name', 'new@example.com', 'newlogin')
            ->withGithubLink('12345', 'token-value');
        $repository->save($updated);

        $row = $admin->fresh();
        expect($row->name)->toBe('New Name')
            ->and($row->login)->toBe('newlogin')
            ->and($row->github_id)->toBe('12345')
            ->and($row->github_token)->not->toBe('token-value')
            ->and(Crypt::decryptString($row->github_token))->toBe('token-value');

        $entity = $repository->findById((int) $admin->id);
        expect($entity->hasGithubId('12345'))->toBeTrue()
            ->and($entity->toGithubToken())->toBe('token-value');
    });

    it('clears the github link via save()', function (): void {
        $repository = app(AdminRepositoryInterface::class);
        $admin = $this->createAdmin(['github_id' => '12345', 'github_token' => Crypt::encryptString('tok')]);

        $repository->save($repository->findById((int) $admin->id)->withoutGithubLink());

        expect($admin->fresh()->github_id)->toBeNull()
            ->and($admin->fresh()->github_token)->toBeNull();
    });

    it('re-hashes passwords via changePassword', function (): void {
        $repository = app(AdminRepositoryInterface::class);
        $admin = $this->createAdmin();

        $repository->changePassword((int) $admin->id, 'NewPlainSecret456!');

        expect(Hash::check('NewPlainSecret456!', $admin->fresh()->password))->toBeTrue();
    });

    it('increments the session version atomically', function (): void {
        $repository = app(AdminRepositoryInterface::class);
        $admin = $this->createAdmin();

        expect($repository->incrementSessionVersion((int) $admin->id))->toBe(1)
            ->and($repository->incrementSessionVersion((int) $admin->id))->toBe(2);
    });

    it('detects github ids taken by other admin rows', function (): void {
        $repository = app(AdminRepositoryInterface::class);
        $admin = $this->createAdmin();

        expect($repository->githubIdTakenByOther('12345', (int) $admin->id))->toBeFalse();

        $other = $this->createAdmin(['login' => 'other', 'email' => 'other@example.com', 'github_id' => '12345']);

        expect($repository->githubIdTakenByOther('12345', (int) $admin->id))->toBeTrue()
            ->and($repository->githubIdTakenByOther('12345', (int) $other->id))->toBeFalse();
    });

    it('reports whether any admin is linked with github (ADR-0013)', function (): void {
        $repository = app(AdminRepositoryInterface::class);

        expect($repository->hasGithubLinkedAdmin())->toBeFalse();

        $this->createAdmin(['github_id' => '12345']);

        expect($repository->hasGithubLinkedAdmin())->toBeTrue();
    });
});

describe('EloquentAdminTimedOtpRepository', function (): void {
    it('persists an enabled posture with the secret encrypted and codes stored', function (): void {
        $repository = app(AdminTimedOtpRepositoryInterface::class);
        $admin = $this->createAdmin();
        $codes = AdminTimedOtp::generateRecoveryCodes(2);

        $repository->saveForAdmin(
            (int) $admin->id,
            AdminTimedOtp::disabled()->enableWith(new TotpSecret('JBSWY3DPEHPK3PXP'), $codes),
        );

        $row = $admin->fresh();
        expect($row->totp_enabled)->toBeTrue()
            ->and($row->totp_secret)->not->toBe('JBSWY3DPEHPK3PXP')
            ->and(Crypt::decryptString($row->totp_secret))->toBe('JBSWY3DPEHPK3PXP')
            ->and($row->totp_recovery_codes)->toHaveCount(2)
            ->and($row->totp_recovery_codes[0])->toHaveKeys(['hash', 'used'])
            ->and($row->totp_failed_attempts)->toBe(0);

        // Round-trip through the Admin entity (mapper decryption + hydration).
        $entity = app(AdminRepositoryInterface::class)->findById((int) $admin->id);
        expect($entity->toTimedOtp()->isEnabled())->toBeTrue()
            ->and($entity->toTimedOtp()->toSecret()->toString())->toBe('JBSWY3DPEHPK3PXP')
            ->and($entity->toTimedOtp()->toRecoveryCodesRemaining())->toBe(2)
            ->and($entity->toTimedOtp()->isRecoveryCodeValid($codes[0]->toDisplayCode()))->toBeTrue();
    });

    it('persists counters and the lock state and resets them', function (): void {
        $repository = app(AdminTimedOtpRepositoryInterface::class);
        $admin = $this->adminWithTotp();
        $entity = app(AdminRepositoryInterface::class)->findById((int) $admin->id);

        $lockedUntil = new \DateTimeImmutable('+1 hour');
        $repository->saveForAdmin($admin->id, $entity->toTimedOtp()->withFailedAttempts(7)->withLockedUntil($lockedUntil));

        $row = $admin->fresh();
        expect($row->totp_failed_attempts)->toBe(7)
            ->and($row->totp_locked_until->getTimestamp())->toBe($lockedUntil->getTimestamp());

        $repository->saveForAdmin($admin->id, $entity->toTimedOtp()->withFailuresReset());
        expect($admin->fresh()->totp_failed_attempts)->toBe(0)
            ->and($admin->fresh()->totp_locked_until)->toBeNull();
    });

    it('nulls the totp columns when disabled', function (): void {
        $repository = app(AdminTimedOtpRepositoryInterface::class);
        $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);

        $repository->saveForAdmin((int) $admin->id, AdminTimedOtp::disabled());

        $row = $admin->fresh();
        expect($row->totp_enabled)->toBeFalse()
            ->and($row->totp_secret)->toBeNull()
            ->and($row->totp_recovery_codes)->toBeNull();
    });

    it('increments failed attempts atomically', function (): void {
        $repository = app(AdminTimedOtpRepositoryInterface::class);
        $admin = $this->adminWithTotp();

        expect($repository->incrementFailedAttempts((int) $admin->id))->toBe(1)
            ->and($repository->incrementFailedAttempts((int) $admin->id))->toBe(2);
    });

    it('marks a recovery code as consumed via the entity', function (): void {
        $repository = app(AdminTimedOtpRepositoryInterface::class);
        $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);
        $entity = app(AdminRepositoryInterface::class)->findById((int) $admin->id);

        $repository->saveForAdmin($admin->id, $entity->toTimedOtp()->withRecoveryCodeConsumed('AAAA-AAAA-AAAA'));

        $fresh = app(AdminRepositoryInterface::class)->findById((int) $admin->id);
        expect($fresh->toTimedOtp()->toRecoveryCodesRemaining())->toBe(0)
            ->and($fresh->toTimedOtp()->isRecoveryCodeUsed('AAAA-AAAA-AAAA'))->toBeTrue();
    });

    it('hydrates stored recovery codes as RecoveryCode value objects', function (): void {
        $admin = $this->adminWithTotp(['AAAA-AAAA-AAAA']);
        $entity = app(AdminRepositoryInterface::class)->findById((int) $admin->id);

        $codes = $entity->toTimedOtp()->toRecoveryCodes();
        expect($codes)->toHaveCount(1)
            ->and($codes[0])->toBeInstanceOf(RecoveryCode::class)
            ->and($codes[0]->isUsed())->toBeFalse()
            ->and($codes[0]->matches(RecoveryCode::normalize('AAAA-AAAA-AAAA')))->toBeTrue();
    });
});
