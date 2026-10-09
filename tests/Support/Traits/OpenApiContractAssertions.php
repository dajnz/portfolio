<?php

declare(strict_types=1);

namespace Tests\Support\Traits;

use App\Features\Shared\Infra\OpenApi\OpenApiSpecDocument;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Testing\TestResponse;
use League\OpenAPIValidation\PSR7\Exception\ValidationFailed;

/**
 * Shared plumbing for the OpenAPI contract suite: per-operation
 * scenarios, real-request dispatch and response validation against
 * `docs/openapi/openapi.yaml` via `league/openapi-psr7-validator`.
 */
trait OpenApiContractAssertions
{
    /**
     * Scenario registry — keyed by `operationId` from `docs/openapi/openapi.yaml`.
     * Every operation must be listed here (enforced by the coverage test);
     * operations whose endpoint does not exist yet stay `implemented: false`
     * and are asserted as 404s.
     *
     * @return array<string, array{path: string, method: string, implemented: bool, implemented_in?: string, cases?: int[]}>
     */
    public static function scenarios(): array
    {
        return [
            'searchSkills' => [
                'path' => '/api/skills/search',
                'method' => 'get',
                'implemented' => true,
                'cases' => [200],
            ],
            'uploadFile' => [
                'path' => '/api/upload',
                'method' => 'post',
                'implemented' => true,
                'cases' => [401, 200, 422],
            ],
            'deleteFile' => [
                'path' => '/api/upload',
                'method' => 'delete',
                'implemented' => true,
                'cases' => [401, 404, 200],
            ],
            'submitFeedback' => [
                'path' => '/api/feedback',
                'method' => 'post',
                'implemented' => true,
                'cases' => [201, 422, 429],
            ],
            'toggleArticleReaction' => [
                'path' => '/api/blog/{article}/reaction',
                'method' => 'post',
                'implemented' => true,
                'implemented_in' => 'Step 8.2',
                'cases' => [200, 422],
            ],
            'createTag' => [
                'path' => '/api/tags',
                'method' => 'post',
                'implemented' => true,
                'cases' => [401, 201, 422],
            ],
            'searchTags' => [
                'path' => '/api/tags/search',
                'method' => 'get',
                'implemented' => true,
                'implemented_in' => 'Step 8.2',
                'cases' => [200],
            ],
            'searchUnifiedBlogTags' => [
                'path' => '/api/blog-tags/search',
                'method' => 'get',
                'implemented' => true,
                'implemented_in' => 'Step 8.2',
                'cases' => [200],
            ],
            'trackEvents' => [
                'path' => '/api/analytics/track',
                'method' => 'post',
                'implemented' => true,
                'implemented_in' => 'Step 11.1',
                'cases' => [204, 422, 429],
            ],
            'regenerateResume' => [
                'path' => '/api/resume/regenerate',
                'method' => 'post',
                'implemented' => true,
                'implemented_in' => 'Step 10.1',
                'cases' => [200],
            ],
            'listBlogArticlesJson' => [
                'path' => '/blog',
                'method' => 'get',
                'implemented' => true,
                'implemented_in' => 'Step 8.2',
                'cases' => [200],
            ],
            'listProjectsJson' => [
                'path' => '/projects',
                'method' => 'get',
                'implemented' => true,
                'cases' => [200],
            ],
        ];
    }

    protected function document(): OpenApiSpecDocument
    {
        return app(OpenApiSpecDocument::class);
    }

    /**
     * Validates the response status + body of a real request against the
     * operation's declared responses (league/openapi-psr7-validator).
     */
    protected function assertConformsToSpec(string $operationId, int $case, TestResponse $response): void
    {
        $scenario = self::scenarios()[$operationId];
        $expectedStatus = $scenario['cases'][$case];

        $this->assertSame($expectedStatus, $response->getStatusCode());

        foreach ($this->document()->pathFinder($scenario['path'], $scenario['method'])->search() as $operation) {
            try {
                $this->document()->responseValidator()->validate($operation, $this->toPsr7($response));
            } catch (ValidationFailed $exception) {
                $this->fail(sprintf(
                    'Contract violation for %s %s (%s) — %s',
                    strtoupper($scenario['method']),
                    $scenario['path'],
                    $operationId,
                    $exception->getMessage(),
                ));
            }
        }
    }

    /**
     * Not-yet-implemented endpoints (scenario `implemented: false`) must
     * answer 404 with the general `{message}` error envelope.
     */
    protected function assertPendingOperation(TestResponse $response): void
    {
        $response->assertNotFound();
        $this->assertIsString($response->json('message'));
        $this->assertNotEmpty($response->json('message'));
    }

    private function toPsr7(TestResponse $response): Psr7Response
    {
        return new Psr7Response(
            $response->getStatusCode(),
            $response->headers->all(),
            (string) $response->getContent(),
        );
    }
}
