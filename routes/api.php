<?php

declare(strict_types=1);

use Illuminate\Support\Facades\App;

foreach (glob(App::path('Features/*/Infra/api_routes.php')) as $file) {
    require $file;
}
