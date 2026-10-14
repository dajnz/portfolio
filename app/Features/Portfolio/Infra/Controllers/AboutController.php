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
use App\Features\Shared\Infra\Services\PerLocaleValues;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET/PUT /admin/about` — the about edit form. The page props carry
 * per-locale maps for the translatable fields (name/title/short_pitch/
 * location) so the LocaleTabs can render every supported locale.
 */
final class AboutController
{
    public function __construct(
        private readonly GetAboutForEditUseCase $getAboutCase,
        private readonly UpdateAboutUseCase $updateAboutCase,
        private readonly PerLocaleValues $localeValues,
    ) {
    }

    public function edit(ResponseFactory $inertia): Response
    {
        $data = $this->getAboutCase->get();

        return $inertia->render('Portfolio/Admin/Pages/AboutEditPage', [
            'about' => $data['about'] === null ? null : $this->props($data['about']),
            // The timezone picker's option set — the same IANA list the
            // `timezone` validation rule accepts (UpdateAboutRequest),
            // already sorted alphabetically by PHP.
            'timezones' => DateTimeZone::listIdentifiers(),
            'resume_mode' => $data['resume_mode'],
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

        return Redirect::back()
            ->with('notification', ['type' => 'success', 'message' => __('admin.toasts.saved')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function props(AboutInfo $about): array
    {
        $translations = $about->translationsByLocale();
        $map = fn (callable $extractor): array => $this->localeValues->mapPerLocale($translations, $extractor);

        return [
            'name' => $map(fn (AboutInfoTranslation $translation): string => $translation->toName()),
            'title' => $map(fn (AboutInfoTranslation $translation): string => $translation->toTitle()),
            'short_pitch' => $map(fn (AboutInfoTranslation $translation): string => $translation->toShortPitch()),
            'location' => $map(fn (AboutInfoTranslation $translation): ?string => $translation->toLocation()),
            'photo' => $about->toPhoto()?->url,
            'photo_alt' => $about->toPhoto()?->altLabelsMap() ?? [],
            'show_location' => $about->toShowLocation(),
            'timezone' => $about->toTimezone(),
            'availability_status' => $about->toAvailabilityStatus()?->value,
            'work_type' => $about->toWorkType()->value,
            'relocation_readiness' => $about->toRelocationReadiness(),
        ];
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
