<?php

declare(strict_types=1);

namespace App\Features\Resume\Infra\Repositories;

use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Query-builder implementation of {@see ResumeDataReaderInterface} — the
 * resume source reads (about info, contacts, experience items) inside the
 * Resume Infra layer, locale-resolved with the `en` fallback. Tech skills
 * come from the Shared read repository (architecture.md:224).
 *
 * The `achievements` jsonb is decoded here: the per-locale column already
 * holds the locale's own descriptions (only the entries with a
 * description for that locale are persisted — StoreTimelineItemUseCase),
 * so no per-locale extraction is needed.
 */
final class EloquentResumeDataReader implements ResumeDataReaderInterface
{
    public function sourceStamps(): array
    {
        return [
            'about_info' => DB::table('about_info')->value('updated_at'),
            'skill_groups' => $this->stampsOf('skill_groups'),
            'skills' => $this->stampsOf('skills'),
            'contacts' => $this->stampsOf('contacts'),
            'experience_timeline_items' => $this->stampsOf('experience_timeline_items'),
        ];
    }

    public function headerData(string $locale): array
    {
        $row = DB::table('about_info')->where('id', 1)->first();

        if ($row === null) {
            return ['name' => '', 'title' => ''];
        }

        $translation = $this->aboutTranslation($locale);

        return [
            'name' => $translation->name ?? '',
            'title' => $translation->title ?? '',
        ];
    }

    public function shortPitch(string $locale): string
    {
        $translation = $this->aboutTranslation($locale);

        return $translation->short_pitch ?? '';
    }

    public function experienceData(string $locale): array
    {
        $rows = DB::table('experience_timeline_items as e')
            ->leftJoin('experience_timeline_item_translations as et', function ($join) use ($locale): void {
                $join->on('et.item_id', '=', 'e.id')->where('et.locale', '=', $locale);
            })
            ->leftJoin('experience_timeline_item_translations as en', function ($join): void {
                $join->on('en.item_id', '=', 'e.id')->where('en.locale', '=', 'en');
            })
            ->where('e.include_in_resume', true)
            ->where('e.is_published', true)
            ->whereNull('e.deleted_at')
            ->orderByDesc('e.started_at')
            ->orderByDesc('e.id')
            ->select([
                'e.id',
                'e.started_at',
                'e.ended_at',
                DB::raw('coalesce(et.position, en.position) as position'),
                DB::raw('coalesce(et.company_name, en.company_name) as company_name'),
                DB::raw('coalesce(et.company_country, en.company_country) as company_country'),
                DB::raw('coalesce(et.achievements, en.achievements) as achievements'),
            ])
            ->get();

        $tagNames = $this->techTagNames($rows->pluck('id')->all());

        return $rows
            ->map(function (stdClass $row) use ($tagNames): array {
                return [
                    'position' => (string) ($row->position ?? ''),
                    'company_name' => $row->company_name === null ? null : (string) $row->company_name,
                    'company_country' => $row->company_country === null ? null : (string) $row->company_country,
                    'started_at' => (string) $row->started_at,
                    'ended_at' => $row->ended_at === null ? null : (string) $row->ended_at,
                    'achievements' => $this->decodeAchievements($row->achievements),
                    'tech_tags' => $tagNames[(int) $row->id] ?? [],
                ];
            })
            ->all();
    }

    public function contactsData(string $locale): array
    {
        return DB::table('contacts as c')
            ->leftJoin('contact_translations as ct', function ($join) use ($locale): void {
                $join->on('ct.contact_id', '=', 'c.id')->where('ct.locale', '=', $locale);
            })
            ->leftJoin('contact_translations as cn', function ($join): void {
                $join->on('cn.contact_id', '=', 'c.id')->where('cn.locale', '=', 'en');
            })
            ->where('c.include_in_resume', true)
            ->orderBy('c.sort_order')
            ->orderBy('c.id')
            ->select([
                'c.value',
                'c.icon',
                'c.url',
                DB::raw('coalesce(ct.name, cn.name) as name'),
            ])
            ->get()
            ->map(function (stdClass $row): array {
                return [
                    'name' => (string) ($row->name ?? ''),
                    'value' => (string) $row->value,
                    'icon' => $row->icon === null ? null : (string) $row->icon,
                    'url' => $row->url === null ? null : (string) $row->url,
                ];
            })
            ->all();
    }

    public function inclusionCounts(): array
    {
        return [
            'skills' => DB::table('skills')->where('include_in_resume', true)->count(),
            'experience' => DB::table('experience_timeline_items')
                ->where('include_in_resume', true)
                ->whereNull('deleted_at')
                ->count(),
            'contacts' => DB::table('contacts')->where('include_in_resume', true)->count(),
        ];
    }

    /**
     * @return string[]
     */
    private function stampsOf(string $table): array
    {
        return DB::table($table)
            ->orderBy('id')
            ->pluck('updated_at')
            ->map(fn (mixed $stamp): string => (string) $stamp)
            ->all();
    }

    private function aboutTranslation(string $locale): ?stdClass
    {
        return $this->aboutTranslationRow($locale)
            ?? $this->aboutTranslationRow('en');
    }

    private function aboutTranslationRow(string $locale): ?stdClass
    {
        return DB::table('about_info_translations')
            ->where('about_info_id', 1)
            ->where('locale', $locale)
            ->first();
    }

    /**
     * @param  array<int, string|null>  $achievements  Raw jsonb values.
     * @return array<int, array{description: string, project_link: string|null}>
     */
    private function decodeAchievements(mixed $achievements): array
    {
        if (! is_string($achievements) || $achievements === '') {
            return [];
        }

        $decoded = json_decode($achievements, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  int[]  $itemIds
     * @return array<int, string[]>
     */
    private function techTagNames(array $itemIds): array
    {
        if ($itemIds === []) {
            return [];
        }

        $rows = DB::table('experience_item_tech_tags as eit')
            ->join('skills as s', 's.id', '=', 'eit.skill_id')
            ->whereIn('eit.experience_item_id', $itemIds)
            ->orderBy('eit.experience_item_id')
            ->orderBy('s.sort_order')
            ->orderBy('s.id')
            ->select(['eit.experience_item_id', 's.name'])
            ->get();

        $names = [];

        foreach ($rows as $row) {
            $names[(int) $row->experience_item_id][] = (string) $row->name;
        }

        return $names;
    }
}
