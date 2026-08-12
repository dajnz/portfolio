<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\ValueObjects;

use App\Features\Projects\Domain\Entities\ProjectScreenshot;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use InvalidArgumentException;

/**
 * Value object: the project's picture set — the optional preview
 * picture ({@see StoredPicture}) and the screenshot gallery
 * ({@see ProjectScreenshot} rows in their display order). Immutable;
 * the screenshot membership is validated here, the per-row rules live
 * in {@see ProjectScreenshot}.
 */
final readonly class ProjectPictures
{
    /**
     * @param  ProjectScreenshot[]  $screenshots
     */
    public function __construct(
        private ?StoredPicture $previewPicture = null,
        private array $screenshots = [],
    ) {
        foreach ($screenshots as $screenshot) {
            if (! $screenshot instanceof ProjectScreenshot) {
                throw new InvalidArgumentException('Project screenshots must be ProjectScreenshot instances.');
            }
        }
    }

    public function toPreviewPicture(): ?StoredPicture
    {
        return $this->previewPicture;
    }

    /**
     * @return ProjectScreenshot[]
     */
    public function toScreenshots(): array
    {
        return $this->screenshots;
    }
}
