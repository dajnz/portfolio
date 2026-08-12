<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Mappers;

use App\Features\Projects\Domain\Entities\ProjectScreenshot;
use App\Features\Projects\Infra\Models\ProjectScreenshotModel;
use App\Features\Shared\Domain\ValueObjects\SortOrder;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;

/**
 * Converts between `project_screenshots` rows and
 * {@see ProjectScreenshot} entities. The model's jsonb arrays are
 * decoded by its `array` cast at the boundary.
 */
final class ProjectScreenshotMapper
{
    public function toEntity(ProjectScreenshotModel $model): ProjectScreenshot
    {
        return new ProjectScreenshot(
            id: (int) $model->id,
            picture: new StoredPicture(
                url: $model->image_path,
                altLabels: $this->map($model->alt_text),
                descriptions: $this->map($model->description),
            ),
            titles: $this->map($model->title),
            sortOrder: new SortOrder((int) $model->sort_order),
        );
    }

    /**
     * @return array{
     *     project_id: int,
     *     image_path: string,
     *     alt_text: array<string, string>|null,
     *     title: array<string, string>|null,
     *     description: array<string, string>|null,
     *     sort_order: int,
     * }
     */
    public function toRow(int $projectId, ProjectScreenshot $screenshot): array
    {
        $picture = $screenshot->toPicture();

        return [
            'project_id' => $projectId,
            'image_path' => $picture->url,
            'alt_text' => $this->nullWhenEmpty($picture->altLabelsMap()),
            'title' => $this->nullWhenEmpty($screenshot->titlesByLocale()),
            'description' => $this->nullWhenEmpty($picture->descriptionsMap()),
            'sort_order' => $screenshot->toSortOrder()->toValue(),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function map(mixed $value): array
    {
        $map = [];

        foreach ((array) $value as $locale => $text) {
            if (is_string($text) && $text !== '') {
                $map[(string) $locale] = $text;
            }
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $map
     * @return array<string, string>|null  Null when empty — the DB jsonb columns are nullable.
     */
    private function nullWhenEmpty(array $map): ?array
    {
        return $map === [] ? null : $map;
    }
}
