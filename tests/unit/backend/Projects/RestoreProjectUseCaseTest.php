<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Projects;

use App\Features\Projects\App\UseCases\RestoreProjectUseCase;
use App\Features\Projects\Domain\Exceptions\ProjectSlugOccupiedException;
use Tests\Support\Fakes\FakeCountCacheInvalidator;
use Tests\Support\Fakes\FakeProjectRepository;

/**
 * The restore slug-uniqueness re-check — single and bulk, with the
 * batched reads (one slugs read + one occupied-subset read). The
 * conflict state cannot arise through the HTTP surface: the DB unique
 * index covers trashed rows (soft-deleted entities occupy their slug),
 * so an active project can never hold a trashed project's slug — hence
 * the repository fake.
 */
describe('RestoreProjectUseCase', function (): void {
    it('rejects a single restore when the slug is occupied by an active project', function (): void {
        $repository = new FakeProjectRepository(
            trashedSlugs: [7 => 'taken-slug'],
            activeSlugs: ['taken-slug'],
        );
        $useCase = new RestoreProjectUseCase($repository, new FakeCountCacheInvalidator());

        expect(fn () => $useCase->restore(7))
            ->toThrow(ProjectSlugOccupiedException::class, 'taken-slug')
            ->and($repository->restoredIds)->toBe([]);
    });

    it('restores a single project when the slug is free and invalidates the count cache', function (): void {
        $repository = new FakeProjectRepository(
            trashedSlugs: [7 => 'free-slug'],
        );
        $countCache = new FakeCountCacheInvalidator();
        $useCase = new RestoreProjectUseCase($repository, $countCache);

        $useCase->restore(7);

        expect($repository->restoredIds)->toBe([7])
            ->and($countCache->invalidated)->toBe(['projects']);
    });

    it('rejects a bulk restore when any of the slugs is occupied', function (): void {
        $repository = new FakeProjectRepository(
            trashedSlugs: [7 => 'free-slug', 9 => 'taken-slug'],
            activeSlugs: ['taken-slug'],
        );
        $useCase = new RestoreProjectUseCase($repository, new FakeCountCacheInvalidator());

        expect(fn () => $useCase->restoreMany([7, 9]))
            ->toThrow(ProjectSlugOccupiedException::class, 'taken-slug')
            ->and($repository->restoredManyIds)->toBe([]);
    });

    it('restores the whole batch when every slug is free', function (): void {
        $repository = new FakeProjectRepository(
            trashedSlugs: [7 => 'free-slug', 9 => 'other-slug'],
        );
        $countCache = new FakeCountCacheInvalidator();
        $useCase = new RestoreProjectUseCase($repository, $countCache);

        $useCase->restoreMany([7, 9]);

        expect($repository->restoredManyIds)->toBe([7, 9])
            ->and($countCache->invalidated)->toBe(['projects']);
    });
});
