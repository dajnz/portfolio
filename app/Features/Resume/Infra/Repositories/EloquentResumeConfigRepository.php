<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Repositories;

use App\Features\Resume\Domain\Entities\ResumeConfig;
use App\Features\Resume\Domain\RepositoryInterfaces\ResumeConfigRepositoryInterface;
use App\Features\Resume\Infra\Mappers\ResumeConfigMapper;
use App\Features\Resume\Infra\Mappers\ResumeConfigTranslationMapper;
use App\Features\Resume\Infra\Models\ResumeConfigModel;
use App\Features\Resume\Infra\Models\ResumeConfigTranslationModel;
use Illuminate\Support\Facades\DB;

/**
 * Eloquent persistence of the single-row resume config aggregate
 * (database.md:104-128): the id = 1 row with its translations
 * reconciled on save (upsert by the composite PK — about texts may be
 * cleared back to null, so rows are never deleted, only overwritten).
 */
final class EloquentResumeConfigRepository implements ResumeConfigRepositoryInterface
{
    public function __construct(
        private readonly ResumeConfigMapper $mapper,
        private readonly ResumeConfigTranslationMapper $translationMapper,
    ) {
    }

    public function find(): ?ResumeConfig
    {
        $model = ResumeConfigModel::query()->with('translations')->first();

        return $model === null ? null : $this->mapper->toEntity($model);
    }

    public function save(ResumeConfig $config): void
    {
        DB::transaction(function () use ($config): void {
            ResumeConfigModel::query()->updateOrCreate(
                ['id' => $config->toId()],
                $this->mapper->toRow($config),
            );

            $rows = [];

            foreach ($config->translationsByLocale() as $translation) {
                $rows[] = $this->translationMapper->toRow($translation, $config->toId());
            }

            if ($rows !== []) {
                ResumeConfigTranslationModel::query()->upsert(
                    $rows,
                    ['resume_config_id', 'locale'],
                    ['about_text'],
                );
            }
        });
    }
}
