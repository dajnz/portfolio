<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Controllers;

use App\Features\Analytics\App\Exceptions\InvalidAnalyticsEventException;
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
use Illuminate\Support\Facades\Response as ResponseFacade;
use Illuminate\Validation\ValidationException;
use Inertia\Response;
use Inertia\ResponseFactory;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Analytics endpoints: the public event ingestion
 * (`POST /api/analytics/track` — rate-limited, no session/CSRF,
 * returns 204 No Content) and the admin dashboard — the Inertia page
 * (`GET /admin/analytics`), the chart series + ranking JSON endpoints
 * and the "Generate Now" full-rebuild dispatch
 * (`POST /admin/analytics/generate-reports`, 202 — the dashboard
 * reloads on completion).
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
        try {
            $this->trackEvents->track(
                events: $request->all(),
                ip: (string) $request->ip(),
                userAgent: $request->userAgent(),
            );
        } catch (InvalidAnalyticsEventException) {
            throw ValidationException::withMessages([
                'batch' => [__('validation.analytics.event_invalid')],
            ]);
        }

        return ResponseFacade::noContent();
    }

    public function index(ResponseFactory $inertia): Response
    {
        return $inertia->render('Analytics/Admin/Pages/AnalyticsDashboardPage', $this->dashboardProps());
    }

    /**
     * The Inertia props — the dashboard DTO mapped onto the wire shape
     * (the transport structure is the controller's concern, not the app
     * layer's).
     *
     * @return array{
     *     overview_blocks: list<array{key: string, value: float, min?: float, max?: float}>,
     *     blog_blocks: list<array{key: string, value: float, min?: float, max?: float}>,
     *     projects_blocks: list<array{key: string, value: float}>,
     *     default_chart: array{metric: string, spans: array<string, array{points: list<array{label: string, value: float}>}>},
     *     default_rankings: array{metric: string, scope: string, rows: array<string, list<array<string, mixed>>>},
     * }
     */
    private function dashboardProps(): array
    {
        $dashboard = $this->dashboard->get();

        return [
            'overview_blocks' => $dashboard->overviewBlocks,
            'blog_blocks' => $dashboard->blogBlocks,
            'projects_blocks' => $dashboard->projectsBlocks,
            'default_chart' => $dashboard->defaultChart,
            'default_rankings' => $dashboard->defaultRankings,
        ];
    }

    public function series(SeriesRequest $request): JsonResponse
    {
        return ResponseFacade::json($this->seriesCase->series(
            metric: MetricName::from($request->validated('metric')),
            span: MetricSpan::from($request->validated('span')),
            dimension: $request->validated('dimension'),
        ));
    }

    public function rankings(RankingsRequest $request): JsonResponse
    {
        return ResponseFacade::json($this->rankingsCase->rankings(
            metric: MetricName::from($request->validated('metric')),
            variant: (string) $request->validated('variant'),
            scope: (string) $request->validated('scope'),
        ));
    }

    public function generate(): JsonResponse
    {
        Bus::dispatch(new RebuildReportsJob(recomputeMetrics: true));

        return ResponseFacade::json(['dispatched' => true], HttpResponse::HTTP_ACCEPTED);
    }
}
