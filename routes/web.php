<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Entry point that includes every feature's route files. Feature files
| live at `app/Features/{Feature}/Infra/routes.php` (see routes.md).
|
*/

foreach (glob(app_path('Features/*/Infra/routes.php')) as $file) {
    require $file;
}

Route::get('/', function () {
    return view('welcome');
});
