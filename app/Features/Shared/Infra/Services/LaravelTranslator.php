<?php

declare(strict_types=1);

namespace App\Features\Shared\Infra\Services;

use App\Features\Shared\App\Contracts\TranslatorInterface;
use Illuminate\Support\Facades\Lang;

/**
 * The framework translator behind the app-layer {@see TranslatorInterface}
 * contract, resolving against the current locale.
 */
final class LaravelTranslator implements TranslatorInterface
{
    public function translate(string $key, array $replace = []): string
    {
        return (string) Lang::get($key, $replace);
    }
}
