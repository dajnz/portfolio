<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Controllers;

use App\Features\TechSkills\App\UseCases\DestroySkillGroupUseCase;
use App\Features\TechSkills\App\UseCases\ReorderSkillGroupsUseCase;
use App\Features\TechSkills\App\UseCases\StoreSkillGroupUseCase;
use App\Features\TechSkills\App\UseCases\UpdateSkillGroupUseCase;
use App\Features\TechSkills\Infra\Requests\ReorderSkillGroupsRequest;
use App\Features\TechSkills\Infra\Requests\StoreSkillGroupRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Skill group admin surface (routes.md:72-75): group CRUD + the ADR-0005
 * `{ "ids": [...] }` reorder. One action → one use case.
 */
final class TechSkillGroupController
{
    public function __construct(
        private readonly StoreSkillGroupUseCase $storeCase,
        private readonly UpdateSkillGroupUseCase $updateCase,
        private readonly DestroySkillGroupUseCase $destroyCase,
        private readonly ReorderSkillGroupsUseCase $reorderCase,
    ) {
    }

    public function store(StoreSkillGroupRequest $request): RedirectResponse
    {
        $this->storeCase->store(
            icon: $request->input('icon'),
            sortOrder: (int) $request->input('sort_order', 0),
            names: $request->input('name', []),
        );

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function update(StoreSkillGroupRequest $request, int $skillGroup): RedirectResponse
    {
        $this->updateCase->update(
            id: $skillGroup,
            icon: $request->input('icon'),
            sortOrder: (int) $request->input('sort_order', 0),
            names: $request->input('name', []),
        );

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function destroy(Request $request, int $skillGroup): RedirectResponse
    {
        $this->destroyCase->destroy($skillGroup);

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function reorder(ReorderSkillGroupsRequest $request): RedirectResponse
    {
        $this->reorderCase->reorder(array_map('intval', $request->input('ids', [])));

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }
}
