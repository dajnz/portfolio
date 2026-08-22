<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Requests;

use App\Features\Analytics\Domain\Enums\MetricName;
use App\Features\Analytics\Domain\Enums\MetricSpan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The `GET /admin/analytics/series` validator (analytics.md:225,
 * 11.3.4): the metric must be a known catalog name and the span one of
 * the report spans. The `dimension` filter is exclusive to the
 * per-target `cta_clicks` rows — the seven targets (the plain
 * `CallToActionTarget` values plus the `language`/`theme` switch
 * dimensions, analytics.md:175) — required for `cta_clicks` and
 * rejected for every other metric.
 */
final class SeriesRequest extends FormRequest
{
    /** @var list<string> */
    private const CTA_TARGETS = ['github', 'demo', 'cv', 'feedback', 'projects', 'language', 'theme'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metric' => ['required', 'string', Rule::enum(MetricName::class)],
            'span' => ['required', 'string', Rule::enum(MetricSpan::class)],
            'dimension' => ['sometimes', 'string', Rule::in(self::CTA_TARGETS)],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $metric = $this->input('metric');
            $dimension = $this->input('dimension');

            if ($metric === MetricName::CtaClicks->value && $dimension === null) {
                $validator->errors()->add('dimension', __('validation.analytics_series_dimension_required'));
            }

            if ($metric !== MetricName::CtaClicks->value && $dimension !== null) {
                $validator->errors()->add('dimension', __('validation.analytics_series_dimension_forbidden'));
            }
        });
    }
}
