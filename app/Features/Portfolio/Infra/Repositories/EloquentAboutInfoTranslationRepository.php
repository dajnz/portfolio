<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Repositories;

use App\Features\Portfolio\Domain\Entities\AboutInfoTranslation;
use App\Features\Portfolio\Infra\Mappers\AboutInfoTranslationMapper;
use App\Features\Portfolio\Infra\Models\AboutInfoTranslationModel;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

/**
 * Persistence of `about_info_translations` rows (database.md:89-99) —
 * read/write side of the `(about_info_id, locale)` composite-PK table.
 */
final class EloquentAboutInfoTranslationRepository
{
    public function __construct(
        private readonly AboutInfoTranslationMapper $mapper,
    ) {
    }

    /**
     * @return AboutInfoTranslation[]
     */
    public function findForAbout(int $aboutInfoId): array
    {
        return AboutInfoTranslationModel::query()
            ->where('about_info_id', $aboutInfoId)
            ->get()
            ->map(fn (AboutInfoTranslationModel $row): AboutInfoTranslation => $this->mapper->toEntity($row))
            ->all();
    }

    /**
     * Upserts the given translations and removes rows whose locales are
     * absent from the set (the entity is assembled from the active
     * `supported_locales` only — content-management.md:419).
     *
     * @param  AboutInfoTranslation[]  $translations
     */
    public function syncForAbout(int $aboutInfoId, array $translations): void
    {
        $rows = [];
        foreach ($translations as $translation) {
            $rows[$translation->toLocale()] = $translation;
        }

        AboutInfoTranslationModel::query()
            ->where('about_info_id', $aboutInfoId)
            ->whereNotIn('locale', array_keys($rows))
            ->delete();

        foreach ($rows as $translation) {
            AboutInfoTranslationModel::query()->updateOrInsert(
                ['about_info_id' => $aboutInfoId, 'locale' => $translation->toLocale()],
                $this->mapper->toRow($translation, $aboutInfoId),
            );
        }
    }

    /**
     * @return EloquentCollection<int, AboutInfoTranslationModel>
     */
    public function findForAbouts(array $aboutInfoIds): EloquentCollection
    {
        return AboutInfoTranslationModel::query()
            ->whereIn('about_info_id', $aboutInfoIds)
            ->get();
    }
}
