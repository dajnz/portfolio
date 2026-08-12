<?php

declare(strict_types=1);

namespace App\Features\Projects\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * `PATCH /admin/projects/{project}/tech-writeup`
 * (content-management.md:159): `{ tech_writeup_article_id: number |
 * null }` — the article must exist and be published (same rule as the
 * form's Relations tab); `null` removes the link.
 */
final class UpdateTechWriteupRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'tech_writeup_article_id' => [
                'nullable',
                'integer',
                Rule::exists('blog_articles', 'id')->where('is_published', true),
            ],
        ];
    }
}
