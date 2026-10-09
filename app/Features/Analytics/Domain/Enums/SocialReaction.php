<?php

declare(strict_types=1);

namespace App\Features\Analytics\Domain\Enums;

/**
 * The like/dislike reaction values carried by `article_reaction`
 * events — distinct from the Blog feature's stored reaction rows (no
 * cross-feature imports).
 */
enum SocialReaction: string
{
    case Like = 'like';

    case Dislike = 'dislike';
}
