<?php

/**
 * Reads upcoming events from The Events Calendar.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Events;

use DateTimeImmutable;
use DateTimeZone;
use Throwable;
use WhatsOn\Support\Attrs;
use WP_Post;

class EventRepository
{
    public const POST_TYPE = 'tribe_events';
    public const TAXONOMY  = 'tribe_events_cat';

    public static function isCalendarActive(): bool
    {
        return function_exists('tribe_get_events');
    }

    /**
     * Events that have not yet ended, soonest first.
     *
     * @return list<EventCard>
     */
    public function upcoming(QueryOptions $options): array
    {
        if (! self::isCalendarActive()) {
            return [];
        }

        $posts = tribe_get_events($this->queryArgs($options));

        $cards = [];
        foreach (is_array($posts) ? $posts : [] as $post) {
            $card = $post instanceof WP_Post ? $this->toCard($post) : null;
            if (null !== $card) {
                $cards[] = $card;
            }
        }

        return $cards;
    }

    /**
     * Arguments for tribe_get_events(); public so the mapping is testable.
     *
     * @return array<string, mixed>
     */
    public function queryArgs(QueryOptions $options): array
    {
        $args = [
            'post_status'    => 'publish',
            'posts_per_page' => $options->count,
            'ends_after'     => 'now',
            'orderby'        => 'event_date',
            'order'          => 'ASC',
        ];

        if ($options->featuredOnly) {
            $args['featured'] = true;
        }

        if ([] !== $options->categories) {
            $args['tax_query'] = [
                [
                    'taxonomy' => self::TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $options->categories,
                ],
            ];
        }

        /**
         * Filters the arguments passed to tribe_get_events() for the What's On carousel.
         *
         * @param array<string, mixed> $args    Query arguments.
         * @param QueryOptions         $options Parsed module options.
         */
        return Attrs::map(apply_filters('whats_on/query_args', $args, $options));
    }

    public function toCard(WP_Post $post): ?EventCard
    {
        $timezone = $this->timezone(Attrs::string(get_post_meta($post->ID, '_EventTimezone', true)));

        $start = $this->parseDate(Attrs::string(get_post_meta($post->ID, '_EventStartDate', true)), $timezone);
        if (null === $start) {
            return null;
        }
        $end = $this->parseDate(Attrs::string(get_post_meta($post->ID, '_EventEndDate', true)), $timezone) ?? $start;

        $url = get_permalink($post);

        return new EventCard(
            id: $post->ID,
            title: html_entity_decode((string) get_the_title($post), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            url: is_string($url) ? $url : '',
            start: $start,
            end: $end,
            allDay: 'yes' === strtolower(Attrs::string(get_post_meta($post->ID, '_EventAllDay', true))),
            image: $this->image($post),
        );
    }

    private function image(WP_Post $post): ?EventImage
    {
        $attachmentId = (int) get_post_thumbnail_id($post);
        if ($attachmentId <= 0) {
            return null;
        }

        $size = Attrs::string(apply_filters('whats_on/image_size', 'large'), 'large');
        $src  = wp_get_attachment_image_src($attachmentId, $size);
        if (! is_array($src) || empty($src[0])) {
            return null;
        }

        $srcset = wp_get_attachment_image_srcset($attachmentId, $size);
        $alt    = Attrs::string(get_post_meta($attachmentId, '_wp_attachment_image_alt', true));

        return new EventImage(
            src: (string) $src[0],
            alt: $alt,
            srcset: is_string($srcset) ? $srcset : '',
            sizes: '(max-width: 767px) 100vw, (max-width: 980px) 50vw, 33vw',
            width: (int) ( $src[1] ?? 0 ),
            height: (int) ( $src[2] ?? 0 ),
        );
    }

    private function timezone(string $name): DateTimeZone
    {
        if ('' !== $name) {
            try {
                return new DateTimeZone($name);
            } catch (Throwable) {
                // Fall through to the site timezone.
            }
        }

        return wp_timezone();
    }

    private function parseDate(string $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ('' === $value) {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $value, $timezone);

        return false === $date ? null : $date;
    }
}
