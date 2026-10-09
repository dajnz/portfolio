<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Markdown;

use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Inline\DelimitedInterface;

/**
 * The underline inline node of the content pipeline — the `++text++` markdown
 * syntax produced by the admin editor's underline button. Rendered as `<u>`;
 * the inner content is parsed with the regular inline parser (text escaping
 * applies).
 */
final class Underline extends AbstractInline implements DelimitedInterface
{
    private string $delimiter;

    public function __construct(string $delimiter = '++')
    {
        parent::__construct();

        $this->delimiter = $delimiter;
    }

    public function getOpeningDelimiter(): string
    {
        return $this->delimiter;
    }

    public function getClosingDelimiter(): string
    {
        return $this->delimiter;
    }
}
