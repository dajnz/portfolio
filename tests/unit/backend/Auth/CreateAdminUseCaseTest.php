<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Auth;

use App\Features\Auth\App\UseCases\CreateAdminUseCase;
use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\Exceptions\AdminAlreadyExistsException;
use InvalidArgumentException;
use Tests\Support\Fakes\FakeAdminRepository;

function existingAdmin(): Admin
{
    return new Admin(
        id: 1,
        name: 'admin',
        email: 'old@example.com',
        login: 'admin',
        passwordHash: 'original-hash',
        githubId: null,
        githubToken: null,
        sessionVersion: 0,
        timedOtp: AdminTimedOtp::disabled(),
    );
}

describe('CreateAdminUseCase', function (): void {
    it('creates the first admin with the login as the display name', function (): void {
        $repository = new FakeAdminRepository();

        $result = (new CreateAdminUseCase($repository))->create(
            'admin',
            'admin@example.com',
            'SecretPassword123!',
            false,
        );

        expect($result->created)->toBeTrue()
            ->and($result->login)->toBe('admin')
            ->and($repository->created)->toHaveCount(1)
            ->and($repository->created[0]['name'])->toBe('admin')
            ->and($repository->created[0]['login'])->toBe('admin')
            ->and($repository->created[0]['plainPassword'])->toBe('SecretPassword123!');
    });

    it('rejects a second account when force is not set', function (): void {
        $repository = new FakeAdminRepository();
        $repository->admin = existingAdmin();

        expect(fn () => (new CreateAdminUseCase($repository))->create(
            'newlogin',
            'new@example.com',
            'SecretPassword123!',
            false,
        ))->toThrow(AdminAlreadyExistsException::class);

        expect($repository->saved)->toBe([]);
    });

    it('overwrites login and email with force while preserving the password', function (): void {
        $repository = new FakeAdminRepository();
        $repository->admin = existingAdmin();

        $result = (new CreateAdminUseCase($repository))->create(
            'newlogin',
            'new@example.com',
            'IgnoredPassword123!',
            true,
        );

        expect($result->created)->toBeFalse()
            ->and($result->login)->toBe('newlogin')
            ->and($repository->saved)->toHaveCount(1)
            ->and($repository->admin?->toLogin())->toBe('newlogin')
            ->and($repository->admin?->toEmail())->toBe('new@example.com')
            ->and($repository->admin?->toPasswordHash())->toBe('original-hash');
    });

    it('requires a password when creating a new account', function (): void {
        $repository = new FakeAdminRepository();

        expect(fn () => (new CreateAdminUseCase($repository))->create(
            'admin',
            'admin@example.com',
            null,
            true,
        ))->toThrow(InvalidArgumentException::class);
    });
});
