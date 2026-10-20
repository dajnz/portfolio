<?php

declare(strict_types=1);

namespace App\Features\Analytics\App\UseCases;

use App\Features\Analytics\App\DTOs\AnalyticsDigestDto;
use App\Features\Analytics\App\Services\DailyDigestBuilder;
use DateTimeImmutable;

/**
 * The daily digest entry point: delegates the payload assembly to
 * `DailyDigestBuilder`.
 */
final class GetDailyDigestUseCase
{
    public function __construct(
        private readonly DailyDigestBuilder $builder,
    ) {
    }

    public function digest(DateTimeImmutable $day): AnalyticsDigestDto
    {
        return $this->builder->digest($day);
    }
}
