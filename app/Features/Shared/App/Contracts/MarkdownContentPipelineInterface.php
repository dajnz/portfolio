<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

use DateTimeImmutable;

/**
 * The shared content pipeline (B23, content-management.md:466-481):
 * the app-layer contract of the safe-mode CommonMark + callouts +
 * server-side highlighting renderer. The Infra implementation
 * ({@see \App\Features\Shared\Infra\Services\MarkdownContentPipeline})
 * is bound in `AppServiceProvider` — app-layer consumers depend on
 * this interface, never on the concrete service.
 */
interface MarkdownContentPipelineInterface
{
    /**
     * The rendered article body, cached under
     * `article:html:{id}:{locale}:{updated_at}` — the timestamp makes
     * the key rotate on every save of the base row, so stale HTML can
     * never be served.
     */
    public function articleHtml(int $id, string $locale, ?DateTimeImmutable $updatedAt, string $markdown): string;

    /**
     * Renders Markdown to safe HTML (uncached).
     */
    public function render(string $markdown): string;
}
