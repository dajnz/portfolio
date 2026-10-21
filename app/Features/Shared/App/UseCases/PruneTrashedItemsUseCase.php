<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Shared\App\Contracts\TrashedItemPrunerInterface;
use App\Features\Shared\App\DTOs\PruneTrashedItemsResultDto;
use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;
use DateTimeImmutable;

/**
 * Applies the configured trash retention window across every registered
 * entity pruner. A retention of `0` disables pruning.
 */
final class PruneTrashedItemsUseCase
{
    private const RETENTION_SETTING_KEY = 'trash_retention_days';
    private const DEFAULT_RETENTION_DAYS = 30;

    /**
     * @param  TrashedItemPrunerInterface[]  $pruners
     */
    public function __construct(
        private readonly SettingsReadRepositoryInterface $settings,
        private readonly array $pruners,
    ) {
    }

    public function prune(): PruneTrashedItemsResultDto
    {
        $retentionDays = (int) $this->settings->value(
            self::RETENTION_SETTING_KEY,
            self::DEFAULT_RETENTION_DAYS,
        );

        if ($retentionDays <= 0) {
            return new PruneTrashedItemsResultDto(retentionDays: 0, deleted: 0);
        }

        $cutoff = (new DateTimeImmutable())->modify("-{$retentionDays} days");
        $deleted = 0;

        foreach ($this->pruners as $pruner) {
            $deleted += $pruner->prune($cutoff);
        }

        return new PruneTrashedItemsResultDto($retentionDays, $deleted);
    }
}
