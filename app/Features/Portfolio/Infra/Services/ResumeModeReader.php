<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Services;

use App\Features\Portfolio\App\Contracts\ResumeModeReaderInterface;
use Illuminate\Support\Facades\DB;

/**
 * The current resume mode (`resume_config.mode`, owned by the Resume
 * feature) for the About page's resume summary line. Query-builder read
 * inside the Portfolio Infra layer (no cross-feature class imports). The
 * column default is `manual`, so an absent row reads as `manual` too.
 */
final class ResumeModeReader implements ResumeModeReaderInterface
{
    public function read(): string
    {
        $mode = DB::table('resume_config')
            ->where('id', 1)
            ->value('mode');

        return is_string($mode) ? $mode : 'manual';
    }
}
