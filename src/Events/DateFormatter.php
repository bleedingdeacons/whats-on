<?php

/**
 * Turns an event's start/end into the one line of text shown on its card.
 *
 * By default the line follows the site language's regional conventions, through
 * ICU (PHP's intl extension): the locale decides the order of day and month,
 * 12- or 24-hour time, abbreviations and punctuation. For English (UK):
 *
 *   Same day:      "Fri, 20 Nov 2026, 19:00–21:30"
 *   All day:       "Fri, 20 Nov 2026, all day"
 *   Multi-day:     "Sat, 21 Nov 2026, 19:00 – Mon, 23 Nov 2026, 16:00"
 *   Multi all-day: "Sat, 21 Nov 2026 – Mon, 23 Nov 2026"
 *   Time hidden:   date only (a range when the event spans days).
 *
 * and for English (US) "Fri, Nov 20, 2026, 7:00 PM–9:30 PM".
 *
 * Two cases use PHP date formats through wp_date() instead: a module that sets
 * its own Date Format, and a server without the intl extension, which falls
 * back to Settings → General. Those read "Fri 20 Nov 2026 · 7:00 pm – 9:30 pm".
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Events;

use DateTimeImmutable;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Throwable;
use WhatsOn\Support\Attrs;

final class DateFormatter
{
    // ICU skeletons: the fields wanted, not their order or punctuation, which
    // the locale supplies. j is the locale's preferred hour cycle (12 or 24).
    private const SKELETON_DATE      = 'EEEdMMMy';
    private const SKELETON_TIME      = 'jmm';
    private const SKELETON_DATE_TIME = 'EEEdMMMyjmm';

    /** @var array<string, IntlDatePatternGenerator> */
    private static array $generators = [];

    /**
     * @param string      $dateFormat PHP date format, used when $locale is null.
     * @param string      $timeFormat PHP time format, used when $locale is null.
     * @param string|null $locale     ICU locale (e.g. "en_GB"); null formats with the PHP formats.
     */
    public function __construct(
        private readonly string $dateFormat,
        private readonly string $timeFormat,
        private readonly bool $showTime = true,
        private readonly ?string $locale = null,
    ) {
    }

    /**
     * The site language's regional format, unless the module sets its own date
     * format or the server has no intl extension; then PHP formats, with the
     * site's Settings → General for whatever the module does not set.
     */
    public static function fromSettings(string $dateFormat = '', bool $showTime = true): self
    {
        $dateFormat = trim($dateFormat);
        $timeFormat = Attrs::string(get_option('time_format', 'g:i a'), 'g:i a');

        if ('' !== $dateFormat) {
            return new self($dateFormat, $timeFormat, $showTime);
        }

        $siteDate = Attrs::string(get_option('date_format', 'j F Y'), 'j F Y');
        $locale   = self::usableLocale(Attrs::string(get_locale(), 'en_US'));

        return new self($siteDate, $timeFormat, $showTime, $locale);
    }

    public function format(EventCard $event): string
    {
        return null === $this->locale ? $this->formatWithPhp($event) : $this->formatWithLocale($event, $this->locale);
    }

    private function formatWithLocale(EventCard $event, string $locale): string
    {
        $sameDay = $event->start->format('Y-m-d') === $event->end->format('Y-m-d');

        if ($event->allDay || ! $this->showTime) {
            $startDate = $this->icu($locale, self::SKELETON_DATE, $event->start);
            if ($sameDay) {
                return $event->allDay && $this->showTime
                    /* translators: %s: event date in the site's regional format */
                    ? sprintf(__('%s, all day', 'whats-on'), $startDate)
                    : $startDate;
            }

            return $startDate . ' – ' . $this->icu($locale, self::SKELETON_DATE, $event->end);
        }

        $start = $this->icu($locale, self::SKELETON_DATE_TIME, $event->start);

        if ($sameDay) {
            $startTime = $this->icu($locale, self::SKELETON_TIME, $event->start);
            $endTime   = $this->icu($locale, self::SKELETON_TIME, $event->end);

            // A time range within one day is closed up with an en dash, as CLDR's
            // interval formats do ("19:00–21:30").
            return $startTime === $endTime ? $start : $start . '–' . $endTime;
        }

        return $start . ' – ' . $this->icu($locale, self::SKELETON_DATE_TIME, $event->end);
    }

    private function formatWithPhp(EventCard $event): string
    {
        $sameDay = $event->start->format('Y-m-d') === $event->end->format('Y-m-d');

        $startDate = $this->php($event->start, $this->dateFormat);
        $endDate   = $this->php($event->end, $this->dateFormat);

        if ($event->allDay || ! $this->showTime) {
            if ($sameDay) {
                return $event->allDay && $this->showTime
                    /* translators: %s: event date */
                    ? sprintf(__('%s · All day', 'whats-on'), $startDate)
                    : $startDate;
            }

            return $startDate . ' – ' . $endDate;
        }

        $startTime = $this->php($event->start, $this->timeFormat);
        $endTime   = $this->php($event->end, $this->timeFormat);

        if ($sameDay) {
            if ($startTime === $endTime) {
                return $startDate . ' · ' . $startTime;
            }

            return $startDate . ' · ' . $startTime . ' – ' . $endTime;
        }

        return $startDate . ' ' . $startTime . ' – ' . $endDate . ' ' . $endTime;
    }

    /**
     * Localised formatting in the event's own timezone, with a PHP date format.
     */
    private function php(DateTimeImmutable $when, string $format): string
    {
        return (string) wp_date($format, $when->getTimestamp(), $when->getTimezone());
    }

    /**
     * Regional formatting in the event's own timezone, from an ICU skeleton.
     */
    private function icu(string $locale, string $skeleton, DateTimeImmutable $when): string
    {
        $pattern = self::generator($locale)->getBestPattern($skeleton);

        $formatter = new IntlDateFormatter(
            $locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::NONE,
            $when->getTimezone(),
            IntlDateFormatter::GREGORIAN,
            false === $pattern ? null : $pattern
        );

        $text = $formatter->format($when);

        return false === $text ? $when->format('Y-m-d H:i') : $text;
    }

    /**
     * The locale when the intl extension can format for it, otherwise null (PHP formats).
     */
    private static function usableLocale(string $locale): ?string
    {
        if (! class_exists(IntlDatePatternGenerator::class) || ! class_exists(IntlDateFormatter::class)) {
            return null;
        }

        try {
            self::generator($locale);
        } catch (Throwable) {
            return null;
        }

        return $locale;
    }

    private static function generator(string $locale): IntlDatePatternGenerator
    {
        return self::$generators[$locale] ??= new IntlDatePatternGenerator($locale);
    }
}
