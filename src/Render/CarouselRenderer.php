<?php

/**
 * Carousel markup. Pure string building so it can be tested without WordPress or Divi;
 * the Visual Builder preview (assets/src/builder/EventsCarousel.jsx) mirrors this structure.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Render;

use WhatsOn\Events\DateFormatter;
use WhatsOn\Events\EventCard;

final class CarouselRenderer
{
    public function __construct(private readonly DateFormatter $dates)
    {
    }

    /**
     * @param list<EventCard> $events Events to show, in order.
     */
    public function render(array $events, CarouselOptions $options): string
    {
        if ([] === $events) {
            $message = '' !== $options->noEventsText
                ? $options->noEventsText
                : __('There are no upcoming events at the moment.', 'whats-on');

            return '<div class="whats-on whats-on--empty"><p class="whats-on__empty">' . esc_html($message) . '</p></div>';
        }

        $slides = '';
        foreach ($events as $index => $event) {
            $slides .= $this->slide($event, $options, $index, count($events));
        }

        // A single event is centred, and there is nothing to step through, so no buttons.
        $single = 1 === count($events);
        $nav    = $options->showArrows && ! $single ? $this->nav() : '';

        return sprintf(
            '<div class="%7$s" data-whats-on data-loop="%1$s" style="%2$s" role="region" aria-roledescription="%3$s" aria-label="%4$s">'
                . '<div class="whats-on__viewport"><ul class="whats-on__track" role="list" tabindex="0">%5$s</ul></div>%6$s'
            . '</div>',
            $options->loop ? 'on' : 'off',
            esc_attr($options->styleAttribute()),
            esc_attr__('carousel', 'whats-on'),
            esc_attr__("What's On", 'whats-on'),
            $slides,
            $nav,
            $single ? 'whats-on whats-on--single' : 'whats-on'
        );
    }

    private function slide(EventCard $event, CarouselOptions $options, int $index, int $total): string
    {
        $heading = $options->headingLevel;

        return sprintf(
            '<li class="whats-on__slide" role="group" aria-roledescription="%1$s" aria-label="%2$s">'
                . '<a class="whats-on__card" href="%3$s">'
                    . '%4$s'
                    . '<div class="whats-on__body">'
                        . '<%5$s class="whats-on__title">%6$s</%5$s>'
                        . '<p class="whats-on__date"><time datetime="%7$s">%8$s</time></p>'
                    . '</div>'
                . '</a>'
            . '</li>',
            esc_attr__('slide', 'whats-on'),
            /* translators: 1: slide number, 2: number of slides */
            esc_attr(sprintf(__('%1$d of %2$d', 'whats-on'), $index + 1, $total)),
            esc_url($event->url),
            $this->image($event),
            $heading,
            esc_html($event->title),
            esc_attr($event->start->format(DATE_ATOM)),
            esc_html($this->dates->format($event))
        );
    }

    private function image(EventCard $event): string
    {
        if (null === $event->image) {
            return '<div class="whats-on__image whats-on__image--placeholder" aria-hidden="true"></div>';
        }

        $image = $event->image;
        $attrs = sprintf(
            'src="%s" alt="%s" loading="lazy" decoding="async"',
            esc_url($image->src),
            esc_attr($image->alt)
        );
        if ('' !== $image->srcset) {
            $attrs .= sprintf(' srcset="%s" sizes="%s"', esc_attr($image->srcset), esc_attr($image->sizes));
        }
        if ($image->width > 0 && $image->height > 0) {
            $attrs .= sprintf(' width="%d" height="%d"', $image->width, $image->height);
        }

        return '<div class="whats-on__image"><img ' . $attrs . ' /></div>';
    }

    private function nav(): string
    {
        $chevron = '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M15.4 5.4 14 4l-8 8 8 8 1.4-1.4L8.8 12z" fill="currentColor"/></svg>';

        return sprintf(
            '<button type="button" class="whats-on__nav whats-on__nav--prev" data-whats-on-prev aria-label="%1$s">%3$s</button>'
            . '<button type="button" class="whats-on__nav whats-on__nav--next" data-whats-on-next aria-label="%2$s">%3$s</button>',
            esc_attr__('Previous events', 'whats-on'),
            esc_attr__('Next events', 'whats-on'),
            $chevron
        );
    }
}
