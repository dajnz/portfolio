<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Projects;

use App\Features\Projects\Domain\Entities\Project;
use App\Features\Projects\Domain\Entities\ProjectTranslation;
use App\Features\Projects\Domain\ValueObjects\FeaturedState;
use App\Features\Projects\Domain\ValueObjects\ProjectId;
use App\Features\Projects\Domain\ValueObjects\ProjectLinks;
use App\Features\Projects\Domain\ValueObjects\ProjectPictures;
use App\Features\Projects\Domain\ValueObjects\ProjectSlugs;
use App\Features\Shared\Domain\ValueObjects\PublishedState;
use App\Features\Shared\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * A minimal project with the given publication state — the only part
 * the publish/unpublish transitions touch.
 */
function projectWithPublished(PublishedState $published): Project
{
    return new Project(
        id: new ProjectId(1),
        slugs: new ProjectSlugs(new Slug('my-project')),
        pictures: new ProjectPictures(),
        links: new ProjectLinks(),
        techWriteupArticleId: null,
        featured: new FeaturedState(false),
        published: $published,
        translations: [new ProjectTranslation('en', 'Title', 'Brief', null, null, null)],
    );
}

/**
 * The mutable {@see Project} aggregate's publication transitions:
 * `publish()` guards the publication invariants (required translated
 * fields + not-yet-published) before mutating the state, `unpublish()`
 * preserves the first-publish timestamp (database.md:210).
 */
describe('Project publication state', function (): void {
    it('publishes an unpublished project and sets published_at on the first publish', function (): void {
        $project = projectWithPublished(PublishedState::unpublished());

        $project->publish(new DateTimeImmutable('2026-08-12 10:00:00'));

        expect($project->toIsPublished())->toBeTrue()
            ->and($project->toPublishedAt())->toEqual(new DateTimeImmutable('2026-08-12 10:00:00'));
    });

    it('rejects publishing an already-published project', function (): void {
        $published = projectWithPublished(PublishedState::published(new DateTimeImmutable('2026-08-12 10:00:00')));

        expect(fn () => $published->publish(new DateTimeImmutable('2026-08-13 10:00:00')))
            ->toThrow(InvalidArgumentException::class, 'already published')
            ->and($published->toPublishedAt())->toEqual(new DateTimeImmutable('2026-08-12 10:00:00'));
    });

    it('keeps the first-publish timestamp when a published project is republished', function (): void {
        $firstAt = new DateTimeImmutable('2026-08-12 10:00:00');
        $project = projectWithPublished(PublishedState::published($firstAt));

        $project->unpublish();
        $project->publish(new DateTimeImmutable('2026-08-13 10:00:00'));

        expect($project->toPublishedAt())->toEqual($firstAt);
    });

    it('unpublishes while preserving published_at', function (): void {
        $firstAt = new DateTimeImmutable('2026-08-12 10:00:00');
        $project = projectWithPublished(PublishedState::published($firstAt));

        $project->unpublish();

        expect($project->toIsPublished())->toBeFalse()
            ->and($project->toPublishedAt())->toEqual($firstAt);
    });

    it('publishes a project whose translations carry the required fields', function (): void {
        $project = projectWithPublished(PublishedState::unpublished());

        expect(fn () => $project->publish(new DateTimeImmutable('now')))->not->toThrow(InvalidArgumentException::class);
    });
});
