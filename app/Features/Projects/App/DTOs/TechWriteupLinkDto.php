<?php

declare(strict_types=1);

namespace App\Features\Projects\App\DTOs;

/**
 * The linked tech-writeup article — resolved against the CURRENT
 * publication state: present only while the article is published.
 */
final readonly class TechWriteupLinkDto
{
    public function __construct(
        public int $id,
        public string $slug,
        public string $title,
    ) {
    }
}
