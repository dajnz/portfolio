<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Services;

use Illuminate\Support\Facades\DB;

/**
 * The current resume mode (`resume_config.mode`, owned by the Resume
 * feature) for the About page's resume summary line
 * (admin-section-components.md:793-794, about.html `data-dynamic="resume.mode"`).
 * Query-builder read inside the Portfolio Infra layer (B11 — no
 * cross-feature class imports). The column default is `manual`
 * (database.md:111), so an absent row reads as `manual` too.
 */
final class ResumeModeReader
{
    public function read(): string
    {
        $mode = DB::table('resume_config')
            ->where('id', 1)
            ->value('mode');

        return is_string($mode) ? $mode : 'manual';
    }
}
