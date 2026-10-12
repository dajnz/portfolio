<?php

declare(strict_types=1);

namespace App\Features\Shared\Domain\RepositoryInterfaces;

/**
 * Read-only access to the contacts aggregate for cross-feature consumers
 * (the public console greeting).
 */
interface ContactReadRepositoryInterface
{
    /**
     * All contacts ordered by `sort_order`, with the name locale-resolved
     * (`en` fallback) and the raw value.
     *
     * @return array<int, array{name: string, value: string}>
     */
    public function findAll(string $locale): array;
}
