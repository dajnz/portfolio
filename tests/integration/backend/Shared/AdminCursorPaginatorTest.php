<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\Shared\Infra\Persistence\Cursor;
use App\Features\Shared\Infra\Persistence\CursorPage;
use App\Features\Shared\Infra\Persistence\PaginationRequest;
use App\Features\Shared\Infra\Persistence\SortSpec;
use App\Features\Shared\Infra\Services\AdminCursorPaginator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * @return SkillGroupModel
 */
function createCursorTestGroup(): SkillGroupModel
{
    return SkillGroupModel::factory()->create(['sort_order' => 1]);
}

/**
 * @return SkillModel
 */
function createCursorTestSkill(SkillGroupModel $group, string $name, int $proficiency): SkillModel
{
    return SkillModel::factory()->create([
        'skill_group_id' => $group->id,
        'name' => $name,
        'proficiency' => $proficiency,
        'sort_order' => 0,
    ]);
}

/**
 * @param  array<string, mixed>  $params
 * @return Request
 */
function cursorRequest(array $params): Request
{
    return Request::create('/admin/test-list', 'GET', $params);
}

/**
 * @return int[]
 */
function pageItemIds(CursorPage $page): array
{
    return array_map(static fn (SkillModel $skill): int => $skill->id, $page->items);
}

describe('AdminCursorPaginator', function (): void {
    beforeEach(function (): void {
        $this->paginator = new AdminCursorPaginator();
    });

    it('returns the newest-first first page with null sort values when no params are given', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        $page = $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10);

        expect(pageItemIds($page))->toBe(array_reverse($ids))
            ->and($page->total)->toBe(3)
            ->and($page->perPage)->toBe(10)
            ->and($page->currentPage)->toBe(1)
            ->and($page->sorting->sortColumn)->toBe('id')
            ->and($page->sorting->sortDirection)->toBe('desc')
            ->and($page->cursors->page)->toBe(1)
            ->and($page->cursors->first->id)->toBe(end($ids))
            ->and($page->cursors->first->sortValue)->toBeNull()
            ->and($page->cursors->last->id)->toBe(reset($ids))
            ->and($page->cursors->last->sortValue)->toBeNull();
    });

    it('serializes to the shared response shape', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);

        $page = $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10);

        expect(array_keys($page->toArray()))->toBe(['items', 'total', 'per_page', 'current_page', 'sort_column', 'sort_direction', 'cursors'])
            ->and(array_keys($page->toArray()['cursors']))->toBe(['first', 'last', 'page'])
            ->and(array_keys($page->toArray()['cursors']['first']))->toBe(['id', 'sort_value']);
    });

    it('walks forward with a next cursor on id sort', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go', 'Rust', 'Docker', 'K8s', 'Redis', 'Postgres'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        $first = $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 4);

        $next = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'cursor' => ['id' => $first->cursors->last->id, 'sort_value' => null],
            ]),
            4,
        );

        expect(pageItemIds($next))->toBe([$ids[5], $ids[4], $ids[3], $ids[2]])
            ->and($next->currentPage)->toBe(2)
            ->and($next->cursors->page)->toBe(2)
            ->and($next->cursors->first->id)->toBe($ids[5])
            ->and($next->cursors->last->id)->toBe($ids[2]);
    });

    it('walks backward with a before cursor on id sort', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go', 'Rust', 'Docker', 'K8s', 'Redis', 'Postgres'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        $second = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest(['cursor' => ['id' => $ids[6], 'sort_value' => null]]),
            4,
        );

        $previous = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'cursor' => ['id' => $second->cursors->first->id, 'sort_value' => null, 'before' => '1'],
            ]),
            4,
        );

        expect(pageItemIds($previous))->toBe(array_slice(array_reverse($ids), 0, 4))
            ->and($previous->currentPage)->toBe(1)
            ->and($previous->cursors->page)->toBe(1);
    });

    it('walks forward on ascending id sort', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go', 'Rust', 'Docker', 'K8s', 'Redis', 'Postgres'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        $first = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest(['sort_direction' => 'asc']),
            4,
        );

        expect(pageItemIds($first))->toBe(array_slice($ids, 0, 4))
            ->and($first->currentPage)->toBe(1);

        $next = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'sort_direction' => 'asc',
                'cursor' => ['id' => $first->cursors->last->id, 'sort_value' => null],
            ]),
            4,
        );

        expect(pageItemIds($next))->toBe(array_slice($ids, 4, 4))
            ->and($next->currentPage)->toBe(2);
    });

    it('paginates by a composite sort column without skipping or duplicating tied rows', function (): void {
        $group = createCursorTestGroup();
        $names = ['Laravel', 'PHP', 'Vue.js', 'Go', 'Rust', 'Docker', 'K8s', 'Redis', 'Postgres', 'Nginx', 'Git', 'Bash'];
        $proficiencies = [5, 5, 5, 4, 4, 4, 4, 4, 3, 3, 2, 1];

        $ids = [];
        foreach ($names as $i => $name) {
            $ids[] = createCursorTestSkill($group, $name, $proficiencies[$i])->id;
        }

        $paginator = new AdminCursorPaginator(allowedSortColumns: ['proficiency']);

        $first = $paginator->paginate(
            SkillModel::query(),
            cursorRequest(['sort_column' => 'proficiency']),
            5,
        );

        $second = $paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'sort_column' => 'proficiency',
                'cursor' => ['id' => $first->cursors->last->id, 'sort_value' => $first->cursors->last->sortValue],
            ]),
            5,
        );

        $third = $paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'sort_column' => 'proficiency',
                'cursor' => ['id' => $second->cursors->last->id, 'sort_value' => $second->cursors->last->sortValue],
            ]),
            5,
        );

        $expected = $ids;
        usort($expected, static function (int $a, int $b) use ($proficiencies, $ids): int {
            $indexA = array_search($a, $ids, true);
            $indexB = array_search($b, $ids, true);

            return $proficiencies[$indexB] <=> $proficiencies[$indexA]
                ?: $b <=> $a;
        });

        $actual = array_merge(pageItemIds($first), pageItemIds($second), pageItemIds($third));

        expect($actual)->toBe($expected)
            ->and(count(array_unique($actual)))->toBe(count($actual))
            ->and($second->currentPage)->toBe(2)
            ->and($third->currentPage)->toBe(3)
            ->and($first->cursors->last->sortValue)->toBe(4)
            ->and($third->cursors->last->sortValue)->toBe(1);
    });

    it('reverses the composite predicate for ascending sorts', function (): void {
        $group = createCursorTestGroup();
        $proficiencies = [5, 5, 3, 3, 1];
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'Go', 'Rust'] as $i => $name) {
            $ids[] = createCursorTestSkill($group, $name, $proficiencies[$i])->id;
        }

        $paginator = new AdminCursorPaginator(allowedSortColumns: ['proficiency']);

        $first = $paginator->paginate(
            SkillModel::query(),
            cursorRequest(['sort_column' => 'proficiency', 'sort_direction' => 'asc']),
            2,
        );

        expect(pageItemIds($first))->toBe([$ids[4], $ids[2]])
            ->and($first->cursors->last->sortValue)->toBe(3);

        $next = $paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'sort_column' => 'proficiency',
                'sort_direction' => 'asc',
                'cursor' => ['id' => $first->cursors->last->id, 'sort_value' => $first->cursors->last->sortValue],
            ]),
            2,
        );

        expect(pageItemIds($next))->toBe([$ids[3], $ids[0]])
            ->and($next->currentPage)->toBe(2);
    });

    it('walks backward on a composite sort via a before cursor', function (): void {
        $group = createCursorTestGroup();
        $proficiencies = [5, 5, 5, 4, 4, 4, 4, 4, 3, 3, 2, 1];
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'Go', 'Rust', 'Docker', 'K8s', 'Redis', 'Postgres', 'Nginx', 'Git', 'Bash'] as $i => $name) {
            $ids[] = createCursorTestSkill($group, $name, $proficiencies[$i])->id;
        }

        $paginator = new AdminCursorPaginator(allowedSortColumns: ['proficiency']);

        $second = $paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'sort_column' => 'proficiency',
                'cursor' => ['id' => $ids[5], 'sort_value' => 4],
            ]),
            5,
        );

        $previous = $paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'sort_column' => 'proficiency',
                'cursor' => ['id' => $second->cursors->first->id, 'sort_value' => $second->cursors->first->sortValue, 'before' => '1'],
            ]),
            5,
        );

        expect(pageItemIds($previous))->toBe([$ids[2], $ids[1], $ids[0], $ids[7], $ids[6]])
            ->and($previous->currentPage)->toBe(1)
            ->and($previous->cursors->page)->toBe(1);
    });

    it('resolves an arbitrary page jump via the positioning subquery on id sort', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go', 'Rust', 'Docker', 'K8s', 'Redis', 'Postgres'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        $page = $this->paginator->paginate(SkillModel::query(), cursorRequest(['page' => 3]), 4);

        expect(pageItemIds($page))->toBe([$ids[1], $ids[0]])
            ->and($page->currentPage)->toBe(3)
            ->and($page->cursors->page)->toBe(3);
    });

    it('resolves a page jump on a composite sort', function (): void {
        $group = createCursorTestGroup();
        $proficiencies = [5, 5, 5, 4, 4, 4, 4, 4, 3, 3, 2, 1];
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'Go', 'Rust', 'Docker', 'K8s', 'Redis', 'Postgres', 'Nginx', 'Git', 'Bash'] as $i => $name) {
            $ids[] = createCursorTestSkill($group, $name, $proficiencies[$i])->id;
        }

        $paginator = new AdminCursorPaginator(allowedSortColumns: ['proficiency']);

        $page = $paginator->paginate(
            SkillModel::query(),
            cursorRequest(['sort_column' => 'proficiency', 'page' => 3]),
            5,
        );

        expect(pageItemIds($page))->toBe([$ids[10], $ids[11]])
            ->and($page->currentPage)->toBe(3);
    });

    it('redirects to the last valid page when the requested page lies beyond it (B175)', function (): void {
        $group = createCursorTestGroup();
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go'] as $name) {
            createCursorTestSkill($group, $name, 5);
        }

        // 5 items @ 4 per page → 2 pages; ?page=3 is beyond → redirect
        // to ?page=2 with the other filters preserved (no empty page the
        // user cannot navigate away from).
        try {
            $this->paginator->paginate(SkillModel::query(), cursorRequest(['page' => 3, 'q' => 'x']), 4);
            $this->fail('Expected a redirect for a beyond-range page.');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();

            expect($response->getStatusCode())->toBe(302)
                ->and($response->headers->get('Location'))->toContain('page=2')
                ->and($response->headers->get('Location'))->toContain('q=x');
        }
    });

    it('redirects to the page containing the cursor when the cursor page was emptied by deletes (B175)', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        // 5 items @ 4 per page (id desc) → page 1 = [5,4,3,2], page 2 =
        // [1]. Walk to page 2 via the next cursor (the last item of page
        // 1), delete the page-2 item — the cursor now points past the
        // last remaining item, so the request would render an empty page
        // with no paginator. It must redirect to the page containing the
        // cursor (?page=1), filters preserved.
        $first = $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 4);
        SkillModel::findOrFail($ids[0])->delete();

        try {
            $this->paginator->paginate(
                SkillModel::query(),
                cursorRequest([
                    'cursor' => ['id' => $first->cursors->last->id, 'sort_value' => null],
                    'q' => 'x',
                ]),
                4,
            );
            $this->fail('Expected a redirect for an emptied cursor page.');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();

            expect($response->getStatusCode())->toBe(302)
                ->and($response->headers->get('Location'))->toContain('page=1')
                ->and($response->headers->get('Location'))->toContain('q=x')
                ->and($response->headers->get('Location'))->not->toContain('cursor');
        }
    });

    it('redirects to page 1 when a previous-page cursor request has nothing before it (B175)', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        // Page 2 = [1] (id desc @ 4/page). Delete the whole first page —
        // the previous-page cursor (the first item of page 2, now the
        // first item overall) has nothing before it → redirect to page 1
        // so the remaining item stays reachable.
        foreach (array_slice($ids, 1) as $id) {
            SkillModel::findOrFail($id)->delete();
        }

        try {
            $this->paginator->paginate(
                SkillModel::query(),
                cursorRequest([
                    'cursor' => ['id' => $ids[0], 'sort_value' => null, 'before' => 1],
                ]),
                4,
            );
            $this->fail('Expected a redirect for an empty previous-page request.');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();

            expect($response->getStatusCode())->toBe(302)
                ->and($response->headers->get('Location'))->toContain('page=1');
        }
    });

    it('redirects to the previous page when the last page is emptied while the positioning cursor still exists (B175)', function (): void {
        $group = createCursorTestGroup();
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript'] as $name) {
            createCursorTestSkill($group, $name, 5);
        }

        // 4 items @ 4 per page → page 2 exists as a page number only.
        // Requesting it directly (page jump) positions on the last item
        // of page 1 — the fetch after it is empty. With total > 0 the
        // request must redirect to the previous page instead of
        // rendering an empty page the user cannot navigate away from.
        try {
            $this->paginator->paginate(SkillModel::query(), cursorRequest(['page' => 2, 'q' => 'x']), 4);
            $this->fail('Expected a redirect for an emptied page-number request.');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();

            expect($response->getStatusCode())->toBe(302)
                ->and($response->headers->get('Location'))->toContain('page=1')
                ->and($response->headers->get('Location'))->toContain('q=x');
        }
    });

    it('applies the base query filters to both the page and the total', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);
        createCursorTestSkill($group, 'PHP', 5);
        createCursorTestSkill($group, 'Vue.js', 4);
        createCursorTestSkill($group, 'Go', 4);

        $page = $this->paginator->paginate(
            SkillModel::query()->where('proficiency', 5),
            cursorRequest([]),
            10,
        );

        expect(pageItemIds($page))->toHaveCount(2)
            ->and($page->total)->toBe(2);
    });

    it('supports qualified id and sort columns against joined queries', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        $paginator = new AdminCursorPaginator(idColumn: 'skills.id');
        $query = SkillModel::query()->join('skill_groups', 'skill_groups.id', '=', 'skills.skill_group_id')
            ->where('skill_groups.id', $group->id)
            ->select('skills.*');

        $page = $paginator->paginate($query, cursorRequest([]), 2);

        expect(pageItemIds($page))->toBe([$ids[4], $ids[3]])
            ->and($page->cursors->last->id)->toBe($ids[3])
            ->and($page->cursors->last->sortValue)->toBeNull();

        $next = $paginator->paginate(
            SkillModel::query()->join('skill_groups', 'skill_groups.id', '=', 'skills.skill_group_id')
                ->where('skill_groups.id', $group->id)
                ->select('skills.*'),
            cursorRequest(['cursor' => ['id' => $ids[3], 'sort_value' => null]]),
            2,
        );

        expect(pageItemIds($next))->toBe([$ids[2], $ids[1]])
            ->and($next->currentPage)->toBe(2);
    });

    it('treats an id-sort cursor carrying a sort value as a mismatched page-1 request and logs a warning', function (): void {
        $group = createCursorTestGroup();
        foreach (['Laravel', 'PHP', 'Vue.js'] as $name) {
            createCursorTestSkill($group, $name, 5);
        }

        Log::shouldReceive('warning')->once();

        $page = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest(['cursor' => ['id' => 1, 'sort_value' => '2026-01-01T00:00:00+00:00']]),
            10,
        );

        expect($page->currentPage)->toBe(1)
            ->and($page->items)->toHaveCount(3);
    });

    it('treats a composite cursor without a sort value as a mismatched page-1 request and logs a warning', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);
        createCursorTestSkill($group, 'PHP', 4);

        Log::shouldReceive('warning')->once();

        $paginator = new AdminCursorPaginator(allowedSortColumns: ['proficiency']);

        $page = $paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'sort_column' => 'proficiency',
                'cursor' => ['id' => 1, 'sort_value' => null],
            ]),
            10,
        );

        expect($page->currentPage)->toBe(1)
            ->and($page->items)->toHaveCount(2);
    });

    it('treats a non-positive cursor id as a mismatched page-1 request and logs a warning', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);

        Log::shouldReceive('warning')->once();

        $page = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest(['cursor' => ['id' => 0, 'sort_value' => null]]),
            10,
        );

        expect($page->currentPage)->toBe(1)
            ->and($page->items)->toHaveCount(1);
    });

    it('ignores a page param when a valid cursor is present', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js', 'TypeScript', 'Go', 'Rust', 'Docker', 'K8s'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        $page = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest([
                'page' => 3,
                'cursor' => ['id' => $ids[5], 'sort_value' => null],
            ]),
            4,
        );

        expect(pageItemIds($page))->toBe([$ids[4], $ids[3], $ids[2], $ids[1]])
            ->and($page->currentPage)->toBe(2);
    });

    it('falls back to the id sort with a warning when the sort column is invalid', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        Log::shouldReceive('warning')->once();

        $page = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest(['sort_column' => 'proficiency; drop table skills']),
            10,
        );

        expect(pageItemIds($page))->toBe(array_reverse($ids))
            ->and($page->sorting->sortColumn)->toBe('id');
    });

    it('rejects a well-formed sort column that is not whitelisted and logs a warning', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        Log::shouldReceive('warning')->once();

        $page = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest(['sort_column' => 'proficiency']),
            10,
        );

        expect(pageItemIds($page))->toBe(array_reverse($ids))
            ->and($page->sorting->sortColumn)->toBe('id');
    });

    it('always allows the id column as the sort column without a whitelist entry', function (): void {
        $group = createCursorTestGroup();
        $ids = [];
        foreach (['Laravel', 'PHP', 'Vue.js'] as $name) {
            $ids[] = createCursorTestSkill($group, $name, 5)->id;
        }

        $page = $this->paginator->paginate(
            SkillModel::query(),
            cursorRequest(['sort_column' => 'id']),
            10,
        );

        expect(pageItemIds($page))->toBe(array_reverse($ids))
            ->and($page->sorting->sortColumn)->toBe('id');
    });

    it('rejects invalid sort specs at construction', function (): void {
        expect(fn () => new SortSpec('', 'desc', 'id'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SortSpec('id', 'sideways', 'id'))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SortSpec('id', 'asc', ''))->toThrow(InvalidArgumentException::class)
            ->and(fn () => new SortSpec('id', 'asc', 'id'))->not->toThrow(InvalidArgumentException::class);
    });

    it('derives the seek operator for directions, inversion and inclusiveness', function (): void {
        $desc = new SortSpec('id', 'desc', 'id');
        $asc = new SortSpec('id', 'asc', 'id');

        expect($desc->seekOperator(false, false))->toBe('<')
            ->and($desc->seekOperator(true, false))->toBe('>')
            ->and($desc->seekOperator(false, true))->toBe('<=')
            ->and($desc->seekOperator(true, true))->toBe('>=')
            ->and($asc->seekOperator(false, false))->toBe('>')
            ->and($asc->seekOperator(true, false))->toBe('<')
            ->and($asc->seekOperator(false, true))->toBe('>=')
            ->and($asc->seekOperator(true, true))->toBe('<=');
    });

    it('compares cursors by id and sort value', function (): void {
        expect((new Cursor(5, '2026-01-01'))->equals(new Cursor(5, '2026-01-01')))->toBeTrue()
            ->and((new Cursor(5, '2026-01-01'))->equals(new Cursor(6, '2026-01-01')))->toBeFalse()
            ->and((new Cursor(5, '2026-01-01'))->equals(new Cursor(5, '2026-01-02')))->toBeFalse()
            ->and((new Cursor(5, null))->equals(new Cursor(5, null)))->toBeTrue();
    });

    it('clamps the requested page and extracts the before marker in PaginationRequest', function (): void {
        expect(PaginationRequest::fromRequest(cursorRequest(['page' => -5]), [], 'id')->page)->toBe(1)
            ->and(PaginationRequest::fromRequest(cursorRequest(['page' => 'abc']), [], 'id')->page)->toBe(1)
            ->and(PaginationRequest::fromRequest(cursorRequest(['page' => 7]), [], 'id')->page)->toBe(7)
            ->and(PaginationRequest::fromRequest(cursorRequest(['cursor' => ['before' => '1']]), [], 'id')->before)->toBeTrue()
            ->and(PaginationRequest::fromRequest(cursorRequest(['cursor' => ['id' => 5]]), [], 'id')->before)->toBeFalse()
            ->and(PaginationRequest::fromRequest(cursorRequest([]), [], 'id')->hasCursorParams())->toBeFalse()
            ->and(PaginationRequest::fromRequest(cursorRequest(['cursor' => ['id' => 5]]), [], 'id')->hasCursorParams())->toBeTrue();
    });

    it('caches the total count per filter combination for 30 seconds, versioned per entity (B175)', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);
        createCursorTestSkill($group, 'PHP', 5);

        $first = $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-count');
        expect($first->total)->toBe(2)
            ->and(Cache::has('admin_cursor_count:skills:test-count:v0'))->toBeTrue();

        createCursorTestSkill($group, 'Vue.js', 5);

        $cached = $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-count');
        expect($cached->total)->toBe(2);

        Cache::forget('admin_cursor_count:skills:test-count:v0');

        $fresh = $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-count');
        expect($fresh->total)->toBe(3);
    });

    it('bumps the per-entity version so writes invalidate the cached total immediately (B175)', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);
        createCursorTestSkill($group, 'PHP', 5);

        $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-invalidate');
        expect($this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-invalidate')->total)->toBe(2);

        createCursorTestSkill($group, 'Vue.js', 5);
        AdminCursorPaginator::invalidateCountCache('skills');

        expect($this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-invalidate')->total)->toBe(3);
    });

    it('refreshes the cached total after the 30-second TTL expires', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);

        $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-ttl');
        expect($this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-ttl')->total)->toBe(1);

        createCursorTestSkill($group, 'PHP', 5);

        Carbon::setTestNow(Carbon::now()->addSeconds(31));

        try {
            $page = $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-ttl');

            expect($page->total)->toBe(2);
        } finally {
            Carbon::setTestNow();
        }
    });

    it('does not cache the total when no cache key is given', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);
        createCursorTestSkill($group, 'PHP', 5);

        $this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10);

        expect(Cache::has('admin_cursor_count:skills:test-count'))->toBeFalse();
    });

    it('keys the count cache per filter combination', function (): void {
        $group = createCursorTestGroup();
        createCursorTestSkill($group, 'Laravel', 5);
        createCursorTestSkill($group, 'PHP', 4);

        $this->paginator->paginate(SkillModel::query()->where('proficiency', 5), cursorRequest([]), 10, 'skills:test-filtered');
        expect($this->paginator->paginate(SkillModel::query()->where('proficiency', 5), cursorRequest([]), 10, 'skills:test-filtered')->total)->toBe(1)
            ->and($this->paginator->paginate(SkillModel::query(), cursorRequest([]), 10, 'skills:test-unfiltered')->total)->toBe(2);
    });
});
