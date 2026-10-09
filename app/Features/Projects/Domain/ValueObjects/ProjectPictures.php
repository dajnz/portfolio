<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\ValueObjects;

use App\Features\Projects\Domain\Entities\ProjectScreenshot;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use InvalidArgumentException;

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
