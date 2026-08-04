<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\Domain\DTOs\TechSkillDTO;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * Autocomplete search over tech skill names (`GET /api/skills/search`).
 *
 * Matches case-insensitively on the skill name, orders matches by name
 * ascending, and caps the result at 20 items.
 */
final class SearchSkillsUseCase extends BaseUseCase
{
    private const MAX_RESULTS = 20;

    public function __construct(
        private readonly TechSkillReadRepositoryInterface $repository,
    ) {
    }

    /**
     * @return TechSkillDTO[]
     */
    public function search(string $query, string $locale): array
    {
        if ($query === '') {
            return [];
        }

        $needle = mb_strtolower($query);

        $matches = array_values(array_filter(
            $this->repository->findAll($locale),
            static fn (TechSkillDTO $skill): bool => str_contains(mb_strtolower($skill->name), $needle),
        ));

        usort(
            $matches,
            static fn (TechSkillDTO $a, TechSkillDTO $b): int => strcmp(
                mb_strtolower($a->name),
                mb_strtolower($b->name),
            ),
        );

        return array_slice($matches, 0, self::MAX_RESULTS);
    }
}
