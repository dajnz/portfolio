<?php

declare(strict_types=1);

namespace Tests\Unit\Backend\Architecture;

use Tests\Support\Architecture\LayerPurityScanner;

const APP_LAYER_ALLOWED_IMPORT_PATTERN = '/^App\\\\Features\\\\[A-Za-z]+\\\\(App|Domain)\\\\/';

/**
 * The App layer's framework-free surface (architecture.md): it imports only
 * `App/`, `Domain/`, `Shared/Domain/` and `Shared/App/` — never facades,
 * Eloquent/HTTP types, framework helpers or `Infra/` classes.
 */
function appLayerForbiddenImport(string $imported): ?string
{
    if (! str_starts_with($imported, 'App\\')) {
        return null;
    }

    if (preg_match(APP_LAYER_ALLOWED_IMPORT_PATTERN, $imported) === 1) {
        return null;
    }

    return "non-app-layer import `{$imported}`";
}

it('keeps the app layer free of Laravel dependencies and infra imports', function (): void {
    $scanner = new LayerPurityScanner(dirname(__DIR__, 4));

    expect($scanner->helperNames())->not->toBeEmpty();

    $violations = $scanner->scan(
        'app/Features/*/App',
        'Tests\Unit\Backend\Architecture\appLayerForbiddenImport',
    );

    expect($violations)->toBe([]);
});
