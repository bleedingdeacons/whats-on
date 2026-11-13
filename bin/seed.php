<?php
/**
 * Seeds the dev site with sample events and a "What's On" demo page.
 *
 * Run through WP-CLI (bin/dev.ps1 seed does this):
 *   wp eval-file bin/seed.php            create sample data (idempotent: replaces earlier samples)
 *   wp eval-file bin/seed.php reset      remove sample data only
 *
 * Everything created is tagged with the `_whats_on_sample` meta key, so reset never touches
 * real content.
 *
 * @package WhatsOn
 */

// No declare(strict_types=1): wp eval-file runs this through eval(), where it is a fatal error.

if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	exit( 'Run with wp eval-file.' );
}

if ( ! defined( 'WHATS_ON_SAMPLE_META' ) ) {
	define( 'WHATS_ON_SAMPLE_META', '_whats_on_sample' );
}

$whats_on_mode = $args[0] ?? 'seed';

if ( ! function_exists( 'tribe_events' ) ) {
	WP_CLI::error( 'The Events Calendar must be active.' );
}

/**
 * Delete every post and attachment previously created by this script.
 */
$whats_on_reset = static function (): void {
	$ids = get_posts(
		[
			'post_type'      => [ 'tribe_events', 'page', 'attachment' ],
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_key'       => WHATS_ON_SAMPLE_META, // phpcs:ignore WordPress.DB.SlowDBQuery
		]
	);
	foreach ( $ids as $id ) {
		if ( 'attachment' === get_post_type( $id ) ) {
			wp_delete_attachment( $id, true );
		} else {
			wp_delete_post( $id, true );
		}
	}
	WP_CLI::log( sprintf( 'Removed %d sample item(s).', count( $ids ) ) );
};

$whats_on_reset();
if ( 'reset' === $whats_on_mode ) {
	WP_CLI::success( 'Sample data removed.' );
	return;
}

/**
 * A 1200x675 JPEG with a colour band and the event title, attached as a featured image.
 */
$whats_on_image = static function ( string $title, array $rgb, int $post_id ): int {
	if ( ! function_exists( 'imagecreatetruecolor' ) ) {
		return 0;
	}
	$img   = imagecreatetruecolor( 1200, 675 );
	$bg    = imagecolorallocate( $img, $rgb[0], $rgb[1], $rgb[2] );
	$dark  = imagecolorallocate( $img, (int) ( $rgb[0] * 0.6 ), (int) ( $rgb[1] * 0.6 ), (int) ( $rgb[2] * 0.6 ) );
	$white = imagecolorallocate( $img, 255, 255, 255 );
	imagefilledrectangle( $img, 0, 0, 1200, 675, $bg );
	imagefilledellipse( $img, 1050, 120, 520, 520, $dark );
	imagefilledellipse( $img, 150, 640, 380, 380, $dark );

	// Built-in GD font scaled up via an intermediate image, so no TTF file is needed.
	$text  = strtoupper( $title );
	$small = imagecreatetruecolor( imagefontwidth( 5 ) * strlen( $text ) + 4, imagefontheight( 5 ) + 4 );
	imagefill( $small, 0, 0, $bg );
	imagecolortransparent( $small, $bg );
	imagestring( $small, 5, 2, 2, $text, $white );
	$scale = max( 1, min( 5, (int) floor( 1000 / imagesx( $small ) ) ) );
	$w     = imagesx( $small ) * $scale;
	$h     = imagesy( $small ) * $scale;
	imagecopyresized( $img, $small, (int) ( ( 1200 - $w ) / 2 ), (int) ( ( 675 - $h ) / 2 ), 0, 0, $w, $h, imagesx( $small ), imagesy( $small ) );

	$upload = wp_upload_dir();
	$file   = trailingslashit( $upload['path'] ) . 'whats-on-sample-' . sanitize_title( $title ) . '.jpg';
	imagejpeg( $img, $file, 85 );

	require_once ABSPATH . 'wp-admin/includes/image.php';
	$attachment_id = wp_insert_attachment(
		[
			'post_mime_type' => 'image/jpeg',
			'post_title'     => $title,
			'post_status'    => 'inherit',
		],
		$file,
		$post_id
	);
	wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );
	update_post_meta( $attachment_id, '_wp_attachment_image_alt', $title );
	update_post_meta( $attachment_id, WHATS_ON_SAMPLE_META, 1 );

	return (int) $attachment_id;
};

// Categories.
$whats_on_terms = [];
foreach ( [ 'meetings' => 'Meetings', 'social' => 'Social', 'workshops' => 'Workshops' ] as $slug => $name ) {
	$term = term_exists( $slug, 'tribe_events_cat' );
	if ( ! $term ) {
		$term = wp_insert_term( $name, 'tribe_events_cat', [ 'slug' => $slug ] );
	}
	$whats_on_terms[ $slug ] = (int) ( is_array( $term ) ? $term['term_id'] : $term );
}

// [ title, days from today, start, end, category, extra ] — times in the site timezone.
$whats_on_events = [
	[ 'Open Meeting', 2, '19:30', '21:00', 'meetings', [] ],
	[ 'Picnic in the Park', 5, '12:00', '16:00', 'social', [ 'featured' => true ] ],
	[ 'Newcomers Workshop', 8, '10:00', '13:00', 'workshops', [] ],
	[ 'Quiz Night', 11, '19:00', '22:30', 'social', [] ],
	[ 'Annual Convention', 15, '09:00', '17:00', 'meetings', [ 'days' => 2, 'featured' => true ] ],
	[ 'Volunteer Day', 19, '00:00', '23:59', 'workshops', [ 'all_day' => true ] ],
	[ 'Speaker Meeting', 22, '19:30', '21:00', 'meetings', [ 'no_image' => true ] ],
	[ 'Winter Social', 30, '18:00', '23:00', 'social', [] ],
	[ 'Last Month\'s Coffee Morning', -30, '10:00', '12:00', 'social', [] ], // Past: must never appear.
];

$whats_on_colours = [ [ 52, 101, 164 ], [ 46, 139, 87 ], [ 199, 107, 41 ], [ 142, 68, 173 ], [ 192, 57, 43 ], [ 22, 160, 133 ], [ 41, 128, 185 ], [ 211, 84, 0 ], [ 127, 140, 141 ] ];

$whats_on_tz = wp_timezone();
foreach ( $whats_on_events as $i => [ $title, $offset, $start, $end, $category, $extra ] ) {
	$day       = ( new DateTimeImmutable( 'today', $whats_on_tz ) )->modify( sprintf( '%+d days', $offset ) );
	$start_at  = $day->format( 'Y-m-d' ) . ' ' . $start . ':00';
	$end_at    = $day->modify( sprintf( '+%d days', ( $extra['days'] ?? 1 ) - 1 ) )->format( 'Y-m-d' ) . ' ' . $end . ':00';
	$all_day   = (bool) ( $extra['all_day'] ?? false );
	$event_arg = [
		'title'      => $title,
		'content'    => 'Sample event created by the What\'s On dev seed.',
		'status'     => 'publish',
		'start_date' => $start_at,
		'end_date'   => $end_at,
		'timezone'   => $whats_on_tz->getName(),
		'all_day'    => $all_day,
		'featured'   => (bool) ( $extra['featured'] ?? false ),
		'category'   => [ $whats_on_terms[ $category ] ],
	];

	$event = tribe_events()->set_args( $event_arg )->create();
	if ( ! $event instanceof WP_Post ) {
		WP_CLI::warning( "Could not create {$title}." );
		continue;
	}
	update_post_meta( $event->ID, WHATS_ON_SAMPLE_META, 1 );

	if ( empty( $extra['no_image'] ) ) {
		$image_id = $whats_on_image( $title, $whats_on_colours[ $i % count( $whats_on_colours ) ], $event->ID );
		if ( $image_id ) {
			set_post_thumbnail( $event->ID, $image_id );
		}
	}

	WP_CLI::log( sprintf( '  %-32s %s → %s%s', $title, $start_at, $end_at, $all_day ? ' (all day)' : '' ) );
}

// Demo page: one full-width row holding the module with its defaults.
$whats_on_version = defined( 'ET_BUILDER_PRODUCT_VERSION' ) ? ET_BUILDER_PRODUCT_VERSION : '5.0.0';
$whats_on_content = str_replace(
	'__V__',
	$whats_on_version,
	'<!-- wp:divi/placeholder -->'
	. '<!-- wp:divi/section {"builderVersion":"__V__"} -->'
	. '<!-- wp:divi/row {"module":{"advanced":{"columnStructure":{"desktop":{"value":"4_4"}},"flexColumnStructure":{"desktop":{"value":"equal-columns_1"}}},"decoration":{"layout":{"desktop":{"value":{"flexWrap":"nowrap"}}}}},"builderVersion":"__V__"} -->'
	. '<!-- wp:divi/column {"module":{"advanced":{"type":{"desktop":{"value":"4_4"}}},"decoration":{"sizing":{"desktop":{"value":{"flexType":"24_24"}}}}},"builderVersion":"__V__"} -->'
	. '<!-- wp:whats-on/events-carousel {"builderVersion":"__V__"} --><!-- /wp:whats-on/events-carousel -->'
	. '<!-- /wp:divi/column -->'
	. '<!-- /wp:divi/row -->'
	. '<!-- /wp:divi/section -->'
	. '<!-- /wp:divi/placeholder -->'
);

$whats_on_page = wp_insert_post(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => "What's On",
		'post_name'    => 'whats-on-demo',
		'post_content' => wp_slash( $whats_on_content ),
		'meta_input'   => [
			WHATS_ON_SAMPLE_META     => 1,
			'_et_pb_use_builder'     => 'on',
			'_et_pb_page_layout'     => 'et_full_width_page',
			'_et_pb_side_nav'        => 'off',
			'_et_pb_post_hide_nav'   => 'default',
		],
	],
	true
);

if ( is_wp_error( $whats_on_page ) ) {
	WP_CLI::error( $whats_on_page->get_error_message() );
}

WP_CLI::success( 'Sample events created. Demo page: ' . get_permalink( $whats_on_page ) );
