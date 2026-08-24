<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\App\DTOs\BackupListDto;
use App\Features\Backup\Domain\RepositoryInterfaces\BackupRepositoryInterface;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;

use function array_slice;
use function ceil;
use function count;
use function max;
use function min;

/**
 * The `GET /admin/backups` page data (openapi/inertia/backup.yaml
 * `BackupListPageProps`): the non-trashed backups, newest first,
 * page-numbered by the `items_per_page` setting (the contract's
 * length-aware `PaginationMeta`), plus `encryption_key_prompt` — true
 * when no active key exists (backup.md:548, the show-once modal).
 * The backup list is small (retention-bounded) — the pagination
 * slices the repository's `listActive()` in memory. Settings reads go
 * through the Shared `SettingsReadRepositoryInterface` (B306).
 */
final class GetBackupListUseCase
{
    public function __construct(
        private readonly BackupRepositoryInterface $backups,
        private readonly EncryptionKeyRepositoryInterface $keys,
        private readonly SettingsReadRepositoryInterface $settings,
    ) {
    }

    public function get(int $page = 1): BackupListDto
    {
        $perPage = (int) $this->settings->value('items_per_page', 10);
        $all = $this->backups->listActive();
        $total = count($all);
        $lastPage = max(1, (int) ceil($total / max(1, $perPage)));
        $page = max(1, min($page, $lastPage));

        $slice = array_slice($all, ($page - 1) * $perPage, $perPage);
        $from = $total === 0 ? null : ($page - 1) * $perPage + 1;
        $to = $total === 0 ? null : min($page * $perPage, $total);

        return new BackupListDto(
            backups: $slice,
            currentPage: $page,
            from: $from,
            to: $to,
            perPage: $perPage,
            lastPage: $lastPage,
            total: $total,
            encryptionKeyPrompt: $this->keys->findActive() === null,
        );
    }
}
