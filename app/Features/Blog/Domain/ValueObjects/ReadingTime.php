<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\ValueObjects;

/**
 * Value object: an article's reading time — `ceil(word_count / 200)`
 * minutes, where the word count is taken over the article CONTENT.
 * Computed per locale, stored on the translation row and recomputed on
 * every save.
 *
 * Immutable.
 */
final readonly class ReadingTime
{
    private const WORDS_PER_MINUTE = 200;

    public function __construct(
        private int $minutes,
    ) {
        if ($minutes < 0) {
            throw new \InvalidArgumentException('Reading time must not be negative.');
        }
    }

    public static function fromText(string $text): self
    {
        $text = trim($text);
        $words = $text === '' ? 0 : count(preg_split('/\s+/', $text) ?: []);

        return new self((int) ceil($words / self::WORDS_PER_MINUTE));
    }

    public function toMinutes(): int
    {
        return $this->minutes;
    }

    public function equals(self $other): bool
    {
        return $other->minutes === $this->minutes;
    }
}
