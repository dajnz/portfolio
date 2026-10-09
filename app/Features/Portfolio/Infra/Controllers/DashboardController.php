<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\UseCases\GetDashboardStatsUseCase;
use Inertia\Response;
use Inertia\ResponseFactory;

final class DashboardController
{
    public function __construct(
        private readonly GetDashboardStatsUseCase $dashboardStatsCase,
    ) {
    }

    public function index(ResponseFactory $inertia): Response
    {
        return $inertia->render('Portfolio/Admin/Pages/DashboardPage', [
            'stats' => $this->dashboardStatsCase->get(),
        ]);
    }
}
