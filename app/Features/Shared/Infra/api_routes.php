<?php

declare(strict_types=1);

use App\Features\Shared\Infra\Controllers\SkillSearchController;
use Illuminate\Support\Facades\Route;

Route::get('/skills/search', [SkillSearchController::class, 'index']);
