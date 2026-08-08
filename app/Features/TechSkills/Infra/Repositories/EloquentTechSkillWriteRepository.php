<?php

declare(strict_types=1);

namespace App\Features\TechSkills\Infra\Repositories;

use App\Features\Shared\Domain\Entities\TechSkill;
use App\Features\Shared\Domain\Entities\TechSkillGroup;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\TechSkillGroupName;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\TechSkills\Domain\RepositoryInterfaces\TechSkillWriteRepositoryInterface;
use App\Features\TechSkills\Infra\Mappers\TechSkillGroupMapper;
use App\Features\TechSkills\Infra\Mappers\TechSkillMapper;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent-backed write repository for the TechSkills feature, over the
 * shared read-side models (database.md:130-134). Group writes go through
 * a transaction with the name sync; both reorders apply the
 * zero-indexed positions with a single batched update (A4,
 * content-management.md:155 style) — no per-row queries. Skills are
 * hydrated with their group context ({@see SkillGroupVo}) and attached
 * to their group via the domain's {@see TechSkillGroup::addSkill()}.
 */
final class EloquentTechSkillWriteRepository implements TechSkillWriteRepositoryInterface
{
    public function __construct(
        private readonly TechSkillMapper $skillMapper,
        private readonly TechSkillGroupMapper $groupMapper,
    ) {
    }

    public function findAllGroups(): array
    {
        $groupRows = SkillGroupModel::query()
            ->with('translations')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $skillRowsByGroupId = [];
        foreach ($this->skillsQuery()->get() as $skillRow) {
            $skillRowsByGroupId[(int) $skillRow->skill_group_id][] = $skillRow;
        }

        $result = [];
        foreach ($groupRows as $groupRow) {
            $group = $this->groupMapper->toEntity($groupRow);
            $groupVo = $group->toGroupVo();

            foreach ($skillRowsByGroupId[(int) $groupRow->id] ?? [] as $skillRow) {
                $group->addSkill(
                    $this->skillMapper->toEntity($skillRow, $groupVo),
                    new SortOrder((int) $skillRow->sort_order),
                );
            }

            $result[] = $group;
        }

        return $result;
    }

    public function findGroupById(int $id): ?TechSkillGroup
    {
        $model = SkillGroupModel::query()->with('translations')->find($id);

        return $model === null ? null : $this->groupMapper->toEntity($model);
    }

    public function skillNameExists(string $name, ?int $ignoreId = null): bool
    {
        $query = SkillModel::query()
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))]);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    public function saveGroup(TechSkillGroup $group): void
    {
        DB::transaction(function () use ($group): void {
            $model = SkillGroupModel::query()->updateOrCreate(
                ['id' => $group->toId()],
                $this->groupMapper->toRow($group),
            );

            $this->syncGroupNames($model->id, $group->toGroupNames());
        });
    }

    public function deleteGroup(int $id): void
    {
        SkillGroupModel::query()->whereKey($id)->delete();
    }

    public function reorderGroups(array $ids): void
    {
        if ($ids === []) {
            return;
        }

        $this->batchUpdate('skill_groups', array_values(array_map('intval', $ids)));
    }

    public function saveSkill(TechSkill $skill): void
    {
        SkillModel::query()->updateOrCreate(
            ['id' => $skill->toId()],
            $this->skillMapper->toRow($skill),
        );
    }

    public function deleteSkill(int $id): void
    {
        SkillModel::query()->whereKey($id)->delete();
    }

    public function reorderSkills(array $skills): void
    {
        if ($skills === []) {
            return;
        }

        DB::transaction(function () use ($skills): void {
            $ids = [];
            $orderCases = [];
            $groupCases = [];

            foreach (array_values($skills) as $position => $item) {
                $id = (int) $item['id'];
                $groupId = (int) $item['skill_group_id'];

                $ids[] = $id;
                $orderCases[] = sprintf('WHEN %d THEN %d', $id, $position);
                $groupCases[] = sprintf('WHEN %d THEN %d', $id, $groupId);
            }

            // Single batched update — no per-row N+1 writes. The CASE
            // operands are validated integers interpolated directly into
            // the expression (DB::raw carries no bindings).
            DB::table('skills')
                ->whereIn('id', $ids)
                ->update([
                    'sort_order' => DB::raw('CASE id '.implode(' ', $orderCases).' END'),
                    'skill_group_id' => DB::raw('CASE id '.implode(' ', $groupCases).' END'),
                ]);
        });
    }

    /**
     * Batched single-update sort-order write (A4): the CASE operands are
     * validated integers interpolated directly into the expression
     * (DB::raw carries no bindings).
     *
     * @param  int[]  $ids  Row IDs in the new drag order.
     */
    private function batchUpdate(string $table, array $ids): void
    {
        $cases = [];
        foreach ($ids as $position => $id) {
            $cases[] = sprintf('WHEN %d THEN %d', $id, $position);
        }

        DB::table($table)
            ->whereIn('id', $ids)
            ->update([
                'sort_order' => DB::raw('CASE id '.implode(' ', $cases).' END'),
            ]);
    }

    /**
     * Upserts the given names and removes rows of the group whose
     * locales are absent from the set (A2-style reconciliation).
     *
     * @param  TechSkillGroupName[]  $groupNames
     */
    private function syncGroupNames(int $groupId, array $groupNames): void
    {
        $localeNames = [];
        foreach ($groupNames as $groupName) {
            $localeNames[$groupName->toLocale()] = $groupName->toName();
        }

        SkillGroupTranslationModel::query()
            ->where('skill_group_id', $groupId)
            ->whereNotIn('locale', array_keys($localeNames))
            ->delete();

        if ($localeNames === []) {
            return;
        }

        SkillGroupTranslationModel::query()->upsert(
            array_map(
                fn (string $locale, string $name): array => [
                    'skill_group_id' => $groupId,
                    'locale' => $locale,
                    'name' => $name,
                ],
                array_keys($localeNames),
                array_values($localeNames),
            ),
            ['skill_group_id', 'locale'],
            ['name'],
        );
    }

    /**
     * Skills ordered for the group-scoped admin list.
     *
     * @return EloquentBuilder<SkillModel>
     */
    private function skillsQuery(): EloquentBuilder
    {
        return SkillModel::query()
            ->orderBy('skill_group_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }
}
