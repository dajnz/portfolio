<?php

declare(strict_types=1);

namespace App\Features\Shared\App\Services;

use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;

/**
 * Resolves the effective page size of a content list: the admin-set value from
 * the `settings` table, falling back to the committed pagination defaults.
 */
final class PageSizeResolver
{
    public function __construct(
        private readonly SettingRepositoryInterface $settings,
        private readonly PaginationDefaultsInterface $defaults,
    ) {
    }

    public function itemsPerPage(): int
    {
        return (int) $this->settings->value(SettingName::ItemsPerPage, $this->defaults->itemsPerPage());
    }

    public function articlesPerPage(): int
    {
        return (int) $this->settings->value(SettingName::ArticlesPerPage, $this->defaults->articlesPerPage());
    }

    public function projectsPerPage(): int
    {
        return (int) $this->settings->value(SettingName::ProjectsPerPage, $this->defaults->projectsPerPage());
    }

    public function experienceItemsPerPage(): int
    {
        return (int) $this->settings->value(
            SettingName::ExperienceItemsPerPage,
            $this->defaults->experienceItemsPerPage(),
        );
    }
}
