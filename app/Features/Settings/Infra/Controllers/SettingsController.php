<?php

declare(strict_types=1);

namespace App\Features\Settings\Infra\Controllers;

use App\Features\Settings\App\UseCases\GetSettingsUseCase;
use App\Features\Settings\App\UseCases\UpdateFeatureFlagsUseCase;
use App\Features\Settings\App\UseCases\UpdateSettingsUseCase;
use App\Features\Settings\Infra\Requests\UpdateFeatureFlagsRequest;
use App\Features\Settings\Infra\Requests\UpdateSettingsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Response;
use Inertia\ResponseFactory;

final class SettingsController
{
    public function __construct(
        private readonly GetSettingsUseCase $getSettings,
        private readonly UpdateSettingsUseCase $updateSettings,
        private readonly UpdateFeatureFlagsUseCase $updateFeatureFlags,
    ) {
    }

    public function index(ResponseFactory $inertia): Response
    {
        return $inertia->render('Settings/Admin/Pages/SettingsPage', [
            'settings' => $this->getSettings->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->updateSettings->update($request->input('settings', []));

        return redirect()->back();
    }

    public function updateFeatureFlags(UpdateFeatureFlagsRequest $request): JsonResponse
    {
        $flags = $request->input('flags', []);

        $this->updateFeatureFlags->update($flags);

        return response()->json(['data' => ['flags' => $flags]]);
    }
}
