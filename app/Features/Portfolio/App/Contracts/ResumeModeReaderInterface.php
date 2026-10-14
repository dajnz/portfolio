<?php

declare(strict_types=1);

namespace App\Features\Portfolio\App\Contracts;

/**
 * Reads the current resume mode (owned by the Resume feature) for the About
 * edit form's resume summary line. The cross-feature read lives on the infra
 * layer so the app layer never queries another feature's table directly.
 */
interface ResumeModeReaderInterface
{
    public function read(): string;
}
