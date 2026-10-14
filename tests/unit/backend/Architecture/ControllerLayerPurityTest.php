<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use Tests\Support\Architecture\LayerPurityScanner;

/**
 * Controllers are transport-only (architecture.md): they translate the HTTP
 * request into use-case calls and the use-case results into response shapes.
 * They never reach past the app layer into persistence — no domain repository
 * interfaces and no Eloquent models. The framework surface (HTTP types,
 * facades, helpers) is expected here, so only the imports are checked.
 */
function controllerLayerForbiddenImport(string $imported): ?string
{
    if (preg_match('/^App\\\\Features\\\\[A-Za-z]+\\\\Domain\\\\RepositoryInterfaces\\\\/', $imported) === 1) {
        return "repository import `{$imported}`";
    }

    if (preg_match('/^App\\\\Features\\\\[A-Za-z]+\\\\Infra\\\\Models\\\\/', $imported) === 1) {
        return "Eloquent model import `{$imported}`";
    }

    return null;
}

it('keeps controllers free of repository and Eloquent-model imports', function (): void {
    $scanner = new LayerPurityScanner(dirname(__DIR__, 4));

    $violations = $scanner->scan(
        'app/Features/*/Infra/Controllers',
        'Tests\Unit\Backend\Architecture\controllerLayerForbiddenImport',
        checkFrameworkSurface: false,
    );

    expect($violations)->toBe([]);
});
