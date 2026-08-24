<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Contracts;

/**
 * The restore's cache refresh (backup.md:406): after the data plane
 * lands, every cached read — settings, sitemap, resume PDFs, article
 * HTML — must reflect the restored rows. An abstraction over the
 * framework's cache flush so the app-layer workflows never name it.
 */
interface CacheClearerInterface
{
    public function flush(): void;
}
