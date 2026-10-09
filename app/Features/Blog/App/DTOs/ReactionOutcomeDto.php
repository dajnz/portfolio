<?php

declare(strict_types=1);

namespace App\Features\Blog\App\DTOs;

use App\Features\Blog\Domain\ValueObjects\ReactionType;

/**
 * The result of a reaction toggle (the `ReactionResponse` wire
 * shape): the visitor's current reaction (`null` after an undo) plus
 * the article's totals. Immutable.
 */
final readonly class ReactionOutcomeDto
{
    public function __construct(
        public ?ReactionType $reaction,
        public int $likes,
        public int $dislikes,
    ) {
    }
}
