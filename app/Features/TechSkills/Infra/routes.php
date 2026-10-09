<?php

declare(strict_types=1);

use App\Features\TechSkills\Infra\Controllers\TechSkillController;
use App\Features\TechSkills\Infra\Controllers\TechSkillGroupController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'auth.session_version', 'admin'])->group(function (): void {
    Route::get('/admin/skills', [TechSkillController::class, 'index']);
    Route::post('/admin/skills', [TechSkillController::class, 'store']);
    Route::put('/admin/skills/{skill}', [TechSkillController::class, 'update']);
    Route::delete('/admin/skills/{skill}', [TechSkillController::class, 'destroy']);
    Route::post('/admin/skills/reorder', [TechSkillController::class, 'reorder']);

    Route::post('/admin/skill-groups', [TechSkillGroupController::class, 'store']);
    Route::put('/admin/skill-groups/{skillGroup}', [TechSkillGroupController::class, 'update']);
    Route::delete('/admin/skill-groups/{skillGroup}', [TechSkillGroupController::class, 'destroy']);
    Route::post('/admin/skill-groups/reorder', [TechSkillGroupController::class, 'reorder']);
});
