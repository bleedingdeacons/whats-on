<?php

declare(strict_types=1);

use Brain\Monkey\Functions;
use WhatsOn\Events\DateFormatter;
use WhatsOn\Events\EventCard;

covers(DateFormatter::class);

function whatsOnEvent(string $start, string $end, bool $allDay = false, string $zone = 'Europe/London'): EventCard
{
    $tz = new DateTimeZone($zone);

    return new EventCard(1, 'Event', 'https://example.test/event/', new DateTimeImmutable($start, $tz), new DateTimeImmutable($end, $tz), $allDay);
}

describe('regional format (the site locale, through ICU)', function (): void {
    beforeEach(function (): void {
        $this->uk = new DateFormatter('', '', true, 'en_GB');
    });

    it('formats a same-day event with a closed-up time range', function (): void {
        expect($this->uk->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))
            ->toBe('Fri, 20 Nov 2026, 19:00–21:30');
    });

    it('shows a single time when start and end are equal', function (): void {
        expect($this->uk->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 19:00')))
            ->toBe('Fri, 20 Nov 2026, 19:00');
    });

    it('labels all-day events', function (): void {
        expect($this->uk->format(whatsOnEvent('2026-11-20 00:00', '2026-11-20 23:59', true)))
            ->toBe('Fri, 20 Nov 2026, all day');
    });

    it('formats multi-day events as a range', function (): void {
        expect($this->uk->format(whatsOnEvent('2026-11-21 19:00', '2026-11-23 16:00')))
            ->toBe('Sat, 21 Nov 2026, 19:00 – Mon, 23 Nov 2026, 16:00')
            ->and($this->uk->format(whatsOnEvent('2026-11-21 00:00', '2026-11-23 23:59', true)))
            ->toBe('Sat, 21 Nov 2026 – Mon, 23 Nov 2026');
    });

    it('shows the date only when time is turned off', function (): void {
        $formatter = new DateFormatter('', '', false, 'en_GB');

        expect($formatter->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))->toBe('Fri, 20 Nov 2026')
            ->and($formatter->format(whatsOnEvent('2026-11-20 00:00', '2026-11-20 23:59', true)))->toBe('Fri, 20 Nov 2026');
    });

    it("follows the locale's own order and clock", function (): void {
        $us = (new DateFormatter('', '', true, 'en_US'))->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30'));
        $de = (new DateFormatter('', '', true, 'de_DE'))->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30'));

        // Month before day and a 12-hour clock in the US; day-dot-month and 24-hour in Germany.
        expect($us)->toStartWith('Fri, Nov 20, 2026, 7:00')->toContain('PM')
            ->and($de)->toBe('Fr., 20. Nov. 2026, 19:00–21:30');
    });

    it('formats in the event timezone, not the site one', function (): void {
        expect($this->uk->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:00', false, 'Asia/Tokyo')))
            ->toBe('Fri, 20 Nov 2026, 19:00–21:00');
    });
});

describe('PHP date formats (a module override, or no intl extension)', function (): void {
    beforeEach(function (): void {
        $this->formatter = new DateFormatter('D j M Y', 'g:i a');
    });

    it('formats a same-day event with a time range', function (): void {
        expect($this->formatter->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))
            ->toBe('Fri 20 Nov 2026 · 7:00 pm – 9:30 pm');
    });

    it('shows a single time when start and end are equal', function (): void {
        expect($this->formatter->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 19:00')))
            ->toBe('Fri 20 Nov 2026 · 7:00 pm');
    });

    it('labels all-day events', function (): void {
        expect($this->formatter->format(whatsOnEvent('2026-11-20 00:00', '2026-11-20 23:59', true)))
            ->toBe('Fri 20 Nov 2026 · All day');
    });

    it('formats multi-day events as a range', function (): void {
        expect($this->formatter->format(whatsOnEvent('2026-11-21 19:00', '2026-11-23 16:00')))
            ->toBe('Sat 21 Nov 2026 7:00 pm – Mon 23 Nov 2026 4:00 pm')
            ->and($this->formatter->format(whatsOnEvent('2026-11-21 00:00', '2026-11-23 23:59', true)))
            ->toBe('Sat 21 Nov 2026 – Mon 23 Nov 2026');
    });

    it('shows the date only when time is turned off', function (): void {
        $formatter = new DateFormatter('D j M Y', 'g:i a', false);

        expect($formatter->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))->toBe('Fri 20 Nov 2026');
    });

    it('formats in the event timezone, not the site one', function (): void {
        expect($this->formatter->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:00', false, 'Asia/Tokyo')))
            ->toBe('Fri 20 Nov 2026 · 7:00 pm – 9:00 pm');
    });
});

describe('fromSettings', function (): void {
    it("uses the site language's regional format by default, whatever Settings → General says", function (): void {
        Functions\when('get_locale')->justReturn('en_GB');
        update_option('date_format', 'Y-m-d');
        update_option('time_format', 'H:i');

        expect(DateFormatter::fromSettings()->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))
            ->toBe('Fri, 20 Nov 2026, 19:00–21:30');
    });

    it("uses the module's own date format when it sets one, with the site's time format", function (): void {
        Functions\when('get_locale')->justReturn('en_GB');
        update_option('time_format', 'H:i');

        expect(DateFormatter::fromSettings('j/n')->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))
            ->toBe('20/11 · 19:00 – 21:30');
    });
});
