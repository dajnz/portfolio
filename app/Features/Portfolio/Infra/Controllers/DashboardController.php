<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\UseCases\GetDashboardStatsUseCase;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET /admin` (routes.md:64) — the admin dashboard (admin-section-ui.md:
 * 220-228): the four stat cards (B32) + quick links rendered client-side.
 */
final class DashboardController
{
    public function __construct(
        private readonly GetDashboardStatsUseCase $useCase,
    ) {
    }

    public function index(ResponseFactory $inertia): Response
    {
        return $inertia->render('Portfolio/Admin/Pages/DashboardPage', [
            'stats' => $this->useCase->get(),
        ]);
    }
}
