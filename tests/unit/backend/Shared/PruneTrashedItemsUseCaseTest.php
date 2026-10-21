<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Shared;

use App\Features\Shared\App\UseCases\PruneTrashedItemsUseCase;
use DateTimeImmutable;
use Tests\Support\Fakes\FakeSettingsReadRepository;
use Tests\Support\Fakes\FakeTrashedItemPruner;

describe('PruneTrashedItemsUseCase', function (): void {
    it('applies the configured retention window to every pruner', function (): void {
        $settings = new FakeSettingsReadRepository(['trash_retention_days' => 7]);
        $first = new FakeTrashedItemPruner(deleted: 3);
        $second = new FakeTrashedItemPruner(deleted: 2);

        $result = (new PruneTrashedItemsUseCase($settings, [$first, $second]))->prune();

        expect($result->retentionDays)->toBe(7)
            ->and($result->deleted)->toBe(5)
            ->and($first->cutoffs)->toHaveCount(1)
            ->and($second->cutoffs)->toHaveCount(1)
            ->and($first->cutoffs[0]->diff(new DateTimeImmutable('-7 days'))->days)->toBe(0);
    });

    it('does nothing when pruning is disabled', function (): void {
        $settings = new FakeSettingsReadRepository(['trash_retention_days' => 0]);
        $pruner = new FakeTrashedItemPruner(deleted: 3);

        $result = (new PruneTrashedItemsUseCase($settings, [$pruner]))->prune();

        expect($result->retentionDays)->toBe(0)
            ->and($result->deleted)->toBe(0)
            ->and($pruner->cutoffs)->toBe([]);
    });
});
