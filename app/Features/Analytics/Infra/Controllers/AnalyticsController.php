<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Controllers;

use App\Features\Analytics\App\UseCases\GetAnalyticsDashboardUseCase;
use App\Features\Analytics\App\UseCases\TrackEventUseCase;
use App\Features\Analytics\Domain\Enums\ReportFrequency;
use App\Features\Analytics\Infra\Jobs\AggregateAnalyticsReportsJob;
use App\Features\Analytics\Infra\Requests\TrackEventsRequest;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Bus;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Analytics endpoints (routes.md:136,189): the public event ingestion
 * (`POST /api/analytics/track` — rate-limited, no session/CSRF per
 * routes.md:184, returns 204 No Content) and the admin dashboard
 * (`GET /admin/analytics` + the B220 "Generate Now" endpoint
 * `POST /admin/analytics/generate-reports`).
 */
final class AnalyticsController
{
    public function __construct(
        private readonly TrackEventUseCase $trackEvents,
        private readonly GetAnalyticsDashboardUseCase $dashboard,
        private readonly SettingRepositoryInterface $settings,
    ) {
    }

    public function track(TrackEventsRequest $request): HttpResponse
    {
        // `validated()` filters unruled keys (free-form `metadata`
        // fields like `title`/`article_id` have no validation rules and
        // would be dropped) — the whole request body is the batch, its
        // shape was already capped by the request.
        $this->trackEvents->track(
            events: $request->all(),
            ip: (string) $request->ip(),
            userAgent: $request->userAgent(),
        );

        return response()->noContent();
    }

    public function index(ResponseFactory $inertia): Response
    {
        return $inertia->render('Analytics/Admin/Pages/AnalyticsDashboardPage', [
            'reports' => $this->dashboard->get(),
        ]);
    }

    public function generate(): JsonResponse
    {
        $frequency = ReportFrequency::fromString(
            (string) $this->settings->value(SettingName::AnalyticsReportFrequency, 'weekly'),
        );
        $period = $frequency->currentPeriod(now()->toDateTimeImmutable());

        Bus::dispatch(new AggregateAnalyticsReportsJob(
            $period->toPeriodStart(),
            $period->toPeriodEnd(),
        ));

        return response()->json(['dispatched' => true], 202);
    }
}
