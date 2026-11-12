<?php

namespace App\Settings;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only way code reads or writes store settings. Unsaved keys fall back to
 * the defaults in SettingsSchema. Values are cached until the next save.
 */
class Settings
{
    private const CACHE_KEY = 'settings.values';

    public function get(string $key): mixed
    {
        $definitions = SettingsSchema::definitions();

        if (! array_key_exists($key, $definitions)) {
            throw new InvalidArgumentException("Unknown setting [{$key}].");
        }

        $stored = $this->stored();

        return array_key_exists($key, $stored) ? $stored[$key] : $definitions[$key]['default'];
    }

    public function bool(string $key): bool
    {
        return (bool) $this->get($key);
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    public function string(string $key): string
    {
        return (string) $this->get($key);
    }

    /**
     * All settings with defaults applied.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = $this->stored();
        $values = [];

        foreach (SettingsSchema::definitions() as $key => $definition) {
            $values[$key] = array_key_exists($key, $stored) ? $stored[$key] : $definition['default'];
        }

        return $values;
    }

    /**
     * Saves the given values. Only keys whose value changed are written, so
     * the audit log shows exactly what was changed.
     *
     * @param  array<string, mixed>  $values
     */
    public function set(array $values): void
    {
        $current = $this->all();

        DB::transaction(function () use ($values, $current): void {
            foreach ($values as $key => $value) {
                if (! array_key_exists($key, $current)) {
                    throw new InvalidArgumentException("Unknown setting [{$key}].");
                }

                $value = SettingsSchema::cast($key, $value);

                if ($value === $current[$key]) {
                    continue;
                }

                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
            }
        });

        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, fn (): array => Setting::query()->pluck('value', 'key')->all());
    }
}
