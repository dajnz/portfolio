<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;

/**
 * The subscript inline node of the content pipeline (B231) — the
 * `<sub>…</sub>` HTML form the admin editor's subscript button
 * serializes to (CommonMark has no native subscript syntax). The
 * parser claims the full tag pair BEFORE the core HtmlInline parser
 * (whose output `html_input: ESCAPE` would render as literal text);
 * the inner content is added as plain escaped text.
 */
final class Subscript extends AbstractInline
{
}
