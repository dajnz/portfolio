<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Exceptions;

use DomainException;

/**
 * The skill tag is already attached to the article — surfaced as a
 * 409 by the attach route.
 */
final class ArticleSkillAlreadyAttachedException extends DomainException
{
    public static function forSkill(int $articleId, int $skillId): self
    {
        return new self("Skill [{$skillId}] is already attached to article [{$articleId}].");
    }
}
