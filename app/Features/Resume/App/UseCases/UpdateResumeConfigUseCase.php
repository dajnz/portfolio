<?php

declare(strict_types=1);

namespace App\Features\Resume\App\UseCases;

use App\Features\Resume\Domain\Entities\ResumeConfig;
use App\Features\Resume\Domain\RepositoryInterfaces\ResumeConfigRepositoryInterface;
use App\Features\Shared\App\Contracts\TransactionManagerInterface;
use DateTimeImmutable;

/**
 * Persists the resume configuration (`PUT /admin/resume` — the single
 * endpoint for mode switches, layout changes and the explicit About Me
 * save): upserts the id = 1 row and reconciles the per-locale about
 * texts. The mode arrives as the request-validated string (`auto`|`manual`)
 * — the entity's constructor and mutation methods enforce the domain
 * invariant.
 */
final class UpdateResumeConfigUseCase
{
    public function __construct(
        private readonly ResumeConfigRepositoryInterface $repository,
        private readonly TransactionManagerInterface $transactionManager,
    ) {
    }

    /**
     * @param  array<string, mixed>  $layout  The validated layout JSON.
     * @param  array<string, string|null>  $aboutTexts  Locale => about text (null clears).
     */
    public function update(
        string $mode,
        array $layout,
        ?string $customResumeFile,
        array $aboutTexts,
    ): void {
        $this->transactionManager->transaction(function () use ($mode, $layout, $customResumeFile, $aboutTexts): void {
            $existing = $this->repository->find();

            $config = $existing ?? new ResumeConfig(
                id: ResumeConfig::ID,
                mode: $mode,
                layout: $layout,
                customResumeFile: $customResumeFile,
                translations: [],
                updatedAt: new DateTimeImmutable(),
            );

            $config->changeMode($mode);
            $config->updateLayout($layout);
            $config->changeCustomResumeFile($customResumeFile);

            foreach ($aboutTexts as $locale => $aboutText) {
                $config->updateAboutText(
                    $locale,
                    is_string($aboutText) && $aboutText !== '' ? $aboutText : null,
                );
            }

            $this->repository->save($config);
        });
    }
}
