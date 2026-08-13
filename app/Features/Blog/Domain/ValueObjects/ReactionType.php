<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\ValueObjects;

/**
 * The article reaction type (database.md:406-420): one of `like`,
 * `dislike`, stored as a varchar + CHECK constraint.
 */
enum ReactionType: string
{
    case Like = 'like';

    case Dislike = 'dislike';
}
