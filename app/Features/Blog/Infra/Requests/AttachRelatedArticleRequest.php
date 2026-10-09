<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * `POST /admin/blog/{article}/related/{relatedArticle}`: validates
 * that the related article exists and is published and that it is not
 * the article itself (the `related_articles` CHECK constraint backs
 * the latter up at the DB level). The duplicate-link 409 is thrown by
 * the repository.
 */
final class AttachRelatedArticleRequest extends FormRequest
{
    /**
     * The related article is a ROUTE parameter — merged into the
     * validation data (FormRequest validation covers the request
     * input only).
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'relatedArticle' => $this->route('relatedArticle'),
        ]);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'relatedArticle' => [
                'required',
                'integer',
                Rule::exists('blog_articles', 'id')->where('is_published', true),
            ],
        ];
    }

    /**
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $articleId = (int) $this->route('article');
                $relatedArticleId = (int) $this->input('relatedArticle');

                if ($articleId === $relatedArticleId) {
                    $validator->errors()->add(
                        'relatedArticle',
                        __('validation.not_in', ['attribute' => 'related article']),
                    );
                }
            },
        ];
    }
}
