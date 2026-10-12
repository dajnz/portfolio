<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Requests;

use App\Features\Shared\Infra\Services\ActiveLocaleService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /admin/blog` / `PUT /admin/blog/{article}`: the shared fields
 * plus the per-locale translatable ones. `title` (min 3),
 * `short_description` and `content` (min 50) are required per active
 * locale. The slug is shape-validated here (kebab-case regex, max 200)
 * — the DB-dependent checks live in the store/update use cases via
 * `ArticleDraftValidator` (the auto-generated value skips the
 * uniqueness rejection because the use case re-resolves it server-side
 * with a numeric collision suffix; a MANUAL override that collides is
 * rejected there).
 *
 * Nested arrays: `tags` (article-tag ids), `skill_tags` (skill ids
 * attached via `article_skill_pivot`) and `related_articles`
 * (existing article ids — the published-target / already-linked /
 * not-self checks are the use cases' job). The lead image is a RustFS
 * path string (`nullable, string, max:255` — the file checks are the
 * upload endpoint's job).
 */
final class StoreArticleRequest extends FormRequest
{
    public function __construct(
        private readonly ActiveLocaleService $locales,
    ) {
        parent::__construct();
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $rules = [
            'slug' => ['required', 'string', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'max:200'],
            'lead_image' => ['nullable', 'string', 'max:255'],
            'is_published' => ['nullable', 'boolean'],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', 'exists:article_tags,id'],
            'skill_tags' => ['nullable', 'array'],
            'skill_tags.*' => ['integer', 'exists:skills,id'],
            'related_articles' => ['nullable', 'array'],
            'related_articles.*' => ['integer', 'exists:blog_articles,id'],
        ];

        foreach ($this->activeLocales() as $locale) {
            $rules["title.$locale"] = ['required', 'string', 'min:3', 'max:200'];
            $rules["short_description.$locale"] = ['required', 'string', 'max:300'];
            $rules["content.$locale"] = ['required', 'string', 'min:50'];
            $rules["lead_image_alt.$locale"] = ['nullable', 'string', 'max:500'];
        }

        $articleId = $this->route('article');
        if ($articleId !== null) {
            // An article cannot link to itself (the `related_articles`
            // CHECK constraint backs it up at the DB level; the
            // use-case validator re-checks it before the sync).
            $rules['related_articles.*'][] = 'not_in:'.$articleId;
        }

        return $rules;
    }

    /**
     * Friendly attribute names for the per-locale fields — the raw
     * `title.en` keys would otherwise leak into the validation
     * messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['slug' => __('admin.blog.slug')];
        foreach ($this->activeLocales() as $locale) {
            $suffix = ' ['.strtoupper($locale).']';
            $attributes["title.$locale"] = __('common.title').$suffix;
            $attributes["short_description.$locale"] = __('admin.blog.short_description').$suffix;
            $attributes["content.$locale"] = __('admin.blog.content').$suffix;
            $attributes["lead_image_alt.$locale"] = __('admin.blog.alt_text').$suffix;
        }

        return $attributes;
    }

    /**
     * @return string[]
     */
    private function activeLocales(): array
    {
        return $this->locales->activeLocales();
    }
}
