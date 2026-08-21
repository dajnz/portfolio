<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Controllers;

use App\Features\Analytics\App\UseCases\TrackEventUseCase;
use App\Features\Analytics\Infra\Requests\TrackEventsRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Analytics endpoints (routes.md:189): the public event ingestion
 * (`POST /api/analytics/track` — rate-limited, no session/CSRF per
 * routes.md:184, returns 204 No Content). The old-schema dashboard +
 * "Generate Now" actions were removed with the periodic report
 * pipeline (step 11.3.1); the new dashboard + JSON endpoints land in
 * 11.3.4.
 */
final class AnalyticsController
{
    public function __construct(
        private readonly TrackEventUseCase $trackEvents,
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
}
