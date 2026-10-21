<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Blog;

use App\Features\Blog\App\UseCases\IncrementArticleViewUseCase;
use App\Features\Shared\App\Contracts\AnalyticsConfigInterface;
use Tests\Support\Fakes\FakeArticleViewRepository;

function incrementAnalyticsConfig(): AnalyticsConfigInterface
{
    return new class () implements AnalyticsConfigInterface {
        public function ipHashKey(): string
        {
            return 'test-key';
        }

        public function botUserAgentPattern(): string
        {
            return '/bot|crawler/i';
        }
    };
}

describe('IncrementArticleViewUseCase', function (): void {
    it('increments the counter for a human visitor', function (): void {
        $views = new FakeArticleViewRepository();

        (new IncrementArticleViewUseCase($views, incrementAnalyticsConfig()))
            ->increment(7, 'Mozilla/5.0 (X11; Linux x86_64) Chrome/126.0');

        expect($views->incremented)->toBe([7]);
    });

    it('skips crawler user agents', function (): void {
        $views = new FakeArticleViewRepository();

        (new IncrementArticleViewUseCase($views, incrementAnalyticsConfig()))
            ->increment(7, 'Googlebot/2.1');

        expect($views->incremented)->toBe([]);
    });

    it('skips missing and empty user agents', function (): void {
        $views = new FakeArticleViewRepository();
        $useCase = new IncrementArticleViewUseCase($views, incrementAnalyticsConfig());

        $useCase->increment(7, null);
        $useCase->increment(7, '');

        expect($views->incremented)->toBe([]);
    });
});
