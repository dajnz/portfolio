<?php

declare(strict_types=1);

namespace App\Features\Resume\Domain\RepositoryInterfaces;

use App\Features\Resume\Domain\Entities\ResumeConfig;

/**
 * Persistence of the single-row resume configuration aggregate
 * (`resume_config` + `resume_config_translations`, owned exclusively by
 * the Resume feature — database.md:128).
 */
interface ResumeConfigRepositoryInterface
{
    public function find(): ?ResumeConfig;

    /**
     * Upserts the config row (id = 1) and reconciles its translations.
     */
    public function save(ResumeConfig $config): void;
}
