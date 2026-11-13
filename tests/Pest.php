<?php

declare(strict_types=1);

use Brain\Monkey;
use Brain\Monkey\Functions;

uses()
	->beforeEach( function (): void {
		Monkey\setUp();
		Functions\stubEscapeFunctions();
		Functions\stubTranslationFunctions();
		Functions\when( 'wp_timezone' )->alias( static fn () => new DateTimeZone( 'Europe/London' ) );
		// wp_date() is a localised date(); for tests, plain DateTime formatting in the given zone.
		Functions\when( 'wp_date' )->alias(
			static fn ( string $format, int $ts, ?DateTimeZone $tz = null ) => ( new DateTimeImmutable( '@' . $ts ) )
				->setTimezone( $tz ?? new DateTimeZone( 'UTC' ) )
				->format( $format )
		);
	} )
	->afterEach( function (): void {
		Monkey\tearDown();
	} )
	->in( 'php' );
