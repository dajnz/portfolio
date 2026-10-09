<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\App\DTOs\AnalyticsDigestDto;
use App\Features\Analytics\App\DTOs\AnalyticsTopArticleDto;
use App\Features\Analytics\Infra\Mail\SendAnalyticsReportMail;
use DateTimeImmutable;

/**
 * The queued analytics digest mail (analytics.md:214-219, ADR-0017,
 * 11.3.5): the previous day's key metrics and top article rendered as
 * a simple HTML table — the subject carries the day, the body the
 * metric labels + values and the article title (the render needs the
 * booted app: the translator + the Blade view).
 */
function digestMail(): SendAnalyticsReportMail
{
    return new SendAnalyticsReportMail(new AnalyticsDigestDto(
        day: new DateTimeImmutable('2026-07-09'),
        pageViews: 3.0,
        uniqueVisitors: 2.0,
        feedbackSubmissions: 5.0,
        topArticle: new AnalyticsTopArticleDto(
            id: 5,
            title: 'Payments Gateway',
            views: 2.0,
        ),
    ));
}

it('uses the day as the subject period', function (): void {
    $mail = digestMail();

    expect($mail->envelope()->subject)->toBe('Analytics report — Jul 9, 2026');
});

it('renders the day, the key metrics and the top article', function (): void {
    $html = digestMail()->render();

    expect($html)->toContain('Jul 9, 2026')
        ->and($html)->toContain('Total page views')
        ->and($html)->toContain('Unique visitors')
        ->and($html)->toContain('Feedback submissions')
        ->and($html)->toContain('Top article')
        ->and($html)->toContain('Payments Gateway');
});

it('renders the no-data notice when there is no top article', function (): void {
    $mail = new SendAnalyticsReportMail(new AnalyticsDigestDto(
        day: new DateTimeImmutable('2026-07-09'),
        pageViews: 0.0,
        uniqueVisitors: 0.0,
        feedbackSubmissions: 0.0,
        topArticle: null,
    ));

    expect($mail->render())->toContain('No data available.');
});
