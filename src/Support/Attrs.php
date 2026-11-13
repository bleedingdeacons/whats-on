<?php

/**
 * Typed reads from loosely shaped data: Divi block attributes, post meta,
 * options and REST parameters all arrive as mixed.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Support;

final class Attrs
{
    /**
     * The value at a dot-separated path in nested arrays, or null when any
     * segment is missing or not an array.
     *
     *   Attrs::at($attrs, 'events.advanced.count.desktop.value')
     *
     * @param array<mixed> $data
     */
    public static function at(array $data, string $path): mixed
    {
        $value = $data;
        foreach (explode('.', $path) as $key) {
            if (! is_array($value) || ! array_key_exists($key, $value)) {
                return null;
            }
            $value = $value[$key];
        }

        return $value;
    }

    /**
     * A Divi attribute's desktop value: `<path>.desktop.value`.
     *
     * @param array<mixed> $data
     */
    public static function desktop(array $data, string $path): mixed
    {
        return self::at($data, $path . '.desktop.value');
    }

    /**
     * A scalar as a string; anything else (null, arrays, objects) as $default.
     */
    public static function string(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * An array with string keys, or [] for anything that is not an array.
     *
     * @return array<string, mixed>
     */
    public static function map(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $map = [];
        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }
}
