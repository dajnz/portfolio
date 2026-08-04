<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Entry point that includes every feature's API routes. Feature files live
| at `app/Features/{Feature}/Infra/api_routes.php` (see routes.md).
|
*/

foreach (glob(app_path('Features/*/Infra/api_routes.php')) as $file) {
    require $file;
}
