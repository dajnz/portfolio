<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\RepositoryInterfaces\AboutInfoRepositoryInterface;

/**
 * In-memory about repository fake for unit tests (single row, id = 1
 * convention).
 */
final class InMemoryAboutInfoRepository implements AboutInfoRepositoryInterface
{
    private ?AboutInfo $about = null;

    public function __construct(?AboutInfo $about = null)
    {
        $this->about = $about;
    }

    public function find(): ?AboutInfo
    {
        return $this->about;
    }

    public function save(AboutInfo $aboutInfo): void
    {
        $this->about = $aboutInfo;
    }

    public function saved(): ?AboutInfo
    {
        return $this->about;
    }
}
