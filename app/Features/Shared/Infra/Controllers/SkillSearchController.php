<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Controllers;

use App\Features\Shared\App\UseCases\SearchSkillsUseCase;
use App\Features\Shared\Domain\Entities\TechSkill;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Response;

/**
 * Autocomplete endpoint for tech skills (`GET /api/skills/search`).
 */
final class SkillSearchController
{
    public function __construct(
        private readonly SearchSkillsUseCase $searchSkillsCase,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $skills = $this->searchSkillsCase->search($request->string('q', '')->toString(), (string) App::getLocale());

        return Response::json([
            'data' => array_map(
                static fn (TechSkill $skill): array => [
                    'id' => $skill->toId(),
                    'name' => $skill->toName(),
                    'icon' => $skill->toIcon(),
                ],
                $skills,
            ),
        ]);
    }
}
