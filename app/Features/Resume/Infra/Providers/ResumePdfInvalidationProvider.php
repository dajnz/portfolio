<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Providers;

use App\Features\Resume\Infra\Services\ResumePdfCacheInvalidator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Resume PDF cache invalidation (content-management.md:400): any save,
 * delete or restore of a resume source row clears every `resume:pdf:*`
 * Redis key, so the next `/cv` request regenerates. The listeners are
 * table-based (the shared data contract — the same tables the resume's
 * query-builder reads touch) rather than per-model-class: the source
 * models belong to other features and must not import Resume classes
 * (B11). Eloquent dispatches string events (`eloquent.saved: Class`)
 * unless a model declares `dispatchesEvents`, hence the wildcard
 * patterns. Batched query-builder writes (reorders) fire no model events
 * — their `updated_at` bumps rotate the content hash instead (see the
 * TechSkills/Portfolio reorder implementations).
 */
final class ResumePdfInvalidationProvider extends ServiceProvider
{
    private const SOURCE_TABLES = [
        'about_info',
        'skill_groups',
        'skills',
        'contacts',
        'experience_timeline_items',
        'resume_config',
    ];

    public function boot(): void
    {
        $invalidator = $this->app->make(ResumePdfCacheInvalidator::class);

        foreach (['saved', 'deleted', 'restored'] as $event) {
            Event::listen(
                "eloquent.{$event}: *",
                // Wildcard listeners receive the raw `(event, payload)`.
                static function (string $event, array $payload) use ($invalidator): void {
                    $model = $payload[0] ?? null;

                    if ($model instanceof Model && in_array($model->getTable(), self::SOURCE_TABLES, true)) {
                        $invalidator->clear();
                    }
                },
            );
        }
    }
}
