<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The reaction toggle payload (`POST /api/blog/{article}/reaction`,
 * features/blog.yaml#ReactionRequest): the `reaction` type must be
 * `like` or `dislike` (422 otherwise).
 */
final class StoreReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reaction' => ['required', Rule::in(['like', 'dislike'])],
        ];
    }
}
