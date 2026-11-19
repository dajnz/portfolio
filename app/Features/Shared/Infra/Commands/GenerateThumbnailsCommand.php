<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Commands;

use App\Features\Shared\App\UseCases\ScheduleThumbnailGenerationUseCase;
use Illuminate\Console\Command;

/**
 * `app:generate-thumbnails` — the maintenance entry point of the card
 * thumbnails: queues the generation for every stored image without a
 * thumbnail (or for all of them with `--force`). `--sync` runs the
 * generation inline, which is what the demo seeder and the post-deploy
 * backfill use. The command is idempotent — an already generated pair is
 * only rewritten with `--force`.
 */
final class GenerateThumbnailsCommand extends Command
{
    protected $signature = 'app:generate-thumbnails
        {--force : Regenerate the thumbnails of every stored image}
        {--sync : Run the generation inline instead of queueing it}';

    protected $description = 'Generate the card thumbnails of project screenshots and article lead images';

    public function handle(ScheduleThumbnailGenerationUseCase $thumbnails): int
    {
        $synchronously = (bool) $this->option('sync');
        $result = $thumbnails->schedule(
            includeGenerated: (bool) $this->option('force'),
            synchronously: $synchronously,
        );

        $this->info(sprintf(
            '%s %d screenshot thumbnail(s) and %d article thumbnail(s).',
            $synchronously ? 'Generated' : 'Queued',
            $result->screenshots,
            $result->articles,
        ));

        return self::SUCCESS;
    }
}
