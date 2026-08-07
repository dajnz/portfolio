<?php

declare(strict_types=1);

use App\Features\Portfolio\Domain\Entities\Feedback;

function makeFeedback(array $overrides = []): Feedback
{
    return new Feedback(
        id: $overrides['id'] ?? 1,
        senderName: $overrides['sender_name'] ?? 'Jane Doe',
        senderEmail: $overrides['sender_email'] ?? 'jane@example.com',
        message: $overrides['message'] ?? 'Great portfolio!',
        ipAddress: $overrides['ip_address'] ?? str_repeat('ab', 32),
        fingerprint: $overrides['fingerprint'] ?? str_repeat('cd', 32),
        isRead: $overrides['is_read'] ?? false,
        isForwarded: $overrides['is_forwarded'] ?? false,
        createdAt: $overrides['created_at'] ?? new DateTimeImmutable('2026-08-01T12:00:00+00:00'),
    );
}

describe('Feedback', function (): void {
    it('exposes its state via toXXX getters', function (): void {
        $createdAt = new DateTimeImmutable('2026-08-01T12:00:00+00:00');
        $feedback = makeFeedback(['created_at' => $createdAt]);

        expect($feedback->toId())->toBe(1)
            ->and($feedback->toSenderName())->toBe('Jane Doe')
            ->and($feedback->toSenderEmail())->toBe('jane@example.com')
            ->and($feedback->toMessage())->toBe('Great portfolio!')
            ->and($feedback->toIpAddress())->toHaveLength(64)
            ->and($feedback->toFingerprint())->toHaveLength(64)
            ->and($feedback->toIsRead())->toBeFalse()
            ->and($feedback->toIsForwarded())->toBeFalse()
            ->and($feedback->toCreatedAt())->toBe($createdAt);
    });

    it('rejects an empty sender name, email or message', function (): void {
        makeFeedback(['sender_name' => '']);
    })->throws(InvalidArgumentException::class);

    it('withRead returns a new instance with the flag set', function (): void {
        $feedback = makeFeedback();
        $read = $feedback->withRead();

        expect($read)->not->toBe($feedback)
            ->and($read->toIsRead())->toBeTrue()
            ->and($feedback->toIsRead())->toBeFalse()
            ->and($read->toId())->toBe($feedback->toId());
    });

    it('withForwarded returns a new instance with the flag set', function (): void {
        $feedback = makeFeedback();
        $forwarded = $feedback->withForwarded();

        expect($forwarded)->not->toBe($feedback)
            ->and($forwarded->toIsForwarded())->toBeTrue()
            ->and($feedback->toIsForwarded())->toBeFalse();
    });

    it('flag mutations preserve the other fields', function (): void {
        $feedback = makeFeedback();
        $mutated = $feedback->withRead()->withForwarded();

        expect($mutated->toSenderName())->toBe($feedback->toSenderName())
            ->and($mutated->toSenderEmail())->toBe($feedback->toSenderEmail())
            ->and($mutated->toMessage())->toBe($feedback->toMessage())
            ->and($mutated->toIpAddress())->toBe($feedback->toIpAddress())
            ->and($mutated->toFingerprint())->toBe($feedback->toFingerprint())
            ->and($mutated->toCreatedAt())->toEqual($feedback->toCreatedAt());
    });
});
