<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Shared\Domain\RepositoryInterfaces\AboutInfoReadRepositoryInterface;
use Illuminate\Support\Facades\DB;

/**
 * The {@see AboutInfoReadRepositoryInterface} over the `about_info`
 * row (id = 1) with its per-locale translations.
 */
final class EloquentAboutInfoReadRepository implements AboutInfoReadRepositoryInterface
{
    public function findSiteName(string $locale): string
    {
        return (string) DB::table('about_info as a')
            ->leftJoin('about_info_translations as at', function ($join) use ($locale): void {
                $join->on('at.about_info_id', '=', 'a.id')
                    ->where('at.locale', '=', $locale);
            })
            ->leftJoin('about_info_translations as en', function ($join): void {
                $join->on('en.about_info_id', '=', 'a.id')
                    ->where('en.locale', '=', 'en');
            })
            ->where('a.id', 1)
            ->value(DB::raw('coalesce(at.name, en.name, \'\')'));
    }
}
