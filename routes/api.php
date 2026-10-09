<?php

declare(strict_types=1);

foreach (glob(app_path('Features/*/Infra/api_routes.php')) as $file) {
    require $file;
}
