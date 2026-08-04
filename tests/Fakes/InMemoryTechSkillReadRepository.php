<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Features\Shared\Domain\DTOs\GroupedTechSkillsDTO;
use App\Features\Shared\Domain\DTOs\TechSkillDTO;
use App\Features\Shared\Domain\RepositoryInterfaces\TechSkillReadRepositoryInterface;

/**
 * In-memory repository fake for unit tests — only the methods exercised by
 * `SearchSkillsUseCase` are implemented (testing.md allows in-memory
 * repository implementations for interface-based use cases).
 */
final class InMemoryTechSkillReadRepository implements TechSkillReadRepositoryInterface
{
    public ?string $lastLocale = null;

    /**
     * @param  TechSkillDTO[]  $skills
     */
    public function __construct(private readonly array $skills)
    {
    }

    /**
     * @return TechSkillDTO[]
     */
    public function findAll(string $locale): array
    {
        $this->lastLocale = $locale;

        return $this->skills;
    }

    /**
     * @param  int[]  $ids
     * @return TechSkillDTO[]
     */
    public function findByIds(array $ids, string $locale): array
    {
        return array_values(array_filter(
            $this->skills,
            static fn (TechSkillDTO $skill): bool => in_array($skill->id, $ids, true),
        ));
    }

    /**
     * @return GroupedTechSkillsDTO[]
     */
    public function findAllGrouped(string $locale): array
    {
        return [];
    }
}
