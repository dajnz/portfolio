<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Projects;

use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
});

describe('ProjectPreviewController', function (): void {
    it('rejects requests without a valid signature', function (): void {
        $project = previewProject();

        $this->get("/admin/preview/projects/{$project->id}")
            ->assertForbidden();
    });

    it('renders the public detail page for an unpublished project via a signed URL', function (): void {
        $project = previewProject(['is_published' => false]);

        $url = URL::temporarySignedRoute(
            'projects.preview',
            now()->addHour(),
            ['project' => $project->id],
        );

        $this->get($url)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Projects/Public/Pages/ProjectDetailPage')
                ->where('project.id', $project->id)
                ->where('project.title', 'Payments Gateway'));
    });

    it('returns 404 for unknown projects', function (): void {
        $url = URL::temporarySignedRoute(
            'projects.preview',
            now()->addHour(),
            ['project' => 999],
        );

        $this->get($url)->assertNotFound();
    });
});

/**
 * Creates a translated project row for the preview tests.
 */
function previewProject(array $attributes = []): \App\Features\Projects\Infra\Models\ProjectModel
{
    $project = \App\Features\Projects\Infra\Models\ProjectModel::factory()->create($attributes);

    ProjectTranslationModel::factory()->forLocale('en')->create([
        'project_id' => $project->id,
        'title' => 'Payments Gateway',
        'brief_description' => 'A payment gateway.',
    ]);

    return $project;
}
