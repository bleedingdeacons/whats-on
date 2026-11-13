<?php

/**
 * An event's featured image.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Events;

final class EventImage
{
    public function __construct(
        public readonly string $src,
        public readonly string $alt = '',
        public readonly string $srcset = '',
        public readonly string $sizes = '',
        public readonly int $width = 0,
        public readonly int $height = 0,
    ) {
    }

    /**
     * @return array<string, string|int>
     */
    public function toArray(): array
    {
        return [
            'src'    => $this->src,
            'alt'    => $this->alt,
            'srcset' => $this->srcset,
            'sizes'  => $this->sizes,
            'width'  => $this->width,
            'height' => $this->height,
        ];
    }
}
