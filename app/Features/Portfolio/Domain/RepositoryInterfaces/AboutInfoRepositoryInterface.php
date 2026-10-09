<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Domain\RepositoryInterfaces;

use App\Features\Portfolio\Domain\Entities\AboutInfo;

/**
 * Persistence of the single-row `about_info` aggregate (id = 1
 * convention) including its translations.
 */
interface AboutInfoRepositoryInterface
{
    public function find(): ?AboutInfo;

    /**
     * Inserts or updates the single row plus its translations
     * (translation rows for locales absent from the entity are removed).
     */
    public function save(AboutInfo $aboutInfo): void;
}
