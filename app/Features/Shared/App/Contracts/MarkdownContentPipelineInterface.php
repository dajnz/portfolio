<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The app-layer contract of the safe-mode CommonMark + callouts +
 * server-side highlighting renderer. The Infra implementation
 * ({@see \App\Features\Shared\Infra\Services\MarkdownContentPipeline})
 * is bound in `AppServiceProvider` — app-layer consumers depend on
 * this interface, never on the concrete service.
 */
interface MarkdownContentPipelineInterface
{
    /**
     * The rendered article body, cached under
     * `article:html:{id}:{locale}:{content-hash}`. The key embeds a hash of
     * the markdown, so any content change rotates the key
     * (self-invalidating by content). Non-content base-row touches (publish
     * flags, tags) keep the key — the rendered HTML does not depend on them.
     */
    public function articleHtml(int $id, string $locale, string $markdown): string;

    /**
     * Renders Markdown to safe HTML (uncached).
     */
    public function render(string $markdown): string;
}
