<?php

declare(strict_types=1);

use App\Features\Resume\Infra\Providers\ResumePdfInvalidationProvider;
use App\Features\Settings\Infra\Providers\SettingsMailConfigProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    SettingsMailConfigProvider::class,
    ResumePdfInvalidationProvider::class,
];
