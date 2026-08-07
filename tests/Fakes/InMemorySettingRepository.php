<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Features\Settings\Domain\Entities\Setting;
use App\Features\Settings\Domain\Enums\SettingName;
use App\Features\Settings\Domain\RepositoryInterfaces\SettingRepositoryInterface;
use App\Features\Settings\Domain\ValueObjects\SettingValue;

/**
 * In-memory settings repository fake for unit tests (values keyed by
 * setting name, type-conformant via the domain objects).
 */
final class InMemorySettingRepository implements SettingRepositoryInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $values = [];

    /**
     * @param  array<string, mixed>  $values  SettingName value => typed value.
     */
    public function __construct(array $values = [])
    {
        foreach ($values as $name => $value) {
            $this->saveValue(SettingName::from((string) $name), $value);
        }
    }

    /**
     * @return Setting[]
     */
    public function findAll(): array
    {
        $settings = [];
        foreach ($this->values as $name => $value) {
            $settings[] = new Setting(SettingName::from((string) $name), new SettingValue(SettingName::from((string) $name)->type(), $value));
        }

        return $settings;
    }

    public function find(SettingName $name): ?Setting
    {
        if (! array_key_exists($name->value, $this->values)) {
            return null;
        }

        return new Setting($name, new SettingValue($name->type(), $this->values[$name->value]));
    }

    public function save(Setting $setting): void
    {
        $this->values[$setting->toName()] = $setting->value();
    }

    public function saveValue(SettingName $name, mixed $value): void
    {
        $this->values[$name->value] = (new SettingValue($name->type(), $value))->value();
    }

    public function value(SettingName $name, mixed $default = null): mixed
    {
        return $this->values[$name->value] ?? $default;
    }

    public static function withDefaults(): self
    {
        return new self([
            SettingName::SupportedLocales->value => ['en', 'ru'],
            SettingName::ItemsPerPage->value => 12,
            SettingName::EmailOnFeedback->value => true,
        ]);
    }
}
