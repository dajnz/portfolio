<?php

declare(strict_types=1);

namespace App\Features\Portfolio\Infra\Controllers;

use App\Features\Portfolio\App\UseCases\GetLandingPageDataUseCase;
use App\Features\Portfolio\Infra\Services\LandingPagePropsBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Inertia\Response;
use Inertia\ResponseFactory;

/**
 * `GET /` and `GET /{locale}` — the landing page: about block, skills,
 * featured projects, contacts and SEO props (Person + WebSite JSON-LD).
 * The `{locale}` path constraint (`en|ru`) is enforced at route
 * registration.
 */
final class LandingController
{
    public function __construct(
        private readonly GetLandingPageDataUseCase $landingPageCase,
        private readonly LandingPagePropsBuilder $props,
    ) {
    }

    public function index(ResponseFactory $inertia, Request $request): Response
    {
        return $inertia->render(
            'Portfolio/Public/Pages/LandingPage',
            $this->props->build(
                $request,
                fn (): array => $this->landingPageCase->get(App::getLocale()),
            ),
        );
    }
}
