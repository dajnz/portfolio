<?php

declare(strict_types=1);

namespace App\Features\Analytics\Infra\Requests;

use App\Features\Analytics\Domain\Enums\MetricName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The `GET /admin/analytics/rankings` validator (analytics.md:226,
 * B293, 11.3.4): the metric must be a known catalog name, the variant
 * one of top/bottom/liked/disliked and the scope one of
 * page/article/project. The (metric, scope) pairs are the ranking
 * surface of the catalog — the count/avg metrics with dimension rows
 * (`time_on_page_seconds` per page; `views`/`unique_visitors` per
 * article and per project; the read-time/completion/reactions metrics
 * per article). The liked/disliked variants pair with the reactions
 * metrics (most liked / most disliked articles).
 */
final class RankingsRequest extends FormRequest
{
    /** @var array<string, list<string>> */
    private const ALLOWED_PAIRS = [
        'page' => ['time_on_page_seconds'],
        'article' => [
            'views',
            'unique_visitors',
            'read_time_seconds',
            'completion_rate_sum',
            'reactions_likes',
            'reactions_dislikes',
        ],
        'project' => ['views', 'unique_visitors'],
    ];

    private const VARIANTS = ['top', 'bottom', 'liked', 'disliked'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'metric' => ['required', 'string', Rule::enum(MetricName::class)],
            'variant' => ['required', 'string', Rule::in(self::VARIANTS)],
            'scope' => ['required', 'string', Rule::in(array_keys(self::ALLOWED_PAIRS))],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $metric = (string) $this->input('metric');
            $variant = (string) $this->input('variant');
            $scope = (string) $this->input('scope');

            if ($metric !== '' && $scope !== '' && ! in_array($metric, self::ALLOWED_PAIRS[$scope] ?? [], true)) {
                $validator->errors()->add('metric', __('validation.analytics_rankings_metric_not_allowed'));
            }

            if ($variant === 'liked' && $metric !== MetricName::ReactionsLikes->value) {
                $validator->errors()->add('variant', __('validation.analytics_rankings_liked_metric'));
            }

            if ($variant === 'disliked' && $metric !== MetricName::ReactionsDislikes->value) {
                $validator->errors()->add('variant', __('validation.analytics_rankings_disliked_metric'));
            }
        });
    }
}
