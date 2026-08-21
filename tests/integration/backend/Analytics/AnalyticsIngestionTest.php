<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Analytics;

use App\Features\Analytics\Infra\Models\AnalyticsEventModel;

/**
 * `POST /api/analytics/track` ingestion (analytics.md:129-152,
 * routes.md:184,189,240,364): batch caps + payload size, the bot-UA
 * discard (B24), keyed HMAC IP hashing (B4) and the 60/min/IP rate
 * limit. Real browsers always send a User-Agent, so the test client
 * must pass one explicitly — a missing header is treated as a bot
 * signal and the batch is discarded.
 */
const BROWSER_USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) Chrome/126.0';

function trackBatch(array $events, array $headers = []): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/analytics/track', $events, array_merge(['User-Agent' => BROWSER_USER_AGENT], $headers));
}

it('stores a valid batch with the hashed ip, the user agent and the page context', function (): void {
    trackBatch([
        [
            'event_type' => 'page_view',
            'session_id' => 's-1',
            'page_type' => 'blog_article',
            'page_entity_id' => 5,
            'page_url' => 'https://example.com/blog/hello',
            'metadata' => ['referrer' => null, 'title' => 'Hello'],
        ],
        [
            'event_type' => 'time_on_page',
            'session_id' => 's-1',
            'page_url' => 'https://example.com/blog/hello',
            'metadata' => ['seconds' => 12, 'page_url' => 'https://example.com/blog/hello'],
        ],
    ])->assertNoContent();

    $this->assertDatabaseCount('analytics_events', 2);

    $ipHash = hash_hmac('sha256', '127.0.0.1', (string) config('analytics.ip_hash_key'));

    $pageView = AnalyticsEventModel::query()->where('event_type', 'page_view')->firstOrFail();
    $timeOnPage = AnalyticsEventModel::query()->where('event_type', 'time_on_page')->firstOrFail();

    expect($pageView->session_id)->toBe('s-1')
        ->and($pageView->page_type)->toBe('blog_article')
        ->and($pageView->page_entity_id)->toBe(5)
        ->and($pageView->page_url)->toBe('https://example.com/blog/hello')
        ->and($pageView->metadata)->toEqual(['referrer' => null, 'title' => 'Hello'])
        ->and($pageView->ip_address)->toBe($ipHash)
        ->and($pageView->user_agent)->toBe(BROWSER_USER_AGENT)
        ->and($timeOnPage->metadata)->toEqual(['seconds' => 12, 'page_url' => 'https://example.com/blog/hello'])
        ->and($timeOnPage->ip_address)->toBe($ipHash);
});

it('discards the whole batch for known bot user agents', function (string $userAgent): void {
    trackBatch([
        ['event_type' => 'page_view', 'session_id' => 's-1'],
    ], ['User-Agent' => $userAgent])->assertNoContent();

    $this->assertDatabaseCount('analytics_events', 0);
})->with([
    'Googlebot' => 'Googlebot/2.1 (+http://www.google.com/bot.html)',
    'Bingbot' => 'Mozilla/5.0 (compatible; Bingbot/2.0; +http://www.bing.com/bingbot.htm)',
    'GPTBot' => 'GPTBot/1.0 (+https://openai.com/gptbot)',
]);

it('discards the batch when the user agent header is empty or missing', function (): void {
    // The test client always sends `User-Agent: Symfony` — an explicit
    // empty header reproduces the missing-UA signal (analytics.md:148).
    $this->postJson('/api/analytics/track', [
        ['event_type' => 'page_view', 'session_id' => 's-1'],
    ], ['User-Agent' => ''])->assertNoContent();

    $this->assertDatabaseCount('analytics_events', 0);
});

it('hashes the same ip deterministically and different ips differently', function (): void {
    trackBatch([['event_type' => 'page_view', 'session_id' => 's-1']]);

    $this->withServerVariables(['REMOTE_ADDR' => '192.168.10.10'])
        ->postJson('/api/analytics/track', [
            ['event_type' => 'page_view', 'session_id' => 's-2'],
        ], ['User-Agent' => BROWSER_USER_AGENT])
        ->assertNoContent();

    $first = AnalyticsEventModel::query()->where('session_id', 's-1')->firstOrFail();
    $second = AnalyticsEventModel::query()->where('session_id', 's-2')->firstOrFail();
    $duplicate = AnalyticsEventModel::factory()->create(['session_id' => 's-1-again']);

    expect($first->ip_address)->toBe(hash_hmac('sha256', '127.0.0.1', (string) config('analytics.ip_hash_key')))
        ->and($second->ip_address)->toBe(hash_hmac('sha256', '192.168.10.10', (string) config('analytics.ip_hash_key')))
        ->and($first->ip_address)->not->toBe($second->ip_address)
        ->and($first->ip_address)->not->toBe($duplicate->ip_address);
});

it('rejects batches larger than 100 events', function (): void {
    $events = array_fill(0, 101, ['event_type' => 'page_view', 'session_id' => 's']);

    trackBatch($events)->assertStatus(422)
        ->assertJsonValidationErrors(['batch']);

    $this->assertDatabaseCount('analytics_events', 0);
});

it('rejects an empty batch', function (): void {
    trackBatch([])->assertStatus(422)
        ->assertJsonValidationErrors(['batch']);

    $this->assertDatabaseCount('analytics_events', 0);
});

it('rejects a non-list batch body', function (): void {
    $this->postJson('/api/analytics/track', [
        'events' => [['event_type' => 'page_view', 'session_id' => 's-1']],
    ], ['User-Agent' => BROWSER_USER_AGENT])->assertStatus(422)
        ->assertJsonValidationErrors(['batch']);

    $this->assertDatabaseCount('analytics_events', 0);
});

it('rejects batches with unknown event types', function (): void {
    trackBatch([
        ['event_type' => 'garbage_type', 'session_id' => 's-1'],
        ['event_type' => 'page_view', 'session_id' => 's-2'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['batch']);

    $this->assertDatabaseCount('analytics_events', 0);
});

it('rejects payloads larger than 50 KB', function (): void {
    trackBatch([
        ['event_type' => 'page_view', 'session_id' => 's', 'metadata' => ['blob' => str_repeat('x', 60000)]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['batch']);

    $this->assertDatabaseCount('analytics_events', 0);
});

it('rejects invalid event fields', function (string $eventType, string $sessionId, ?string $pageUrl, ?array $metadata, string $errorKey, array $extra = []): void {
    trackBatch([
        ['event_type' => $eventType, 'session_id' => $sessionId, 'page_url' => $pageUrl, 'metadata' => $metadata] + $extra,
    ])->assertStatus(422)
        ->assertJsonValidationErrors([$errorKey]);

    $this->assertDatabaseCount('analytics_events', 0);
})->with([
    'event type too long' => [str_repeat('x', 101), 's', null, null, '0.event_type'],
    'missing session id' => ['page_view', '', null, null, '0.session_id'],
    'session id too long' => ['page_view', str_repeat('s', 256), null, null, '0.session_id'],
    'page url too long' => ['page_view', 's', str_repeat('u', 501), null, '0.page_url'],
    'page type too long' => ['page_view', 's', null, null, '0.page_type', ['page_type' => str_repeat('p', 51)]],
    'page entity id not positive' => ['page_view', 's', null, null, '0.page_entity_id', ['page_entity_id' => 0]],
    'referrer too long' => ['page_view', 's', null, ['referrer' => str_repeat('r', 2049)], '0.metadata.referrer'],
    'seconds out of range' => ['time_on_page', 's', null, ['seconds' => 86401], '0.metadata.seconds'],
    'percentage out of range' => ['article_read', 's', null, ['percentage_completed' => 101], '0.metadata.percentage_completed'],
]);

it('rate limits ingestion at 60 requests per minute per ip', function (): void {
    foreach (range(1, 60) as $attempt) {
        trackBatch([['event_type' => 'page_view', 'session_id' => 's-'.$attempt]])
            ->assertNoContent();
    }

    trackBatch([['event_type' => 'page_view', 'session_id' => 's-61']])
        ->assertStatus(429);
});
