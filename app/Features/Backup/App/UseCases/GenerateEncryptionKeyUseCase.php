<?php

declare(strict_types=1);

namespace App\Features\Backup\App\UseCases;

use App\Features\Backup\Domain\Entities\EncryptionKey;
use App\Features\Backup\Domain\RepositoryInterfaces\EncryptionKeyRepositoryInterface;
use DateTimeImmutable;

use function base64_encode;
use function random_bytes;

/**
 * Key generation + rotation: creates a fresh 44-character
 * base64-of-32-bytes key (the AES-256 key format) and makes it active —
 * the previous active key is deactivated but kept (old backups still
 * reference it). The exactly-one-active invariant is enforced here. The
 * raw value is returned to the caller, which displays it exactly once.
 */
final class GenerateEncryptionKeyUseCase
{
    public function __construct(
        private readonly EncryptionKeyRepositoryInterface $keys,
    ) {
    }

    public function generate(string $name): EncryptionKey
    {
        $now = new DateTimeImmutable();

        $key = $this->keys->create(new EncryptionKey(
            id: 0,
            name: $name,
            keyValue: base64_encode(random_bytes(32)),
            isActive: true,
            createdAt: $now,
            updatedAt: $now,
        ));

        $previous = $this->keys->findActive();

        if ($previous !== null && $previous->toId() !== $key->toId()) {
            $previous->deactivate();
            $this->keys->save($previous);
        }

        return $key;
    }
}
