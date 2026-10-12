<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Repositories;

use App\Features\Shared\Domain\RepositoryInterfaces\ContactReadRepositoryInterface;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The {@see ContactReadRepositoryInterface} over the `contacts` rows
 * with their per-locale translations (`en` fallback).
 */
final class EloquentContactReadRepository implements ContactReadRepositoryInterface
{
    public function findAll(string $locale): array
    {
        return DB::table('contacts as c')
            ->leftJoin('contact_translations as ct', function ($join) use ($locale): void {
                $join->on('ct.contact_id', '=', 'c.id')->where('ct.locale', '=', $locale);
            })
            ->leftJoin('contact_translations as en', function ($join): void {
                $join->on('en.contact_id', '=', 'c.id')->where('en.locale', '=', 'en');
            })
            ->orderBy('c.sort_order')
            ->orderBy('c.id')
            ->select([
                'c.value',
                DB::raw('coalesce(ct.name, en.name) as name'),
            ])
            ->get()
            ->map(fn (stdClass $row): array => [
                'name' => (string) ($row->name ?? ''),
                'value' => (string) $row->value,
            ])
            ->all();
    }
}
