<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * The superscript inline node of the content pipeline (ADR-0024) — the
 * `<sup>…</sup>` HTML form the admin editor's superscript button
 * serializes to (see {@see Subscript} for the design rationale).
 */
final class Superscript extends AbstractInline
{
}
