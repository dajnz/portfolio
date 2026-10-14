<?php

declare(strict_types=1);

namespace App\Features\Resume\App\UseCases;

use App\Features\Resume\App\Contracts\ResumePdfRendererInterface;
use App\Features\Resume\App\DTOs\ResumePdfDto;
use App\Features\Resume\App\Services\ResumePdfStore;
use App\Features\Resume\App\Services\ResumeSkillsAssembler;
use App\Features\Resume\Domain\Entities\ResumeConfig;
use App\Features\Resume\Domain\RepositoryInterfaces\ResumeConfigRepositoryInterface;
use App\Features\Resume\App\Contracts\ResumeDataReaderInterface;

/**
 * The `/cv` on-demand pipeline. In
 * `manual` mode the uploaded file path is returned directly; in `auto`
 * mode the flow is:
 *
 * 1. content hash = SHA-256 of `json_encode()` of all source data
 *    `updated_at` stamps + `resume_config.updated_at` + the layout JSON;
 * 2. Redis MISS on `resume:pdf:{locale}:{hash}` → check RustFS for the
 *    existing PDF at `about/resume_{locale}_{hash}.pdf` (covers
 *    cache-cleared-but-file-still-exists scenarios like backup restore)
 *    → if found, serve + cache the path;
 * 3. otherwise assemble the source data (visible sections only),
 *    render the Blade template via dompdf, store the PDF in RustFS and
 *    cache the path.
 *
 * Returns null when no config row exists or the manual file is missing —
 * the controller maps that to a 404.
 */
final class GetResumePdfUseCase
{
    public function __construct(
        private readonly ResumeConfigRepositoryInterface $configs,
        private readonly ResumeDataReaderInterface $reader,
        private readonly ResumePdfRendererInterface $renderer,
        private readonly ResumePdfStore $store,
        private readonly ResumeSkillsAssembler $skills,
    ) {
    }

    public function get(string $locale): ?ResumePdfDto
    {
        $config = $this->configs->find();

        if ($config === null) {
            return null;
        }

        if ($config->isManual()) {
            $path = $config->toCustomResumeFile();

            return $path === null
                ? null
                : new ResumePdfDto($path, 'resume.pdf', 'application/pdf');
        }

        return $this->autoModePdf($config, $locale);
    }

    private function autoModePdf(ResumeConfig $config, string $locale): ResumePdfDto
    {
        $hash = $this->contentHash($config);
        $path = "about/resume_{$locale}_{$hash}.pdf";
        $cacheKey = "resume:pdf:{$locale}:{$hash}";

        $existing = $this->store->find($cacheKey, $path);

        if ($existing !== null) {
            return new ResumePdfDto($existing, 'resume.pdf', 'application/pdf');
        }

        $pdf = $this->renderer->render(
            $config->toTemplate(),
            $locale,
            $this->assemblyData($config, $locale),
        );

        $this->store->write($cacheKey, $path, $pdf);

        return new ResumePdfDto($path, 'resume.pdf', 'application/pdf');
    }

    private function contentHash(ResumeConfig $config): string
    {
        return hash('sha256', json_encode([
            'stamps' => $this->reader->sourceStamps(),
            'config_updated_at' => $config->toUpdatedAt()->format('Y-m-d H:i:s'),
            'layout' => $config->toLayout(),
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * Assembles the per-locale PDF data. Hidden sections (layout
     * `visible: false`) are excluded from the assembly — they are neither
     * loaded nor rendered.
     *
     * @return array<string, mixed>
     */
    private function assemblyData(ResumeConfig $config, string $locale): array
    {
        $sections = $config->toLayout()['sections'];

        $data = [
            'locale' => $locale,
            'sections' => $sections,
            'header' => ['name' => '', 'title' => ''],
            'about' => '',
            'skills' => [],
            'experience' => [],
            'contacts' => [],
        ];

        if ($sections['header']['visible']) {
            $data['header'] = $this->reader->headerData($locale);
        }

        if ($sections['about_me']['visible']) {
            $data['about'] = $config->aboutTextFor($locale) ?? $this->reader->shortPitch($locale);
        }

        if ($sections['skills']['visible']) {
            $data['skills'] = $this->skills->forLocale($locale);
        }

        if ($sections['experience']['visible']) {
            $data['experience'] = $this->reader->experienceData($locale);
        }

        if ($sections['contacts']['visible']) {
            $data['contacts'] = $this->reader->contactsData($locale);
        }

        return $data;
    }
}
