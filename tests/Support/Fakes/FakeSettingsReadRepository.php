<?php

declare(strict_types=1);

namespace Tests\Support\Fakes;

use App\Features\Shared\Domain\RepositoryInterfaces\SettingsReadRepositoryInterface;

final class FakeSettingsReadRepository implements SettingsReadRepositoryInterface
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function __construct(
        public array $values = [],
    ) {
    }

    public function value(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }
}
