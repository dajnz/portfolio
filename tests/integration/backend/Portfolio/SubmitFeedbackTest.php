<?php

declare(strict_types=1);

namespace Tests\Integration\Backend\Portfolio;

use App\Features\Portfolio\Infra\Models\FeedbackModel;
use Illuminate\Support\Facades\Mail;

uses(\Tests\Support\Traits\InteractsWithAdmin::class);

beforeEach(function (): void {
    $this->seed(\Database\Seeders\SettingsSeeder::class);
});

function validFeedbackPayload(array $overrides = []): array
{
    return array_merge([
        'sender_name' => 'Jane Doe',
        'sender_email' => 'jane@example.com',
        'message' => 'A message with enough length for validation.',
    ], $overrides);
}

describe('POST /api/feedback', function (): void {
    it('stores a valid submission and returns 201 with no body', function (): void {
        config(['analytics.ip_hash_key' => 'test-key']);

        $this->withHeader('User-Agent', 'TestAgent/1.0')
            ->postJson('/api/feedback', validFeedbackPayload())
            ->assertCreated()
            ->assertContent('');

        $row = FeedbackModel::query()->first();
        expect($row)->not->toBeNull()
            ->and($row->sender_name)->toBe('Jane Doe')
            ->and($row->sender_email)->toBe('jane@example.com')
            ->and($row->message)->toBe('A message with enough length for validation.')
            ->and($row->is_read)->toBeFalse()
            ->and($row->is_forwarded)->toBeFalse()
            ->and($row->ip_address)->toBe(hash_hmac('sha256', '127.0.0.1', 'test-key'))
            ->and($row->ip_address)->not->toContain('127.0.0.1')
            ->and($row->fingerprint)->toBe(hash_hmac('sha256', '127.0.0.1|TestAgent/1.0', 'test-key'))
            ->and(strlen($row->fingerprint))->toBe(64);
    });

    it('dispatches the queued admin mail on a valid submission', function (): void {
        Mail::fake();

        $this->postJson('/api/feedback', validFeedbackPayload())->assertCreated();

        Mail::assertQueued(
            \App\Features\Portfolio\Infra\Mail\NotifyAdminOfFeedbackMail::class,
            fn ($mail): bool => $mail->senderName === 'Jane Doe' && $mail->excerpt() === 'A message with enough length for validation.',
        );
    });

    it('skips the admin mail when email_on_feedback is off', function (): void {
        Mail::fake();
        \App\Features\Settings\Infra\Models\SettingModel::query()
            ->where('key', 'email_on_feedback')
            ->update(['value' => '0']);

        $this->postJson('/api/feedback', validFeedbackPayload())->assertCreated();

        Mail::assertNothingQueued();
        expect(FeedbackModel::query()->count())->toBe(1);
    });

    it('rejects invalid payloads with 422', function (): void {
        $this->postJson('/api/feedback', [])->assertUnprocessable()
            ->assertJsonValidationErrors(['sender_name', 'sender_email', 'message']);

        $this->postJson('/api/feedback', validFeedbackPayload(['sender_email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('sender_email');

        $this->postJson('/api/feedback', validFeedbackPayload(['message' => 'short']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('message');
    });

    it('rejects a filled honeypot silently (200, nothing stored)', function (): void {
        $response = $this->get('/');
        $honeypot = \App\Features\Portfolio\Infra\Requests\SubmitFeedbackRequest::honeypotFieldName();

        expect($honeypot)->not->toBeNull();

        $this->postJson('/api/feedback', validFeedbackPayload([$honeypot => 'spam']))
            ->assertOk()
            ->assertContent('');

        expect(FeedbackModel::query()->count())->toBe(0);
    });

    it('keeps the honeypot field out of the stored row when empty', function (): void {
        $this->get('/');
        $honeypot = \App\Features\Portfolio\Infra\Requests\SubmitFeedbackRequest::honeypotFieldName();

        $this->postJson('/api/feedback', validFeedbackPayload([$honeypot => '']))
            ->assertCreated();

        expect(FeedbackModel::query()->count())->toBe(1);
    });

    it('rate-limits at 5 requests per minute per IP (layer 1)', function (): void {
        // The fingerprint layer (3/h per IP+UA key) is checked second, so
        // layer 1 is exercised with a distinct User-Agent per request —
        // the per-IP limiter still counts them all against the same key.
        foreach (range(1, 5) as $i) {
            $this->withHeader('User-Agent', "TestAgent/$i")
                ->postJson('/api/feedback', validFeedbackPayload(['sender_name' => "S$i"]))
                ->assertCreated();
        }

        $this->withHeader('User-Agent', 'TestAgent/6')
            ->postJson('/api/feedback', validFeedbackPayload())
            ->assertStatus(429)
            ->assertJson(['message' => 'Too many attempts.']);
    });

    it('rate-limits at 3 requests per hour per IP+UA fingerprint (layer 2)', function (): void {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/feedback', validFeedbackPayload(['sender_name' => "S$i"]))
                ->assertCreated();
        }

        $this->postJson('/api/feedback', validFeedbackPayload())
            ->assertStatus(429)
            ->assertJson(['message' => 'Too many requests.'])
            ->assertHeader('Retry-After');

        expect(FeedbackModel::query()->count())->toBe(3);
    });

    it('keeps the two rate limiters independent (a fresh UA resets layer 2, not layer 1)', function (): void {
        foreach (range(1, 3) as $i) {
            $this->postJson('/api/feedback', validFeedbackPayload(['sender_name' => "S$i"]))
                ->assertCreated();
        }

        // Layer 2 exhausted for the current UA → 429.
        $this->postJson('/api/feedback', validFeedbackPayload())
            ->assertStatus(429);

        // A fresh UA gets a fresh fingerprint key → the request passes
        // (the per-IP limiter is at 3/5, so it still allows it).
        $this->withHeader('User-Agent', 'FreshAgent')
            ->postJson('/api/feedback', validFeedbackPayload())
            ->assertCreated();
    });
});
