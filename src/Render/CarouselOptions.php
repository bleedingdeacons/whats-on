<?php

/**
 * How the carousel looks and behaves, parsed from module attributes.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Render;

use WhatsOn\Events\QueryOptions;
use WhatsOn\Support\Attrs;

final class CarouselOptions
{
    private const HEADING_LEVELS       = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ];
    private const DEFAULT_AUTOPLAY_SEC = 5;
    private const MAX_AUTOPLAY_SEC     = 60;

    public function __construct(
        public readonly string $headingLevel = 'h3',
        public readonly bool $loop = false,
        public readonly bool $showArrows = true,
        public readonly string $noEventsText = '',
        public readonly bool $autoplay = true,
        public readonly int $autoplaySeconds = self::DEFAULT_AUTOPLAY_SEC,
    ) {
    }

    /**
     * @param array<string, mixed> $attrs Module attributes.
     */
    public static function fromAttrs(array $attrs): self
    {
        $heading = strtolower(Attrs::string(Attrs::at($attrs, 'title.decoration.font.font.desktop.value.headingLevel'), 'h3'));

        return new self(
            headingLevel: in_array($heading, self::HEADING_LEVELS, true) ? $heading : 'h3',
            loop: QueryOptions::parseBool(Attrs::desktop($attrs, 'carousel.advanced.loop') ?? 'off'),
            showArrows: QueryOptions::parseBool(Attrs::desktop($attrs, 'arrows.advanced.show') ?? 'on'),
            noEventsText: trim(Attrs::string(Attrs::desktop($attrs, 'events.advanced.noEventsText'))),
            autoplay: QueryOptions::parseBool(Attrs::desktop($attrs, 'carousel.advanced.autoplay') ?? 'on'),
            autoplaySeconds: self::seconds(Attrs::desktop($attrs, 'carousel.advanced.autoplaySpeed')),
        );
    }

    /**
     * Milliseconds between automatic steps, or 0 when auto scroll is off.
     */
    public function autoplayInterval(): int
    {
        return $this->autoplay ? max(1, $this->autoplaySeconds) * 1000 : 0;
    }

    private static function seconds(mixed $value): int
    {
        if (null === $value || '' === $value) {
            return self::DEFAULT_AUTOPLAY_SEC;
        }

        $number = (int) filter_var(Attrs::string($value), FILTER_SANITIZE_NUMBER_INT);

        return max(1, min(self::MAX_AUTOPLAY_SEC, $number));
    }
}
