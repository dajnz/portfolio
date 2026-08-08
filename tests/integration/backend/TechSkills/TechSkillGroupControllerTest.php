<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\TechSkills;

use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

describe('TechSkillGroupController', function (): void {
    it('stores a group with per-locale names', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/skill-groups', [
            'name' => ['en' => 'Backend', 'ru' => 'Бэкенд'],
            'icon' => 'heroicons:server',
            'sort_order' => 2,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = SkillGroupModel::query()->first();
        expect($row)->not->toBeNull()
            ->and($row->icon)->toBe('heroicons:server')
            ->and($row->sort_order)->toBe(2)
            ->and(SkillGroupTranslationModel::query()->where('locale', 'en')->first()->name)->toBe('Backend')
            ->and(SkillGroupTranslationModel::query()->where('locale', 'ru')->first()->name)->toBe('Бэкенд');
    });

    it('validates the per-locale group names', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/skill-groups', [
            'name' => ['en' => 'Backend'],
        ])->assertRedirect()
            ->assertSessionHasErrors(['name.ru']);
    });

    it('updates a group and reconciles its translations', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create(['icon' => 'heroicons:server']);
        SkillGroupTranslationModel::factory()->create(['skill_group_id' => $group->id, 'locale' => 'en', 'name' => 'Old']);

        $this->put("/admin/skill-groups/{$group->id}", [
            'name' => ['en' => 'New', 'ru' => 'Новый'],
            'icon' => null,
        ])->assertRedirect()
            ->assertSessionHasNoErrors();

        $row = $group->fresh();
        expect($row->icon)->toBeNull()
            ->and(SkillGroupTranslationModel::query()->where('skill_group_id', $group->id)->pluck('name')->all())
            ->toBe(['New', 'Новый']);
    });

    it('deletes a group with its translations and skills', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $group = SkillGroupModel::factory()->create();
        SkillGroupTranslationModel::factory()->create(['skill_group_id' => $group->id, 'locale' => 'en', 'name' => 'X']);
        SkillModel::factory()->create(['skill_group_id' => $group->id]);

        $this->delete("/admin/skill-groups/{$group->id}")->assertRedirect();

        expect(SkillGroupModel::query()->count())->toBe(0)
            ->and(SkillGroupTranslationModel::query()->count())->toBe(0)
            ->and(SkillModel::query()->count())->toBe(0);
    });

    it('reorders groups by the zero-indexed payload positions (A4)', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $a = SkillGroupModel::factory()->create(['sort_order' => 0]);
        $b = SkillGroupModel::factory()->create(['sort_order' => 1]);
        $c = SkillGroupModel::factory()->create(['sort_order' => 2]);

        $this->post('/admin/skill-groups/reorder', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect(SkillGroupModel::query()->find($c->id)->sort_order)->toBe(0)
            ->and(SkillGroupModel::query()->find($a->id)->sort_order)->toBe(1)
            ->and(SkillGroupModel::query()->find($b->id)->sort_order)->toBe(2);
    });

    it('rejects a malformed group reorder payload', function (): void {
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/skill-groups/reorder', ['ids' => ['x']])->assertRedirect()
            ->assertSessionHasErrors('ids.0');

        $this->post('/admin/skill-groups/reorder', [])->assertRedirect()
            ->assertSessionHasErrors('ids');
    });
});
