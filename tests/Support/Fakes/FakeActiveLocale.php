<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Shared\App\Contracts\ActiveLocaleInterface;

final class FakeActiveLocale implements ActiveLocaleInterface
{
    /**
     * @param  string[]  $locales
     */
    public function __construct(
        private readonly array $locales = ['en', 'ru'],
        private readonly string $current = 'en',
    ) {
    }

    public function current(): string
    {
        return $this->current;
    }

    /**
     * @return string[]
     */
    public function activeLocales(): array
    {
        return $this->locales;
    }
}
