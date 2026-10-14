<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\PaginationDefaultsInterface;
use Illuminate\Support\Facades\Config;

/**
 * The committed `config('portfolio.*_per_page')` defaults behind the app-layer
 * {@see PaginationDefaultsInterface} contract.
 */
final class LaravelPaginationDefaults implements PaginationDefaultsInterface
{
    public function itemsPerPage(): int
    {
        return (int) Config::get('portfolio.items_per_page', 12);
    }

    public function articlesPerPage(): int
    {
        return (int) Config::get('portfolio.articles_per_page', 10);
    }

    public function projectsPerPage(): int
    {
        return (int) Config::get('portfolio.projects_per_page', 12);
    }

    public function experienceItemsPerPage(): int
    {
        return (int) Config::get('portfolio.experience_items_per_page', 15);
    }
}
