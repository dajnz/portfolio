<?php

declare(strict_types=1);

namespace App\Features\Blog\Infra\Controllers;

use App\Features\Blog\App\UseCases\GetRssFeedUseCase;
use App\Features\Shared\App\Services\RssXmlBuilder;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Illuminate\Support\Facades\App;

/**
 * `GET /rss`: the RSS 2.0 feed of the 20 most recent published blog
 * articles. The feed DATA is built by the app layer
 * ({@see GetRssFeedUseCase}) and the XML by the shared
 * {@see RssXmlBuilder} — the controller only wires the two and returns
 * the response (the transport shape is a controller concern). The
 * locale middleware resolves the names per the visitor's cookie/URL.
 */
final class FeedController
{
    public function __construct(
        private readonly GetRssFeedUseCase $feedCase,
        private readonly RssXmlBuilder $xml,
    ) {
    }

    public function rss(): HttpResponse
    {
        $feed = $this->feedCase->get(App::getLocale());

        return Response::make($this->xml->build($feed), SymfonyResponse::HTTP_OK, [
            'Content-Type' => 'application/rss+xml; charset=UTF-8',
        ]);
    }
}
