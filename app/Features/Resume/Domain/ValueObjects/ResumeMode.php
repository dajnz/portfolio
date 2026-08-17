<?php

declare(strict_types=1);

namespace App\Features\Resume\Domain\ValueObjects;

/**
 * The resume generation mode (database.md:111, content-management.md:330):
 * `auto` = generate the PDF from the structured portfolio data, `manual` =
 * serve the uploaded file from `resume_config.custom_resume_file`. Stored
 * as a varchar + CHECK constraint; the enum enforces the two-value domain
 * by construction.
 */
enum ResumeMode: string
{
    case Auto = 'auto';

    case Manual = 'manual';
}
