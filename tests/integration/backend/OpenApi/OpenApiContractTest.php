<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\OpenApi;

use App\Features\Blog\Infra\Models\ArticleModel;
use App\Features\Blog\Infra\Models\ArticleTranslationModel;
use App\Features\Settings\Infra\Models\FeatureFlagModel;
use App\Features\Shared\Infra\Models\SkillGroupModel;
use App\Features\Shared\Infra\Models\SkillGroupTranslationModel;
use App\Features\Shared\Infra\Models\SkillModel;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use Database\Seeders\SettingsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use LogicException;
use Tests\Support\Traits\InteractsWithAdmin;
use Tests\Support\Traits\OpenApiContractAssertions;

uses(OpenApiContractAssertions::class, InteractsWithAdmin::class);

/*
|--------------------------------------------------------------------------
| OpenAPI contract suite (step 2.7, testing.md:200)
|--------------------------------------------------------------------------
|
| For every operation in docs/openapi/openapi.yaml a real request is sent
| and the response status/body is validated against the operation's
| declared responses via league/openapi-psr7-validator. Operations whose
| endpoints do not exist yet are asserted as 404 + `{message}` envelope
| until their feature step lands (see `self::scenarios()`).
| Inertia pages are excluded — validated via vue-tsc and Spectral separately.
|
*/

beforeEach(function (): void {
    Storage::fake('rustfs');
});

it('covers every operation declared in docs/openapi/openapi.yaml', function (): void {
    $specOperations = $this->document()->operations();
    $operationIds = array_column($specOperations, 'operationId');

    foreach (self::scenarios() as $operationId => $scenario) {
        expect($operationIds)->toContain($operationId)
            ->and($scenario['path'])->toBeIn(
                array_column($specOperations, 'path')
            );
    }

    foreach ($specOperations as $operation) {
        $scenario = self::scenarios()[$operation['operationId']] ?? null;

        expect($scenario)->not->toBeNull("No scenario for spec operation {$operation['operationId']}")
            ->and($scenario['path'])->toBe($operation['path'])
            ->and($scenario['method'])->toBe($operation['method']);
    }
});

it('validates the searchSkills response against the spec', function (): void {
    $group = SkillGroupModel::factory()->create();
    SkillGroupTranslationModel::factory()->create([
        'skill_group_id' => $group->id,
        'locale' => 'en',
        'name' => 'Backend',
    ]);
    SkillModel::factory()->create([
        'skill_group_id' => $group->id,
        'name' => 'Laravel',
        'icon' => 'devicon:laravel',
        'proficiency' => 5,
        'sort_order' => 1,
    ]);

    $this->assertConformsToSpec('searchSkills', 0, $this->getJson('/api/skills/search?q=lar'));
});

it('validates the uploadFile responses against the spec', function (): void {
    $this->assertConformsToSpec('uploadFile', 0, $this->postJson('/api/upload', [
        'file' => UploadedFile::fake()->image('x.png'),
        'entity_type' => 'blog',
    ]));

    $this->loginAsAdmin($this->createAdmin());

    $this->assertConformsToSpec('uploadFile', 1, $this->postJson('/api/upload', [
        'file' => UploadedFile::fake()->image('x.png'),
        'entity_type' => 'blog',
    ]));

    $this->assertConformsToSpec('uploadFile', 2, $this->postJson('/api/upload', [
        'file' => UploadedFile::fake()->image('x.png'),
        'entity_type' => 'avatar',
    ]));
});

it('validates the deleteFile responses against the spec', function (): void {
    $this->assertConformsToSpec('deleteFile', 0, $this->deleteJson('/api/upload', [
        'path' => 'blog/missing.png',
    ]));

    $this->loginAsAdmin($this->createAdmin());

    $this->assertConformsToSpec('deleteFile', 1, $this->deleteJson('/api/upload', [
        'path' => 'blog/missing.png',
    ]));

    Storage::disk('rustfs')->put('blog/exists.png', 'x');

    $this->assertConformsToSpec('deleteFile', 2, $this->deleteJson('/api/upload', [
        'path' => 'blog/exists.png',
    ]));
});

it('validates the submitFeedback responses against the spec', function (): void {
    $this->assertConformsToSpec('submitFeedback', 0, $this->postJson('/api/feedback', [
        'sender_name' => 'Jane Doe',
        'sender_email' => 'jane@example.com',
        'message' => 'This is a valid feedback message.',
    ]));

    $this->assertConformsToSpec('submitFeedback', 1, $this->postJson('/api/feedback', [
        'sender_name' => '',
    ]));

    // The fingerprint limiter (3/h per IP+UA) rejects the 4th request —
    // the two calls above already consumed 2 of the 3 attempts.
    $this->postJson('/api/feedback', [
        'sender_name' => 'Sender 3',
        'sender_email' => 'jane@example.com',
        'message' => 'This is a valid feedback message.',
    ])->assertCreated();

    $this->assertConformsToSpec('submitFeedback', 2, $this->postJson('/api/feedback', [
        'sender_name' => 'Jane Doe',
        'sender_email' => 'jane@example.com',
        'message' => 'This is a valid feedback message.',
    ]));
});

it('validates the listProjectsJson responses against the spec', function (): void {
    $this->seed(SettingsSeeder::class);

    $project = ProjectModel::factory()->published()->create();
    ProjectTranslationModel::factory()->forLocale('en')->create([
        'project_id' => $project->id,
        'title' => 'Payments Gateway',
        'brief_description' => 'A payment processing gateway.',
    ]);

    $this->assertConformsToSpec('listProjectsJson', 0, $this->getJson('/projects'));

    $this->assertConformsToSpec('listProjectsJson', 0, $this->getJson('/projects?q=gateway'));
});

it('validates the createTag responses against the spec', function (): void {
    $this->assertConformsToSpec('createTag', 0, $this->postJson('/api/tags', [
        'slug' => 'new-tag',
        'name' => ['en' => 'New tag'],
    ]));

    $this->loginAsAdmin($this->createAdmin());

    $this->assertConformsToSpec('createTag', 1, $this->postJson('/api/tags', [
        'slug' => 'another-tag',
        'name' => ['en' => 'Another tag'],
    ]));

    $this->assertConformsToSpec('createTag', 2, $this->postJson('/api/tags', [
        'slug' => 'another-tag',
        'name' => [],
    ]));
});

it('answers 404 with the error envelope for not-yet-implemented operations', function (): void {
    foreach (array_keys(array_filter(
        self::scenarios(),
        static fn (array $scenario): bool => ! $scenario['implemented'],
    )) as $operationId) {
        $response = match ($operationId) {
            'trackEvents' => $this->postJson('/api/analytics/track', ['events' => [
                ['event_type' => 'page_view', 'session_id' => 'contract-test-session'],
            ]]),
            default => throw new LogicException("No pending-request builder for [{$operationId}]."),
        };

        $this->assertPendingOperation($response);
    }
});

it('validates the regenerateResume responses against the spec', function (): void {
    $this->seed(SettingsSeeder::class);
    $this->loginAsAdmin($this->createAdmin());

    $this->assertConformsToSpec('regenerateResume', 0, $this->postJson('/api/resume/regenerate'));
});

it('validates the toggleArticleReaction responses against the spec', function (): void {
    $this->seed(SettingsSeeder::class);
    FeatureFlagModel::query()->updateOrCreate(
        ['key' => 'social_reactions'],
        ['enabled' => true, 'description' => ''],
    );

    $article = ArticleModel::factory()->published()->create();
    ArticleTranslationModel::factory()->forLocale('en')->create([
        'article_id' => $article->id,
        'title' => 'Payments Gateway',
        'short_description' => 'A payment gateway article.',
        'content' => 'The article body with enough words.',
    ]);

    $this->assertConformsToSpec('toggleArticleReaction', 0, $this->postJson(
        "/api/blog/{$article->id}/reaction",
        ['reaction' => 'like'],
    ));

    $this->assertConformsToSpec('toggleArticleReaction', 1, $this->postJson(
        "/api/blog/{$article->id}/reaction",
        ['reaction' => 'meh'],
    ));
});

it('validates the searchTags and searchUnifiedBlogTags responses against the spec', function (): void {
    $this->seed(SettingsSeeder::class);

    $this->assertConformsToSpec('searchTags', 0, $this->getJson('/api/tags/search?q=php'));
    $this->assertConformsToSpec('searchUnifiedBlogTags', 0, $this->getJson('/api/blog-tags/search?q=php'));
});

it('validates the listBlogArticlesJson responses against the spec', function (): void {
    $this->seed(SettingsSeeder::class);

    $this->assertConformsToSpec('listBlogArticlesJson', 0, $this->getJson('/blog'));
});
