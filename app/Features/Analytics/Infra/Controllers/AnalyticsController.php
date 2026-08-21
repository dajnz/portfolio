<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Controllers;

use App\Features\Analytics\App\UseCases\GetAnalyticsDashboardUseCase;
use App\Features\Analytics\App\UseCases\GetMetricRankingsUseCase;
use App\Features\Analytics\App\UseCases\GetMetricSeriesUseCase;
use App\Features\Analytics\App\UseCases\TrackEventUseCase;
use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use App\Features\Analytics\Infra\Jobs\RebuildReportsJob;
use App\Features\Analytics\Infra\Requests\RankingsRequest;
use App\Features\Analytics\Infra\Requests\SeriesRequest;
use App\Features\Analytics\Infra\Requests\TrackEventsRequest;
use Illuminate\Support\Facades\Bus;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Analytics endpoints (routes.md:136-139,189): the public event
 * ingestion (`POST /api/analytics/track` — rate-limited, no
 * session/CSRF per routes.md:184, returns 204 No Content) and the
 * admin dashboard — the Inertia page (`GET /admin/analytics`), the
 * chart series + ranking JSON endpoints and the "Generate Now"
 * full-rebuild dispatch (`POST /admin/analytics/generate-reports`,
 * 202 — the dashboard reloads on completion, analytics.md:212).
 */
final class AnalyticsController
{
    public function __construct(
        private readonly TrackEventUseCase $trackEvents,
        private readonly GetAnalyticsDashboardUseCase $dashboard,
        private readonly GetMetricSeriesUseCase $seriesCase,
        private readonly GetMetricRankingsUseCase $rankingsCase,
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
        return $inertia->render('Analytics/Admin/Pages/AnalyticsDashboardPage', $this->dashboard->get());
    }

    public function series(SeriesRequest $request): JsonResponse
    {
        return response()->json($this->seriesCase->series(
            metric: MetricName::from($request->validated('metric')),
            span: MetricSpan::from($request->validated('span')),
            dimension: $request->validated('dimension'),
        ));
    }

    public function rankings(RankingsRequest $request): JsonResponse
    {
        return response()->json($this->rankingsCase->rankings(
            metric: MetricName::from($request->validated('metric')),
            variant: (string) $request->validated('variant'),
            scope: (string) $request->validated('scope'),
        ));
    }

    public function generate(): JsonResponse
    {
        Bus::dispatch(new RebuildReportsJob(recomputeMetrics: true));

        return response()->json(['dispatched' => true], HttpResponse::HTTP_ACCEPTED);
    }
}
