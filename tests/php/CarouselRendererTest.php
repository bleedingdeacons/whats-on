<?php

declare(strict_types=1);

use WhatsOn\Events\DateFormatter;
use WhatsOn\Events\EventCard;
use WhatsOn\Events\EventImage;
use WhatsOn\Render\CarouselOptions;
use WhatsOn\Render\CarouselRenderer;

function whatsOnCard(int $id, string $title, ?EventImage $image = null): EventCard
{
    $tz = new DateTimeZone('Europe/London');

    return new EventCard(
        $id,
        $title,
        "https://example.test/event/{$id}/",
        new DateTimeImmutable('2026-11-20 19:00', $tz),
        new DateTimeImmutable('2026-11-20 21:00', $tz),
        false,
        $image
    );
}

beforeEach(function (): void {
    $this->renderer = new CarouselRenderer(new DateFormatter('j M', 'H:i'));
});

it('renders one linked card per event with image, title and date', function (): void {
    $html = $this->renderer->render(
        [
            whatsOnCard(1, 'Quiz Night', new EventImage('https://example.test/q.jpg', 'Quiz', 'https://example.test/q.jpg 1024w', '33vw', 1024, 576)),
            whatsOnCard(2, 'Open Meeting'),
        ],
        new CarouselOptions()
    );

    expect(substr_count($html, 'class="whats-on__slide"'))->toBe(2)
        ->and($html)->toContain('<a class="whats-on__card" href="https://example.test/event/1/">')
        ->and($html)->toContain('<h3 class="whats-on__title">Quiz Night</h3>')
        ->and($html)->toContain('<time datetime="2026-11-20T19:00:00+00:00">20 Nov · 19:00 – 21:00</time>')
        ->and($html)->toContain('src="https://example.test/q.jpg" alt="Quiz" loading="lazy"')
        ->and($html)->toContain('width="1024" height="576"')
        ->and($html)->toContain('whats-on__image--placeholder')
        ->and($html)->toContain('aria-label="2 of 2"');
});

it('renders previous and next buttons unless turned off', function (): void {
    $events = [ whatsOnCard(1, 'A') ];

    expect($this->renderer->render($events, new CarouselOptions()))
        ->toContain('data-whats-on-prev')
        ->toContain('data-whats-on-next')
        ->and($this->renderer->render($events, new CarouselOptions(showArrows: false)))
        ->not->toContain('data-whats-on-prev');
});

it('carries loop and per-view settings to the front end', function (): void {
    $html = $this->renderer->render([ whatsOnCard(1, 'A') ], new CarouselOptions(loop: true, perViewDesktop: 4, perViewTablet: 2, perViewPhone: 1));

    expect($html)->toContain('data-loop="on"')
        ->toContain('style="--whats-on-per-view:4;--whats-on-per-view-tablet:2;--whats-on-per-view-phone:1"');
});

it('escapes event titles', function (): void {
    $html = $this->renderer->render([ whatsOnCard(1, '<script>alert(1)</script>') ], new CarouselOptions());

    expect($html)->not->toContain('<script>');
});

it('uses the chosen heading level', function (): void {
    $html = $this->renderer->render([ whatsOnCard(1, 'A') ], new CarouselOptions(headingLevel: 'h2'));

    expect($html)->toContain('<h2 class="whats-on__title">A</h2>');
});

it('shows a message when there are no events', function (): void {
    expect($this->renderer->render([], new CarouselOptions()))
        ->toContain('There are no upcoming events at the moment.')
        ->not->toContain('data-whats-on')
        ->and($this->renderer->render([], new CarouselOptions(noEventsText: 'Nothing on!')))
        ->toContain('Nothing on!');
});

it('parses carousel options from module attributes', function (): void {
    $options = CarouselOptions::fromAttrs(
        [
            'title'    => [ 'decoration' => [ 'font' => [ 'font' => [ 'desktop' => [ 'value' => [ 'headingLevel' => 'script' ] ] ] ] ] ],
            'carousel' => [
                'advanced' => [
                    'slidesPerView' => [ 'desktop' => [ 'value' => '5' ], 'phone' => [ 'value' => '9' ] ],
                    'loop'          => [ 'desktop' => [ 'value' => 'on' ] ],
                ],
            ],
            'arrows'   => [ 'advanced' => [ 'show' => [ 'desktop' => [ 'value' => 'off' ] ] ] ],
        ]
    );

    expect($options->headingLevel)->toBe('h3')
        ->and($options->loop)->toBeTrue()
        ->and($options->showArrows)->toBeFalse()
        ->and($options->perViewDesktop)->toBe(5)
        ->and($options->perViewTablet)->toBe(2)
        ->and($options->perViewPhone)->toBe(6);
});
