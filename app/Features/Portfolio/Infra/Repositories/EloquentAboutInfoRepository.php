<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Repositories;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;
use App\Features\Portfolio\Infra\Mappers\AboutInfoMapper;
use App\Features\Portfolio\Infra\Models\AboutInfoModel;

/**
 * Eloquent-backed single-row about repository. The id = 1 convention is
 * applied at the application level: `find()` reads the first row, `save()`
 * updates it (creating it on first write).
 */
final class EloquentAboutInfoRepository implements AboutInfoRepositoryInterface
{
    public function __construct(
        private readonly AboutInfoMapper $mapper,
        private readonly EloquentAboutInfoTranslationRepository $translations,
    ) {
    }

    public function find(): ?AboutInfo
    {
        $model = AboutInfoModel::query()->orderBy('id')->first();

        return $model === null
            ? null
            : $this->mapper->toEntity($model);
    }

    public function save(AboutInfo $aboutInfo): void
    {
        $model = AboutInfoModel::query()->updateOrCreate(
            ['id' => $aboutInfo->toId()],
            $this->mapper->toRow($aboutInfo),
        );

        $this->translations->syncForAbout($model->id, $aboutInfo->toTranslations());
    }
}
