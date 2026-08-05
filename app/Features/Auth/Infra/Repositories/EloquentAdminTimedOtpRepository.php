<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Repositories;

use App\Features\Auth\Domain\Entities\AdminTimedOtp;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminTimedOtpRepositoryInterface;
use App\Features\Auth\Infra\Mappers\AdminTimedOtpMapper;
use App\Features\Auth\Infra\Models\AdminModel;

final class EloquentAdminTimedOtpRepository implements AdminTimedOtpRepositoryInterface
{
    public function __construct(
        private readonly AdminTimedOtpMapper $mapper,
    ) {
    }

    public function saveForAdmin(int $adminId, AdminTimedOtp $otp): void
    {
        $this->model($adminId)->update($this->mapper->toRow($otp));
    }

    public function incrementFailedAttempts(int $adminId): int
    {
        $model = $this->model($adminId);
        $model->increment('totp_failed_attempts');

        return (int) $model->fresh()->totp_failed_attempts;
    }

    private function model(int $adminId): AdminModel
    {
        return AdminModel::query()->findOrFail($adminId);
    }
}
