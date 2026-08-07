<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\UseCases\GetAboutForEditUseCase;
use App\Features\Portfolio\App\UseCases\UpdateAboutUseCase;
use App\Features\Portfolio\Domain\Entities\AboutInfo;
use App\Features\Portfolio\Domain\ValueObjects\AboutInfoTranslation;
use App\Features\Portfolio\Domain\ValueObjects\AvailabilityStatus;
use App\Features\Portfolio\Domain\ValueObjects\WorkType;
use App\Features\Portfolio\Infra\Requests\UpdateAboutRequest;
use App\Features\Shared\Domain\ValueObjects\StoredPicture;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET/PUT /admin/about` (routes.md:65-66) — the about edit form
 * (admin-section-ui.md:230-239). The page props carry per-locale maps
 * for the translatable fields (name/title/short_pitch/location) so the
 * LocaleTabs can render every supported locale (mockup `data-dynamic`
 * keys, e.g. `about.name.en`).
 */
final class AboutController
{
    public function __construct(
        private readonly GetAboutForEditUseCase $getAboutCase,
        private readonly UpdateAboutUseCase $updateAboutCase,
    ) {
    }

    public function edit(ResponseFactory $inertia): Response
    {
        $about = $this->getAboutCase->get();

        return $inertia->render('Portfolio/Admin/Pages/AboutEditPage', [
            'about' => $about === null ? null : $this->props($about),
        ]);
    }

    public function update(UpdateAboutRequest $request): RedirectResponse
    {
        $this->updateAboutCase->update(
            id: 1,
            photo: $this->photoFromRequest($request),
            showLocation: (bool) $request->boolean('show_location'),
            timezone: $request->input('timezone'),
            relocationReadiness: (bool) $request->boolean('relocation_readiness'),
            availabilityStatus: AvailabilityStatus::from((string) $request->input('availability_status')),
            workType: WorkType::from((string) $request->input('work_type')),
            translations: $this->translations($request),
        );

        return redirect()->back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(AboutInfo $about): array
    {
        $translations = $about->translationsByLocale();

        $perLocale = fn (string $field, ?string $default = null): array => $this->perLocaleValues($translations, $field, $default);

        return [
            'name' => $perLocale('name'),
            'title' => $perLocale('title'),
            'short_pitch' => $perLocale('short_pitch'),
            'location' => $perLocale('location'),
            'photo' => $about->toPhoto()?->url,
            'photo_alt' => $about->toPhoto()?->altLabelsMap() ?? [],
            'show_location' => $about->toShowLocation(),
            'timezone' => $about->toTimezone(),
            'availability_status' => $about->toAvailabilityStatus()?->value,
            'work_type' => $about->toWorkType()->value,
            'relocation_readiness' => $about->toRelocationReadiness(),
        ];
    }

    /**
     * @param  array<string, AboutInfoTranslation>  $translations
     * @return array<string, string|null>
     */
    private function perLocaleValues(array $translations, string $field, ?string $default = null): array
    {
        $values = [];
        foreach ($translations as $locale => $translation) {
            $values[$locale] = match ($field) {
                'name' => $translation->toName(),
                'title' => $translation->toTitle(),
                'short_pitch' => $translation->toShortPitch(),
                'location' => $translation->toLocation() ?? $default,
                default => $default,
            };
        }

        return $values;
    }

    private function photoFromRequest(UpdateAboutRequest $request): ?StoredPicture
    {
        $url = $request->input('photo');

        if (! is_string($url) || $url === '') {
            return null;
        }

        $altLabels = [];
        foreach ((array) $request->input('photo_alt', []) as $locale => $alt) {
            if (is_string($alt) && $alt !== '') {
                $altLabels[$locale] = $alt;
            }
        }

        return new StoredPicture(url: $url, altLabels: $altLabels);
    }

    /**
     * @return array<string, array{name: string, title: string, short_pitch: string, location: string|null}>
     */
    private function translations(UpdateAboutRequest $request): array
    {
        $translations = [];
        foreach (array_keys($request->input('name', [])) as $locale) {
            $translations[$locale] = [
                'name' => (string) $request->input("name.$locale", ''),
                'title' => (string) $request->input("title.$locale", ''),
                'short_pitch' => (string) $request->input("short_pitch.$locale", ''),
                'location' => $request->input("location.$locale"),
            ];
        }

        return $translations;
    }
}
