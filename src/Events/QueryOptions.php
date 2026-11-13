<?php

/**
 * Which events to fetch, parsed from module attributes or a REST request.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Events;

use WhatsOn\Support\Attrs;

final class QueryOptions
{
    public const DEFAULT_COUNT = 9;
    public const MAX_COUNT     = 50;

    /**
     * @param list<string> $categories Event category slugs; empty means all.
     */
    public function __construct(
        public readonly int $count = self::DEFAULT_COUNT,
        public readonly array $categories = [],
        public readonly bool $featuredOnly = false,
    ) {
    }

    /**
     * Build from Divi module attributes (`events.advanced.*`, desktop values).
     *
     * @param array<string, mixed> $attrs Module attributes.
     */
    public static function fromAttrs(array $attrs): self
    {
        return self::fromValues(
            Attrs::desktop($attrs, 'events.advanced.count'),
            Attrs::desktop($attrs, 'events.advanced.categories'),
            Attrs::desktop($attrs, 'events.advanced.featuredOnly'),
        );
    }

    /**
     * Build from loose values — strings from the builder or query args from REST.
     */
    public static function fromValues(mixed $count, mixed $categories, mixed $featuredOnly): self
    {
        return new self(
            self::parseCount($count),
            self::parseCategories($categories),
            self::parseBool($featuredOnly),
        );
    }

    public static function parseCount(mixed $value): int
    {
        if (null === $value || '' === $value) {
            return self::DEFAULT_COUNT;
        }
        // The range field can store "9" or "9px"-style values; take the leading integer.
        $number = (int) filter_var(Attrs::string($value), FILTER_SANITIZE_NUMBER_INT);

        return max(1, min(self::MAX_COUNT, $number));
    }

    /**
     * @return list<string>
     */
    public static function parseCategories(mixed $value): array
    {
        if (is_array($value)) {
            $value = implode(',', array_map(static fn (mixed $item): string => Attrs::string($item), $value));
        }
        if (! is_string($value) || '' === trim($value)) {
            return [];
        }

        // A list rather than array keys: a numeric slug ("2026") would become an int key.
        $slugs = [];
        foreach (explode(',', $value) as $slug) {
            $slug = (string) preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($slug)));
            if ('' !== $slug && ! in_array($slug, $slugs, true)) {
                $slugs[] = $slug;
            }
        }

        return $slugs;
    }

    public static function parseBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(Attrs::string($value)), [ 'on', '1', 'true', 'yes' ], true);
    }
}
