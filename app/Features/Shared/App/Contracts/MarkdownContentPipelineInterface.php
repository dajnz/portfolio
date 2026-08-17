<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

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
     * `article:html:{id}:{locale}:{content-hash}` (B270) — the key
     * embeds a hash of the markdown, so ANY content change rotates
     * the key (self-invalidating by content, including saves landing
     * within the same `updated_at` second — the 8.2 stamp scheme
     * collided there, serving stale HTML: a second save in the same
     * second kept the old key and the cached render of the first
     * save, observed live 2026-08-16). Non-content base-row touches
     * (publish flags, tags) keep the key — the rendered HTML does not
     * depend on them.
     */
    public function articleHtml(int $id, string $locale, string $markdown): string;

    /**
     * Renders Markdown to safe HTML (uncached).
     */
    public function render(string $markdown): string;
}
