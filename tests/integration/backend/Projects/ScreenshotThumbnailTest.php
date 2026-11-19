<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Projects;

use App\Features\Projects\Infra\Jobs\GenerateScreenshotThumbnailJob;
use App\Features\Projects\Infra\Models\ProjectModel;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Projects\Infra\Models\ProjectTranslationModel;
use App\Features\Shared\Domain\ValueObjects\ThumbnailPath;
use Database\Seeders\SettingsSeeder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ImageFixtures;
use Tests\Support\Traits\InteractsWithAdmin;

uses(InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(SettingsSeeder::class);
    Storage::fake('rustfs');
});

function thumbnailTestProject(array $attributes = []): ProjectModel
{
    $project = ProjectModel::factory()->published()->create($attributes);

    foreach ([
        'en' => ['title' => 'Payments Gateway', 'brief_description' => 'A payment gateway.'],
        'ru' => ['title' => 'Платёжный шлюз', 'brief_description' => 'Платёжный шлюз.'],
    ] as $locale => $fields) {
        ProjectTranslationModel::factory()
            ->forLocale($locale)
            ->create(['project_id' => $project->id] + $fields);
    }

    return $project;
}

function thumbnailTestScreenshot(
    ProjectModel $project,
    string $path,
    int $sortOrder = 0,
    ?string $thumbnailPath = null,
): ProjectScreenshotModel {
    return ProjectScreenshotModel::factory()->create([
        'project_id' => $project->id,
        'image_path' => $path,
        'thumbnail_path' => $thumbnailPath,
        'sort_order' => $sortOrder,
    ]);
}

function thumbnailStoredSize(string $path): array
{
    $image = imagecreatefromstring((string) Storage::disk('rustfs')->get($path));

    return [imagesx($image), imagesy($image)];
}

/**
 * @return array<string, mixed>
 */
function thumbnailTestProjectPayload(array $overrides = []): array
{
    return array_merge([
        'slug' => 'payments-gateway',
        'title' => ['en' => 'Payments Gateway', 'ru' => 'Платёжный шлюз'],
        'brief_description' => ['en' => 'A payment processing gateway.', 'ru' => 'Платёжный шлюз для обработки платежей.'],
        'full_description' => ['en' => '**Full** markdown.', 'ru' => '**Полное** описание.'],
        'problem' => ['en' => 'Slow payouts.', 'ru' => 'Медленные выплаты.'],
        'solution' => ['en' => 'Queued processing.', 'ru' => 'Обработка через очередь.'],
        'github_url' => null,
        'live_demo_url' => null,
        'is_featured' => false,
        'featured_sort_order' => null,
        'is_published' => false,
        'tech_tags' => [],
        'screenshots' => [],
        'detail_items' => [],
        'tech_writeup_article_id' => null,
    ], $overrides);
}

describe('screenshot thumbnails', function (): void {
    it('generates both variants and records the 1x path', function (): void {
        $path = 'projects/thumb-source.webp';
        Storage::disk('rustfs')->put($path, ImageFixtures::png(1200, 800));
        $screenshot = thumbnailTestScreenshot(thumbnailTestProject(), $path);

        $this->app->call([new GenerateScreenshotThumbnailJob($screenshot->id), 'handle']);

        $standard = ThumbnailPath::forSource($path)->toStandard();
        $retina = ThumbnailPath::forSource($path)->toRetina();

        expect(Storage::disk('rustfs')->exists($standard))->toBeTrue()
            ->and(Storage::disk('rustfs')->exists($retina))->toBeTrue()
            ->and($screenshot->fresh()->thumbnail_path)->toBe($standard)
            ->and(thumbnailStoredSize($standard))->toBe([480, 270])
            ->and(thumbnailStoredSize($retina))->toBe([960, 540]);
    });

    it('skips a missing source object', function (): void {
        $screenshot = thumbnailTestScreenshot(thumbnailTestProject(), 'projects/missing.webp');

        $this->app->call([new GenerateScreenshotThumbnailJob($screenshot->id), 'handle']);

        expect($screenshot->fresh()->thumbnail_path)->toBeNull()
            ->and(Storage::disk('rustfs')->allFiles())->toBe([]);
    });

    it('skips a screenshot deleted before the job ran', function (): void {
        $this->app->call([new GenerateScreenshotThumbnailJob(999999), 'handle']);

        expect(Storage::disk('rustfs')->allFiles())->toBe([]);
    });

    it('exposes the generated pair in the public index props', function (): void {
        $path = 'projects/thumb-props.webp';
        thumbnailTestScreenshot(
            thumbnailTestProject(),
            $path,
            thumbnailPath: ThumbnailPath::forSource($path)->toStandard(),
        );

        $this->get('/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.screenshots.0.thumbnail.path', ThumbnailPath::forSource($path)->toStandard())
                ->where('projects.0.screenshots.0.thumbnail.retina_path', ThumbnailPath::forSource($path)->toRetina()));
    });

    it('leaves the thumbnail null in the props until it is generated', function (): void {
        thumbnailTestScreenshot(thumbnailTestProject(), 'projects/pending.webp');

        $this->get('/projects')
            ->assertInertia(fn (Assert $page) => $page
                ->where('projects.0.screenshots.0.thumbnail', null));
    });

    it('schedules one job per submitted screenshot on store', function (): void {
        Queue::fake();
        $this->loginAsAdmin($this->createAdmin());

        $this->post('/admin/projects', thumbnailTestProjectPayload([
            'screenshots' => [
                ['image' => 'projects/one.webp', 'title' => [], 'description' => []],
                ['image' => 'projects/two.webp', 'title' => [], 'description' => []],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        Queue::assertPushed(GenerateScreenshotThumbnailJob::class, 2);
    });

    it('does not schedule a reorder-only update', function (): void {
        $project = thumbnailTestProject();
        $first = thumbnailTestScreenshot($project, 'projects/one.webp', 0, ThumbnailPath::forSource('projects/one.webp')->toStandard());
        $second = thumbnailTestScreenshot($project, 'projects/two.webp', 1, ThumbnailPath::forSource('projects/two.webp')->toStandard());

        Queue::fake();
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/projects/'.$project->id, thumbnailTestProjectPayload([
            'slug' => $project->slug,
            'screenshots' => [
                ['id' => $second->id, 'image' => 'projects/two.webp', 'title' => [], 'description' => []],
                ['id' => $first->id, 'image' => 'projects/one.webp', 'title' => [], 'description' => []],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        Queue::assertNothingPushed();
        expect($first->fresh()->thumbnail_path)->toBe(ThumbnailPath::forSource('projects/one.webp')->toStandard());
    });

    it('clears the stale thumbnail and schedules the job when an image is replaced', function (): void {
        $project = thumbnailTestProject();
        $screenshot = thumbnailTestScreenshot(
            $project,
            'projects/old.webp',
            0,
            ThumbnailPath::forSource('projects/old.webp')->toStandard(),
        );

        Queue::fake();
        $this->loginAsAdmin($this->createAdmin());

        $this->put('/admin/projects/'.$project->id, thumbnailTestProjectPayload([
            'slug' => $project->slug,
            'screenshots' => [
                ['id' => $screenshot->id, 'image' => 'projects/new.webp', 'title' => [], 'description' => []],
            ],
        ]))->assertRedirect()->assertSessionHasNoErrors();

        expect($screenshot->fresh()->image_path)->toBe('projects/new.webp')
            ->and($screenshot->fresh()->thumbnail_path)->toBeNull();

        Queue::assertPushed(GenerateScreenshotThumbnailJob::class, 1);
    });
});
