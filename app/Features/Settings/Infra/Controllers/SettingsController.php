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
        private readonly GetSettingsUseCase $getSettingsCase,
        private readonly UpdateSettingsUseCase $updateSettingsCase,
        private readonly UpdateFeatureFlagsUseCase $updateFeatureFlagsCase,
    ) {
    }

    public function index(ResponseFactory $inertia): Response
    {
        return $inertia->render('Settings/Admin/Pages/SettingsPage', [
            'settings' => $this->getSettingsCase->all(),
        ]);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $this->updateSettingsCase->update($request->input('settings', []));

        return redirect()->back();
    }

    public function updateFeatureFlags(UpdateFeatureFlagsRequest $request): JsonResponse
    {
        $flags = $request->input('flags', []);

        $this->updateFeatureFlagsCase->update($flags);

        return response()->json(['data' => ['flags' => $flags]]);
    }
}
