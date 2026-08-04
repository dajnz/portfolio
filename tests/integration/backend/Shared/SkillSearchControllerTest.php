<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Shared;

use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;

/**
 * @return array{laravel: SkillModel, php: SkillModel}
 */
function createSearchFixture(): array
{
    $group = SkillGroupModel::factory()->create(['sort_order' => 1]);
    SkillGroupTranslationModel::factory()->create(['skill_group_id' => $group->id, 'locale' => 'en', 'name' => 'Backend']);

    return [
        'laravel' => SkillModel::factory()->create([
            'skill_group_id' => $group->id,
            'name' => 'Laravel',
            'icon' => 'simple-icons:laravel',
            'proficiency' => 5,
            'sort_order' => 1,
        ]),
        'php' => SkillModel::factory()->create([
            'skill_group_id' => $group->id,
            'name' => 'PHP',
            'icon' => null,
            'proficiency' => 4,
            'sort_order' => 2,
        ]),
    ];
}

describe('SkillSearchController', function (): void {
    it('returns matching skills in the {data: [{id, name, icon}]} shape', function (): void {
        $fixture = createSearchFixture();

        $response = $this->getJson('/api/skills/search?q=lar');

        $response->assertOk()
            ->assertExactJson([
                'data' => [
                    [
                        'id' => $fixture['laravel']->id,
                        'name' => 'Laravel',
                        'icon' => 'simple-icons:laravel',
                    ],
                ],
            ]);
    });

    it('returns skills with a null icon as null', function (): void {
        $fixture = createSearchFixture();

        $response = $this->getJson('/api/skills/search?q=php');

        $response->assertOk()
            ->assertJsonPath('data.0', [
                'id' => $fixture['php']->id,
                'name' => 'PHP',
                'icon' => null,
            ]);
    });

    it('orders results by name ascending and matches case-insensitively', function (): void {
        $fixture = createSearchFixture();
        SkillModel::factory()->create([
            'skill_group_id' => $fixture['laravel']->skill_group_id,
            'name' => 'Laravel Mix',
            'icon' => null,
            'proficiency' => 3,
            'sort_order' => 3,
        ]);

        $response = $this->getJson('/api/skills/search?q=LAR');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Laravel')
            ->assertJsonPath('data.1.name', 'Laravel Mix');
    });

    it('returns an empty data list for a non-matching query', function (): void {
        createSearchFixture();

        $response = $this->getJson('/api/skills/search?q=python');

        $response->assertOk()
            ->assertExactJson(['data' => []]);
    });

    it('returns an empty data list for a missing query', function (): void {
        createSearchFixture();

        $response = $this->getJson('/api/skills/search');

        $response->assertOk()
            ->assertExactJson(['data' => []]);
    });
});
