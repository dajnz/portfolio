<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Mappers;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Support\Facades\Crypt;

/**
 * Converts between `admins` rows and `Admin` aggregates. Decrypts the
 * encrypted-at-rest columns (`totp_secret`, `github_token`) so the domain
 * sees plaintext values; the TOTP posture is embedded via
 * `AdminTimedOtpMapper`.
 */
final class AdminMapper
{
    public function __construct(
        private readonly AdminTimedOtpMapper $otpMapper,
    ) {
    }

    public function toEntity(AdminModel $model): Admin
    {
        return new Admin(
            id: (int) $model->id,
            name: $model->name,
            email: $model->email,
            login: $model->login,
            passwordHash: $model->password,
            githubId: $model->github_id,
            githubToken: $model->github_token !== null ? Crypt::decryptString($model->github_token) : null,
            sessionVersion: (int) $model->session_version,
            timedOtp: $this->otpMapper->toEntity($model),
        );
    }
}
