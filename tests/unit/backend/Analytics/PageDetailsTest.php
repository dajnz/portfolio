<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Analytics;

use App\Features\Analytics\Domain\Enums\PageType;
use App\Features\Analytics\Domain\ValueObjects\PageDetails;
use InvalidArgumentException;

/**
 * The page context VO (B289): the page type, entity id and URL, with
 * the typed entity-id accessors used by the reports.
 */
describe('PageDetails', function (): void {
    it('exposes the page context', function (): void {
        $details = new PageDetails(PageType::BlogArticle, 5, 'https://example.com/blog/hello');

        expect($details->toPageType())->toBe(PageType::BlogArticle)
            ->and($details->toPageEntityId())->toBe(5)
            ->and($details->toPageUrl())->toBe('https://example.com/blog/hello');
    });

    it('returns the blog article id only on article pages', function (): void {
        expect((new PageDetails(PageType::BlogArticle, 5, null))->toBlogArticleId())->toBe(5)
            ->and((new PageDetails(PageType::Landing, 5, null))->toBlogArticleId())->toBeNull()
            ->and((new PageDetails(PageType::BlogArticle, null, null))->toBlogArticleId())->toBeNull();
    });

    it('returns the project id only on project detail pages', function (): void {
        expect((new PageDetails(PageType::ProjectDetail, 3, null))->toProjectId())->toBe(3)
            ->and((new PageDetails(PageType::BlogArticle, 3, null))->toProjectId())->toBeNull();
    });

    it('accepts a missing page type, entity id and url', function (): void {
        $details = new PageDetails(null, null, null);

        expect($details->toPageType())->toBeNull()
            ->and($details->toPageEntityId())->toBeNull()
            ->and($details->toPageUrl())->toBeNull();
    });

    it('rejects a non-positive entity id', function (): void {
        expect(fn () => new PageDetails(PageType::BlogArticle, 0, null))
            ->toThrow(InvalidArgumentException::class, 'must be a positive integer');
    });

    it('rejects a page url longer than 500 characters', function (): void {
        expect(fn () => new PageDetails(null, null, str_repeat('u', 501)))
            ->toThrow(InvalidArgumentException::class, 'must not exceed 500 characters');
    });
});
