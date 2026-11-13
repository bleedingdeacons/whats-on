<?php

declare(strict_types=1);

use BleedingDeacons\WpMocks\WpState;
use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WhatsOn\Events\EventRepository;
use WhatsOn\Events\QueryOptions;

covers(EventRepository::class);

/**
 * Seed an event the way The Events Calendar stores one: a post plus its meta.
 *
 * @param array<string, string> $meta
 */
function whatsOnSeedEvent(int $id, string $title, array $meta): WP_Post
{
    $post = WpState::addPost($id, [ 'post_title' => $title, 'post_type' => EventRepository::POST_TYPE ]);
    foreach ($meta as $key => $value) {
        update_post_meta($id, $key, $value);
    }

    return new WP_Post($post);
}

describe('queryArgs', function (): void {
    it('asks for published events that have not ended, soonest first', function (): void {
        expect(( new EventRepository() )->queryArgs(new QueryOptions(5)))->toBe(
            [
                'post_status'    => 'publish',
                'posts_per_page' => 5,
                'ends_after'     => 'now',
                'orderby'        => 'event_date',
                'order'          => 'ASC',
            ]
        );
    });

    it('adds featured and category filters when set', function (): void {
        $args = ( new EventRepository() )->queryArgs(new QueryOptions(3, [ 'social' ], true));

        expect($args['featured'])->toBeTrue()
            ->and($args['tax_query'])->toBe(
                [
                    [
                        'taxonomy' => 'tribe_events_cat',
                        'field'    => 'slug',
                        'terms'    => [ 'social' ],
                    ],
                ]
            );
    });

    it('lets the query args be filtered', function (): void {
        Filters\expectApplied('whats_on/query_args')->once()->andReturnUsing(
            static fn (array $args) => [ 'posts_per_page' => 1 ] + $args
        );

        expect(( new EventRepository() )->queryArgs(new QueryOptions(5))['posts_per_page'])->toBe(1);
    });
});

describe('toCard', function (): void {
    it('maps an event post to a card', function (): void {
        $post = whatsOnSeedEvent(42, 'Quiz &amp; Chips', [
            '_EventStartDate' => '2026-11-20 19:00:00',
            '_EventEndDate'   => '2026-11-20 21:00:00',
            '_EventAllDay'    => '',
            '_EventTimezone'  => 'Europe/London',
        ]);
        WpState::$thumbnails[42]  = 77;
        WpState::$attachments[77] = [ 'https://example.test/quiz.jpg', 1024, 576, true ];
        update_post_meta(77, '_wp_attachment_image_alt', 'A crowd');
        // Not one of wp-mocks' stubs: the only thing that needs it is this plugin.
        Functions\when('wp_get_attachment_image_srcset')->justReturn('https://example.test/quiz.jpg 1024w');

        $card = ( new EventRepository() )->toCard($post);

        expect($card)->not->toBeNull()
            ->and($card?->id)->toBe(42)
            ->and($card?->title)->toBe('Quiz & Chips')
            ->and($card?->url)->toBe(get_permalink(42))
            ->and($card?->start->format('Y-m-d H:i e'))->toBe('2026-11-20 19:00 Europe/London')
            ->and($card?->end->format('H:i'))->toBe('21:00')
            ->and($card?->allDay)->toBeFalse()
            ->and($card?->image?->src)->toBe('https://example.test/quiz.jpg')
            ->and($card?->image?->srcset)->toBe('https://example.test/quiz.jpg 1024w')
            ->and($card?->image?->alt)->toBe('A crowd')
            ->and($card?->image?->width)->toBe(1024)
            ->and($card?->image?->height)->toBe(576);
    });

    it('skips posts without a start date', function (): void {
        expect(( new EventRepository() )->toCard(whatsOnSeedEvent(1, 'No date', [])))->toBeNull();
    });

    it('handles all-day events, a missing end date and a missing image', function (): void {
        $card = ( new EventRepository() )->toCard(whatsOnSeedEvent(2, 'Volunteer Day', [
            '_EventStartDate' => '2026-12-02 00:00:00',
            '_EventAllDay'    => 'yes',
        ]));

        expect($card?->allDay)->toBeTrue()
            ->and($card?->image)->toBeNull()
            ->and($card?->end)->toEqual($card?->start);
    });

    it('falls back to the site timezone when the event has none, or an invalid one', function (): void {
        $repository = new EventRepository();

        $none    = $repository->toCard(whatsOnSeedEvent(3, 'A', [ '_EventStartDate' => '2026-11-20 19:00:00' ]));
        $invalid = $repository->toCard(whatsOnSeedEvent(4, 'B', [
            '_EventStartDate' => '2026-11-20 19:00:00',
            '_EventTimezone'  => 'Not/AZone',
        ]));

        expect($none?->start->getTimezone()->getName())->toBe(wp_timezone()->getName())
            ->and($invalid?->start->getTimezone()->getName())->toBe(wp_timezone()->getName());
    });
});

describe('upcoming', function (): void {
    it('maps what The Events Calendar returns and drops anything that is not an event post', function (): void {
        $event = whatsOnSeedEvent(5, 'Open Meeting', [ '_EventStartDate' => '2026-11-15 19:30:00' ]);
        $undated = whatsOnSeedEvent(6, 'Undated', []);
        Functions\expect('tribe_get_events')->once()->andReturn([ $event, 99, $undated ]);

        $cards = ( new EventRepository() )->upcoming(new QueryOptions(3));

        expect($cards)->toHaveCount(1)
            ->and($cards[0]->title)->toBe('Open Meeting');
    });

    it('passes the query args to The Events Calendar', function (): void {
        Functions\expect('tribe_get_events')
            ->once()
            ->with(Mockery::on(static fn (array $args): bool => 4 === $args['posts_per_page'] && 'now' === $args['ends_after']))
            ->andReturn([]);

        expect(( new EventRepository() )->upcoming(new QueryOptions(4)))->toBe([]);
    });
});
