<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * Config defaults + forever-cache overrides for admin-configurable workflow rules.
 */
abstract class CachedConfigSettings
{
    abstract protected static function cacheKey(): string;

    abstract protected static function configKey(): string;

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return config(static::configKey(), []) ?: [];
    }

    /**
     * @return array<string, mixed>
     */
    public static function all(): array
    {
        return array_merge(static::defaults(), Cache::get(static::cacheKey(), []) ?: []);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return static::all()[$key] ?? $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        return (bool) static::get($key, $default);
    }

    public static function int(string $key, int $default = 0): int
    {
        return (int) static::get($key, $default);
    }

    public static function float(string $key, float $default = 0.0): float
    {
        return (float) static::get($key, $default);
    }

    public static function string(string $key, string $default = ''): string
    {
        return (string) static::get($key, $default);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{previous: array<string, mixed>, current: array<string, mixed>}
     */
    public static function put(array $values): array
    {
        $previous = static::all();
        $current = array_merge($previous, $values);
        Cache::forever(static::cacheKey(), $current);

        return ['previous' => $previous, 'current' => $current];
    }

    public static function forget(): void
    {
        Cache::forget(static::cacheKey());
    }
}
