<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\TechSkills;

use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

describe('TechSkillController', function (): void {
    it('requires authentication for the list', function (): void {
        $this->get('/admin/skills')->assertRedirect('/admin/login');
    });

    it('lists groups with their skills ordered by sort_order and per-locale names', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $backend = SkillGroupModel::factory()->create(['sort_order' => 0, 'icon' => 'heroicons:server']);
        SkillGroupTranslationModel::factory()->create(['skill_group_id' => $backend->id, 'locale' => 'en', 'name' => 'Backend']);
        SkillGroupTranslationModel::factory()->create(['skill_group_id' => $backend->id, 'locale' => 'ru', 'name' => 'Бэкенд']);
        $frontend = SkillGroupModel::factory()->create(['sort_order' => 1]);
        SkillGroupTranslationModel::factory()->create(['skill_group_id' => $frontend->id, 'locale' => 'en', 'name' => 'Frontend']);
        SkillGroupTranslationModel::factory()->create(['skill_group_id' => $frontend->id, 'locale' => 'ru', 'name' => 'Фронтенд']);

        $php = SkillModel::factory()->create(['skill_group_id' => $backend->id, 'name' => 'PHP', 'sort_order' => 0, 'include_in_resume' => false]);
        SkillModel::factory()->create(['skill_group_id' => $backend->id, 'name' => 'Laravel', 'sort_order' => 1]);
        SkillModel::factory()->create(['skill_group_id' => $frontend->id, 'name' => 'Vue.js', 'sort_order' => 0]);

        $this->get('/admin/skills')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TechSkills/Admin/Pages/SkillsListPage')
                ->has('skillGroups', 2)
                ->where('skillGroups.0.id', $backend->id)
                ->where('skillGroups.0.name', 'Backend')
                ->where('skillGroups.0.names.en', 'Backend')
                ->where('skillGroups.0.names.ru', 'Бэкенд')
                ->where('skillGroups.0.icon', 'heroicons:server')
                ->where('skillGroups.0.sort_order', 0)
                ->has('skillGroups.0.skills', 2)
                ->where('skillGroups.0.skills.0.name', 'PHP')
                ->where('skillGroups.0.skills.0.proficiency', $php->proficiency)
                ->where('skillGroups.0.skills.0.include_in_resume', false)
                ->where('skillGroups.0.skills.1.name', 'Laravel')
                ->where('skillGroups.1.name', 'Frontend')
                ->has('skillGroups.1.skills', 1)
                ->where('skillGroups.1.skills.0.name', 'Vue.js'));
    });

    it('stores a skill with all non-translatable fields', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create();

        $this->post('/admin/skills', [
            'name' => 'PostgreSQL',
            'icon' => 'devicon:postgresql',
            'proficiency' => 5,
            'skill_group_id' => $group->id,
            'include_in_resume' => true,
            'sort_order' => 3,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = SkillModel::query()->first();
        expect($row)->not->toBeNull()
            ->and($row->name)->toBe('PostgreSQL')
            ->and($row->icon)->toBe('devicon:postgresql')
            ->and($row->proficiency)->toBe(5)
            ->and($row->skill_group_id)->toBe($group->id)
            ->and($row->include_in_resume)->toBeTrue()
            ->and($row->sort_order)->toBe(3);
    });

    it('validates the skill fields', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/skills', [
            'name' => '',
            'proficiency' => 6,
            'skill_group_id' => 999,
        ])->assertRedirect()
            ->assertSessionHasErrors(['name', 'proficiency', 'skill_group_id']);

        $this->post('/admin/skills', [
            'name' => 'PHP',
            'proficiency' => 0,
            'skill_group_id' => 999,
        ])->assertRedirect()
            ->assertSessionHasErrors(['proficiency', 'skill_group_id']);
    });

    it('rejects a skill name matching another skill (case-insensitive)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create();
        SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'Laravel']);

        $this->post('/admin/skills', [
            'name' => 'laravel',
            'proficiency' => 4,
            'skill_group_id' => $group->id,
        ])->assertRedirect()
            ->assertSessionHasErrors('name');

        expect(SkillModel::query()->count())->toBe(1);
    });

    it('does not check skill names against article tag names', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create();

        // The tag↔skill cross-check belongs to the article-tag side (8.1):
        // a skill name may legitimately repeat an article tag name.
        $tagId = DB::table('article_tags')->insertGetId(['slug' => 'tips']);
        DB::table('article_tag_translations')->insert([
            ['article_tag_id' => $tagId, 'locale' => 'en', 'name' => 'Laravel Tips'],
        ]);

        $this->post('/admin/skills', [
            'name' => 'Laravel Tips',
            'proficiency' => 3,
            'skill_group_id' => $group->id,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(SkillModel::query()->where('name', 'Laravel Tips')->exists())->toBeTrue();
    });

    it('updates a skill and keeps its own name on uniqueness re-check', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create();
        $skill = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'PHP', 'sort_order' => 0]);

        // Unchanged name passes the uniqueness check (the row itself is ignored).
        $this->put("/admin/skills/{$skill->id}", [
            'name' => 'PHP',
            'icon' => null,
            'proficiency' => 2,
            'skill_group_id' => $group->id,
            'include_in_resume' => false,
            'sort_order' => 1,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = $skill->fresh();
        expect($row->name)->toBe('PHP')
            ->and($row->proficiency)->toBe(2)
            ->and($row->include_in_resume)->toBeFalse()
            ->and($row->sort_order)->toBe(1);
    });

    it('rejects an update whose name collides with another skill', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create();
        $skill = SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'PHP']);
        SkillModel::factory()->create(['skill_group_id' => $group->id, 'name' => 'Laravel']);

        $this->put("/admin/skills/{$skill->id}", [
            'name' => 'Laravel',
            'proficiency' => 4,
            'skill_group_id' => $group->id,
        ])->assertRedirect()
            ->assertSessionHasErrors('name');
    });

    it('deletes a skill', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create();
        $skill = SkillModel::factory()->create(['skill_group_id' => $group->id]);

        $this->delete("/admin/skills/{$skill->id}")->assertRedirect();

        expect(SkillModel::query()->count())->toBe(0);
    });

    it('reorders skills per the ADR-0005 payload and persists cross-group moves', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $g1 = SkillGroupModel::factory()->create();
        $g2 = SkillGroupModel::factory()->create();
        $a = SkillModel::factory()->create(['skill_group_id' => $g1->id, 'sort_order' => 0]);
        $b = SkillModel::factory()->create(['skill_group_id' => $g1->id, 'sort_order' => 1]);
        $c = SkillModel::factory()->create(['skill_group_id' => $g2->id, 'sort_order' => 0]);

        $this->post('/admin/skills/reorder', [
            'skills' => [
                ['id' => $c->id, 'skill_group_id' => $g1->id],
                ['id' => $a->id, 'skill_group_id' => $g1->id],
                ['id' => $b->id, 'skill_group_id' => $g2->id],
            ],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(SkillModel::query()->find($c->id)->skill_group_id)->toBe($g1->id)
            ->and(SkillModel::query()->find($c->id)->sort_order)->toBe(0)
            ->and(SkillModel::query()->find($a->id)->skill_group_id)->toBe($g1->id)
            ->and(SkillModel::query()->find($a->id)->sort_order)->toBe(1)
            ->and(SkillModel::query()->find($b->id)->skill_group_id)->toBe($g2->id)
            ->and(SkillModel::query()->find($b->id)->sort_order)->toBe(2);
    });

    it('bumps updated_at on reorder — the resume PDF content hash rotates', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create();
        $a = SkillModel::factory()->create(['skill_group_id' => $group->id, 'sort_order' => 0]);
        $b = SkillModel::factory()->create(['skill_group_id' => $group->id, 'sort_order' => 1]);

        $stale = now()->subDay();
        SkillModel::query()->whereKey($a->id)->update(['updated_at' => $stale]);
        SkillModel::query()->whereKey($b->id)->update(['updated_at' => $stale]);

        $this->post('/admin/skills/reorder', [
            'skills' => [
                ['id' => $b->id, 'skill_group_id' => $group->id],
                ['id' => $a->id, 'skill_group_id' => $group->id],
            ],
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        // The batched write fires no model events — the explicit stamp
        // bump is what rotates the resume content hash (step 10.1).
        expect(SkillModel::query()->find($a->id)->updated_at->isAfter($stale))->toBeTrue()
            ->and(SkillModel::query()->find($b->id)->updated_at->isAfter($stale))->toBeTrue();
    });

    it('rejects a malformed skill reorder payload', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/skills/reorder', [])->assertRedirect()
            ->assertSessionHasErrors('skills');

        $this->post('/admin/skills/reorder', [
            'skills' => [['id' => 'x', 'skill_group_id' => 999]],
        ])->assertRedirect()
            ->assertSessionHasErrors(['skills.0.id', 'skills.0.skill_group_id']);
    });
});
