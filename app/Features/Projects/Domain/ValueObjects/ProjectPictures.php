<?php

declare(strict_types=1);

namespace App\Features\Projects\Domain\ValueObjects;

use App\Features\Projects\Domain\Entities\ProjectScreenshot;
use InvalidArgumentException;

final readonly class ProjectPictures
{
    /**
     * @param  ProjectScreenshot[]  $screenshots
     */
    public function __construct(
        private array $screenshots = [],
    ) {
        foreach ($screenshots as $screenshot) {
            if (! $screenshot instanceof ProjectScreenshot) {
                throw new InvalidArgumentException('Project screenshots must be ProjectScreenshot instances.');
            }
        }
    }

    /**
     * @return ProjectScreenshot[]
     */
    public function toScreenshots(): array
    {
        return $this->screenshots;
    }
}
