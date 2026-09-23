<?php

declare(strict_types=1);

namespace App\Support\Automations;

use App\Models\AutomationSetting;
use Carbon\CarbonInterface;

final readonly class AutomationRow
{
    public function __construct(
        public AutomationDefinition $definition,
        public bool $enabled,
        public ?string $disabledReason,
        public ?string $disabledBy,
        public ?CarbonInterface $disabledAt,
    ) {}

    /**
     * @return list<self>
     */
    public static function all(): array
    {
        $settings = AutomationSetting::query()->with('disabledBy')->get()->keyBy('key');

        return array_map(
            fn (AutomationDefinition $definition): self => self::from($definition, $settings->get($definition->key)),
            AutomationCatalogue::all(),
        );
    }

    public static function find(string $key): ?self
    {
        $definition = AutomationCatalogue::find($key);

        if (! $definition instanceof AutomationDefinition) {
            return null;
        }

        $setting = AutomationSetting::query()->with('disabledBy')->where('key', $key)->first();

        return self::from($definition, $setting);
    }

    private static function from(AutomationDefinition $definition, mixed $setting): self
    {
        if (! $setting instanceof AutomationSetting || $setting->enabled) {
            return new self($definition, true, null, null, null);
        }

        $name = $setting->disabledBy?->name;

        return new self(
            $definition,
            false,
            $setting->disabled_reason,
            is_string($name) && $name !== '' ? $name : null,
            $setting->disabled_at,
        );
    }
}
