<?php

declare(strict_types=1);

namespace App\Features\Auth\Domain\Entities;

use App\Features\Shared\Domain\BaseEntity;
use InvalidArgumentException;

/**
 * The single administrator account — aggregate root holding the identity
 * fields, the GitHub link and the embedded TOTP posture (`AdminTimedOtp`).
 *
 * The bcrypt password hash and the encrypted-at-rest fields (TOTP secret,
 * GitHub token) are decrypted by the mappers before construction. All
 * mutations return a new instance; persistence happens through
 * `AdminRepositoryInterface::save()`.
 */
final class Admin extends BaseEntity
{
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly string $email,
        private readonly string $login,
        private readonly string $passwordHash,
        private readonly ?string $githubId,
        private readonly ?string $githubToken,
        private readonly int $sessionVersion,
        private readonly AdminTimedOtp $timedOtp,
    ) {
        $this->validate();
    }

    public function toId(): int
    {
        return $this->id;
    }

    public function toName(): string
    {
        return $this->name;
    }

    public function toEmail(): string
    {
        return $this->email;
    }

    public function toLogin(): string
    {
        return $this->login;
    }

    public function toPasswordHash(): string
    {
        return $this->passwordHash;
    }

    /** Raw GitHub ID — consumed by the repository sync (`save()`). */
    public function toGithubId(): ?string
    {
        return $this->githubId;
    }

    /** Plaintext (decrypted) GitHub token — consumed by the repository sync. */
    public function toGithubToken(): ?string
    {
        return $this->githubToken;
    }

    public function toSessionVersion(): int
    {
        return $this->sessionVersion;
    }

    public function toTimedOtp(): AdminTimedOtp
    {
        return $this->timedOtp;
    }

    public function hasGithubId(string $githubId): bool
    {
        return $this->githubId === $githubId;
    }

    public function isGithubLinked(): bool
    {
        return $this->githubId !== null;
    }

    public function sessionVersionMatches(int $version): bool
    {
        return $this->sessionVersion === $version;
    }

    /**
     * Whether GitHub OAuth is the primary login method: a GitHub ID is
     * linked and no password hash is stored. Always false under the
     * current schema (`admins.password` is NOT NULL) — derived anyway for
     * forward compatibility.
     */
    public function isOauthPrimary(): bool
    {
        return $this->githubId !== null && $this->passwordHash === '';
    }

    public function updateCredentials(string $name, string $email, string $login): self
    {
        return new self($this->id, $name, $email, $login, $this->passwordHash, $this->githubId, $this->githubToken, $this->sessionVersion, $this->timedOtp);
    }

    public function withGithubLink(string $githubId, string $githubToken): self
    {
        return new self($this->id, $this->name, $this->email, $this->login, $this->passwordHash, $githubId, $githubToken, $this->sessionVersion, $this->timedOtp);
    }

    public function withoutGithubLink(): self
    {
        return new self($this->id, $this->name, $this->email, $this->login, $this->passwordHash, null, null, $this->sessionVersion, $this->timedOtp);
    }

    public function withSessionVersion(int $sessionVersion): self
    {
        return new self($this->id, $this->name, $this->email, $this->login, $this->passwordHash, $this->githubId, $this->githubToken, $sessionVersion, $this->timedOtp);
    }

    public function withTimedOtp(AdminTimedOtp $timedOtp): self
    {
        return new self($this->id, $this->name, $this->email, $this->login, $this->passwordHash, $this->githubId, $this->githubToken, $this->sessionVersion, $timedOtp);
    }

    private function validate(): void
    {
        if ($this->id < 1) {
            throw new InvalidArgumentException('Admin id must be a positive integer.');
        }

        if ($this->name === '' || $this->email === '' || $this->login === '') {
            throw new InvalidArgumentException('Admin name, email and login must not be empty.');
        }

        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Admin email is not a valid email address.');
        }

        if ($this->passwordHash === '') {
            throw new InvalidArgumentException('Admin password hash must not be empty.');
        }

        if ($this->sessionVersion < 0) {
            throw new InvalidArgumentException('Admin session version must not be negative.');
        }
    }
}
