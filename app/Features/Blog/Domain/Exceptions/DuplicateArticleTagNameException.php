<?php

declare(strict_types=1);

namespace App\Features\Blog\Domain\Exceptions;

use DomainException;

/**
 * The tag name matches an existing tech skill (the article-tag side
 * of the tag↔skill cross-table check, content-management.md:595,695) —
 * surfaced as a `name` validation error.
 */
final class DuplicateArticleTagNameException extends DomainException
{
}
