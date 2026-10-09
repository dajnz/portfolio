<?php

declare(strict_types=1);

namespace App\Features\Shared\App\UseCases;

use App\Features\Shared\App\BaseUseCase;
use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * Autocomplete search over tech skill names (`GET /api/skills/search`):
 * case-insensitive substring match on the name, ordered by name ascending
 * and capped at 20 results.
 */
final class SearchSkillsUseCase extends BaseUseCase
{
    private const MAX_RESULTS = 20;

    public function __construct(
        private readonly TechSkillReadRepositoryInterface $repository,
    ) {
    }

    /**
     * @return TechSkill[]
     */
    public function search(string $query, string $locale): array
    {
        if ($query === '') {
            return [];
        }

        $needle = mb_strtolower($query);

        $matches = array_values(array_filter(
            $this->repository->findAll($locale),
            static fn (TechSkill $skill): bool => str_contains(mb_strtolower($skill->toName()), $needle),
        ));

        usort(
            $matches,
            static fn (TechSkill $a, TechSkill $b): int => strcmp(
                mb_strtolower($a->toName()),
                mb_strtolower($b->toName()),
            ),
        );

        return array_slice($matches, 0, self::MAX_RESULTS);
    }
}
