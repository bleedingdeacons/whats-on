<?php

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use WhatsOn\Events\EventRepository;
use WhatsOn\Events\QueryOptions;

it( 'asks for published events that have not ended, soonest first', function (): void {
	$args = ( new EventRepository() )->queryArgs( new QueryOptions( 5 ) );

	expect( $args )->toBe(
		[
			'post_status'    => 'publish',
			'posts_per_page' => 5,
			'ends_after'     => 'now',
			'orderby'        => 'event_date',
			'order'          => 'ASC',
		]
	);
} );

it( 'adds featured and category filters when set', function (): void {
	$args = ( new EventRepository() )->queryArgs( new QueryOptions( 3, [ 'social' ], true ) );

	expect( $args['featured'] )->toBeTrue()
		->and( $args['tax_query'] )->toBe(
			[
				[
					'taxonomy' => 'tribe_events_cat',
					'field'    => 'slug',
					'terms'    => [ 'social' ],
				],
			]
		);
} );

it( 'lets the query args be filtered', function (): void {
	Filters\expectApplied( 'whats_on/query_args' )->once()->andReturnUsing(
		static fn ( array $args ) => [ 'posts_per_page' => 1 ] + $args
	);

	expect( ( new EventRepository() )->queryArgs( new QueryOptions( 5 ) )['posts_per_page'] )->toBe( 1 );
} );

it( 'maps an event post to a card', function (): void {
	$meta = [
		'_EventStartDate'          => '2026-11-20 19:00:00',
		'_EventEndDate'            => '2026-11-20 21:00:00',
		'_EventAllDay'             => '',
		'_EventTimezone'           => 'Europe/London',
		'_wp_attachment_image_alt' => 'A crowd',
	];
	Functions\when( 'get_post_meta' )->alias( static fn ( $id, $key ) => $meta[ $key ] ?? '' );
	Functions\when( 'get_permalink' )->justReturn( 'https://example.test/event/quiz/' );
	Functions\when( 'get_the_title' )->justReturn( 'Quiz &amp; Chips' );
	Functions\when( 'get_post_thumbnail_id' )->justReturn( 77 );
	Functions\when( 'wp_get_attachment_image_src' )->justReturn( [ 'https://example.test/quiz.jpg', 1024, 576, true ] );
	Functions\when( 'wp_get_attachment_image_srcset' )->justReturn( 'https://example.test/quiz.jpg 1024w' );

	$card = ( new EventRepository() )->toCard( new WP_Post( 42 ) );

	expect( $card )->not->toBeNull()
		->and( $card->id )->toBe( 42 )
		->and( $card->title )->toBe( 'Quiz & Chips' )
		->and( $card->url )->toBe( 'https://example.test/event/quiz/' )
		->and( $card->start->format( 'Y-m-d H:i e' ) )->toBe( '2026-11-20 19:00 Europe/London' )
		->and( $card->end->format( 'H:i' ) )->toBe( '21:00' )
		->and( $card->allDay )->toBeFalse()
		->and( $card->image?->src )->toBe( 'https://example.test/quiz.jpg' )
		->and( $card->image?->alt )->toBe( 'A crowd' )
		->and( $card->image?->width )->toBe( 1024 );
} );

it( 'skips posts without a start date and handles missing images', function (): void {
	Functions\when( 'get_permalink' )->justReturn( 'https://example.test/' );
	Functions\when( 'get_the_title' )->justReturn( 'X' );
	Functions\when( 'get_post_thumbnail_id' )->justReturn( 0 );

	Functions\when( 'get_post_meta' )->justReturn( '' );
	expect( ( new EventRepository() )->toCard( new WP_Post( 1 ) ) )->toBeNull();

	Functions\when( 'get_post_meta' )->alias(
		static fn ( $id, $key ) => [ '_EventStartDate' => '2026-12-02 00:00:00', '_EventAllDay' => 'yes' ][ $key ] ?? ''
	);
	$card = ( new EventRepository() )->toCard( new WP_Post( 2 ) );

	expect( $card?->allDay )->toBeTrue()
		->and( $card?->image )->toBeNull()
		->and( $card?->end )->toEqual( $card?->start );
} );
