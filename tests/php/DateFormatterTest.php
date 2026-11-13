<?php

declare(strict_types=1);

use WhatsOn\Events\DateFormatter;
use WhatsOn\Events\EventCard;

function whatsOnEvent(string $start, string $end, bool $allDay = false): EventCard
{
    $tz = new DateTimeZone('Europe/London');

    return new EventCard(1, 'Event', 'https://example.test/event/', new DateTimeImmutable($start, $tz), new DateTimeImmutable($end, $tz), $allDay);
}

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

    expect($formatter->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))->toBe('Fri 20 Nov 2026')
        ->and($formatter->format(whatsOnEvent('2026-11-20 00:00', '2026-11-20 23:59', true)))->toBe('Fri 20 Nov 2026');
});

it("falls back to the site's date and time formats", function (): void {
    update_option('date_format', 'Y-m-d');
    update_option('time_format', 'H:i');

    expect(DateFormatter::fromSettings()->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))
        ->toBe('2026-11-20 · 19:00 – 21:30')
        ->and(DateFormatter::fromSettings('j/n')->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))
        ->toBe('20/11 · 19:00 – 21:30');
});

it('formats in the event timezone, not the site one', function (): void {
    $tokyo = new DateTimeZone('Asia/Tokyo');
    $event = new EventCard(1, 'E', 'https://example.test/', new DateTimeImmutable('2026-11-20 19:00', $tokyo), new DateTimeImmutable('2026-11-20 21:00', $tokyo));

    expect($this->formatter->format($event))->toBe('Fri 20 Nov 2026 · 7:00 pm – 9:00 pm');
});

it("uses the WordPress defaults when the site's formats are unset", function (): void {
    expect(DateFormatter::fromSettings()->format(whatsOnEvent('2026-11-20 19:00', '2026-11-20 21:30')))
        ->toBe('20 November 2026 · 7:00 pm – 9:30 pm');
});
