<?php

declare(strict_types=1);

use App\Features\Portfolio\App\UseCases\SubmitFeedbackUseCase;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Portfolio\Infra\Mail\LaravelFeedbackNotificationMailer;
use App\Features\Portfolio\Infra\Mail\NotifyAdminOfFeedbackMail;
use App\Features\Shared\Infra\Services\LaravelAnalyticsConfig;
use Illuminate\Support\Facades\Mail;
use Tests\Fakes\InMemoryFeedbackRepository;
use Tests\Fakes\InMemorySettingRepository;

beforeEach(function (): void {
    $this->feedback = new InMemoryFeedbackRepository();
    $this->settings = InMemorySettingRepository::withDefaults();
    $this->useCase = new SubmitFeedbackUseCase(
        $this->feedback,
        $this->settings,
        new LaravelAnalyticsConfig(),
        new LaravelFeedbackNotificationMailer(),
    );
});

describe('SubmitFeedbackUseCase', function (): void {
    it('stores the feedback with keyed HMAC identifiers', function (): void {
        config(['analytics.ip_hash_key' => 'test-key']);

        $this->useCase->submit('Jane', 'jane@example.com', 'A message with enough length', '203.0.113.7', 'TestAgent/1.0');

        $stored = $this->feedback->lastStored();

        expect($stored)->not->toBeNull()
            ->and($stored->toSenderName())->toBe('Jane')
            ->and($stored->toSenderEmail())->toBe('jane@example.com')
            ->and($stored->toIsRead())->toBeFalse()
            ->and($stored->toIsForwarded())->toBeFalse()
            ->and($stored->toIpAddress())->toBe(hash_hmac('sha256', '203.0.113.7', 'test-key'))
            ->and($stored->toFingerprint())->toBe(hash_hmac('sha256', '203.0.113.7|TestAgent/1.0', 'test-key'))
            ->and($stored->toIpAddress())->not->toContain('203.0.113.7');
    });

    it('dispatches the queued admin mail when email_on_feedback is on', function (): void {
        Mail::fake();
        $this->settings->saveValue(SettingName::EmailOnFeedback, true);

        $this->useCase->submit('Jane', 'jane@example.com', 'A message with enough length', '203.0.113.7', 'UA');

        Mail::assertQueued(
            NotifyAdminOfFeedbackMail::class,
            fn (NotifyAdminOfFeedbackMail $mail): bool => $mail->senderName === 'Jane'
                && $mail->senderEmail === 'jane@example.com'
                && $mail->message === 'A message with enough length',
        );
    });

    it('skips the mail when email_on_feedback is off', function (): void {
        Mail::fake();
        $this->settings->saveValue(SettingName::EmailOnFeedback, false);

        $this->useCase->submit('Jane', 'jane@example.com', 'A message with enough length', '203.0.113.7', 'UA');

        Mail::assertNothingSent();
    });

    it('stores the row even when the mail is disabled', function (): void {
        $this->settings->saveValue(SettingName::EmailOnFeedback, false);

        $this->useCase->submit('Jane', 'jane@example.com', 'A message with enough length', '203.0.113.7', 'UA');

        expect($this->feedback->lastStored())->not->toBeNull();
    });
});
