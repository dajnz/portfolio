<?php

declare(strict_types=1);

namespace App\Features\Auth\Infra\Repositories;

use App\Features\Auth\Domain\Entities\Admin;
use App\Features\Auth\Domain\RepositoryInterfaces\AdminRepositoryInterface;
use App\Features\Auth\Infra\Mappers\AdminMapper;
use App\Features\Auth\Infra\Models\AdminModel;
use Illuminate\Support\Facades\Crypt;

final class EloquentAdminRepository implements AdminRepositoryInterface
{
    public function __construct(
        private readonly AdminMapper $mapper,
    ) {
    }

    public function findById(int $id): ?Admin
    {
        return $this->mapOrNull(AdminModel::query()->find($id));
    }

    public function findFirst(): ?Admin
    {
        return $this->mapOrNull(AdminModel::query()->orderBy('id')->first());
    }

    public function findByLoginOrEmail(string $loginOrEmail): ?Admin
    {
        $model = AdminModel::query()
            ->where('login', $loginOrEmail)
            ->orWhere('email', $loginOrEmail)
            ->first();

        return $this->mapOrNull($model);
    }

    public function findByGithubId(string $githubId): ?Admin
    {
        return $this->mapOrNull(AdminModel::query()->where('github_id', $githubId)->first());
    }

    public function githubIdTakenByOther(string $githubId, int $excludeAdminId): bool
    {
        return AdminModel::query()
            ->where('github_id', $githubId)
            ->where('id', '!=', $excludeAdminId)
            ->exists();
    }

    public function create(string $name, string $email, string $login, string $plainPassword): Admin
    {
        $model = AdminModel::query()->create([
            'name' => $name,
            'email' => $email,
            'login' => $login,
            'password' => $plainPassword,
            'totp_enabled' => false,
            'totp_failed_attempts' => 0,
            'session_version' => 0,
        ]);

        return $this->mapper->toEntity($model->fresh());
    }

    public function save(Admin $admin): void
    {
        $this->model($admin->toId())->update([
            'name' => $admin->toName(),
            'email' => $admin->toEmail(),
            'login' => $admin->toLogin(),
            'github_id' => $admin->toGithubId(),
            'github_token' => $admin->toGithubToken() !== null ? Crypt::encryptString($admin->toGithubToken()) : null,
            'session_version' => $admin->toSessionVersion(),
        ]);
    }

    public function changePassword(int $adminId, string $plainPassword): void
    {
        $this->model($adminId)->update(['password' => $plainPassword]);
    }

    public function incrementSessionVersion(int $adminId): int
    {
        $model = $this->model($adminId);
        $model->increment('session_version');

        return (int) $model->fresh()->session_version;
    }

    private function model(int $adminId): AdminModel
    {
        return AdminModel::query()->findOrFail($adminId);
    }

    private function mapOrNull(?AdminModel $model): ?Admin
    {
        return $model === null ? null : $this->mapper->toEntity($model);
    }
}
