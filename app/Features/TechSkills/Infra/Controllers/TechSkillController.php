<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Controllers;

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\TechSkills\App\UseCases\DestroySkillUseCase;
use App\Features\TechSkills\App\UseCases\ListSkillGroupsUseCase;
use App\Features\TechSkills\App\UseCases\ReorderSkillsUseCase;
use App\Features\TechSkills\App\UseCases\StoreSkillUseCase;
use App\Features\TechSkills\App\UseCases\UpdateSkillUseCase;
use App\Features\TechSkills\Domain\Exceptions\DuplicateSkillNameException;
use App\Features\TechSkills\Infra\Requests\ReorderSkillsRequest;
use App\Features\TechSkills\Infra\Requests\StoreSkillRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * Skills admin surface: the consolidated skills list (groups with their
 * skills) plus skill CRUD and the `{ "skills": [...] }` reorder that also
 * persists cross-group moves. One action → one use case; the list props
 * are mapped from the hydrated entities here.
 */
final class TechSkillController
{
    public function __construct(
        private readonly ListSkillGroupsUseCase $listCase,
        private readonly StoreSkillUseCase $storeCase,
        private readonly UpdateSkillUseCase $updateCase,
        private readonly DestroySkillUseCase $destroyCase,
        private readonly ReorderSkillsUseCase $reorderCase,
    ) {
    }

    public function index(ResponseFactory $inertia): Response
    {
        return $inertia->render('TechSkills/Admin/Pages/SkillsListPage', [
            'skillGroups' => array_map(
                fn (TechSkillGroup $group): array => $this->groupData($group),
                $this->listCase->list(),
            ),
        ]);
    }

    public function store(StoreSkillRequest $request): RedirectResponse
    {
        try {
            $this->storeCase->store(
                name: $request->input('name'),
                icon: $request->input('icon'),
                proficiency: (int) $request->input('proficiency'),
                groupId: (int) $request->input('skill_group_id'),
                includeInResume: $request->boolean('include_in_resume'),
                sortOrder: (int) $request->input('sort_order', 0),
            );
        } catch (DuplicateSkillNameException) {
            throw ValidationException::withMessages(['name' => __('validation.unique', ['attribute' => __('common.name')])]);
        }

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function update(StoreSkillRequest $request, int $skill): RedirectResponse
    {
        try {
            $this->updateCase->update(
                id: $skill,
                name: $request->input('name'),
                icon: $request->input('icon'),
                proficiency: (int) $request->input('proficiency'),
                groupId: (int) $request->input('skill_group_id'),
                includeInResume: $request->boolean('include_in_resume'),
                sortOrder: (int) $request->input('sort_order', 0),
            );
        } catch (DuplicateSkillNameException) {
            throw ValidationException::withMessages(['name' => __('validation.unique', ['attribute' => __('common.name')])]);
        }

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    public function destroy(Request $request, int $skill): RedirectResponse
    {
        $this->destroyCase->destroy($skill);

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.deleted')]);
    }

    public function reorder(ReorderSkillsRequest $request): RedirectResponse
    {
        $this->reorderCase->reorder($request->input('skills', []));

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function groupData(TechSkillGroup $group): array
    {
        return [
            'id' => $group->toId(),
            'name' => $group->nameFor(app()->getLocale()),
            // Per-locale names map — the inline LocaleTabs editor PUTs the
            // full map back (StoreSkillGroupRequest requires every active
            // locale's name).
            'names' => $group->groupNamesByLocale(),
            'icon' => $group->toIcon(),
            'sort_order' => $group->toSortOrder(),
            'skills' => array_map(
                fn (TechSkill $skill): array => [
                'id' => $skill->toId(),
                'name' => $skill->toName(),
                'icon' => $skill->toIcon(),
                'proficiency' => $skill->toProficiency(),
                'sort_order' => $skill->toSortOrder(),
                'include_in_resume' => $skill->toIncludeInResume(),
                ],
                $group->toSkills(),
            ),
        ];
    }
}
