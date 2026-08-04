<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use App\Features\Shared\App\UseCases\SearchSkillsUseCase;
use App\Features\Shared\Domain\DTOs\TechSkillDTO;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

/**
 * Autocomplete endpoint for tech skills (`GET /api/skills/search`).
 */
final class SkillSearchController
{
    public function __construct(
        private readonly SearchSkillsUseCase $useCase,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $skills = $this->useCase->search($request->string('q', '')->toString(), (string) App::getLocale());

        return response()->json([
            'data' => array_map(
                static fn (TechSkillDTO $skill): array => [
                    'id' => $skill->id,
                    'name' => $skill->name,
                    'icon' => $skill->icon,
                ],
                $skills,
            ),
        ]);
    }
}
