<?php

declare(strict_types=1);

// Pest configuration.
//
// Every test under tests/php runs on wp-mocks' TestCase, which runs Brain
// Monkey's setUp/tearDown and resets WpState between tests. A test placed
// anywhere else runs on plain PHPUnit and has to be named here if it touches
// WordPress.

use BleedingDeacons\WpMocks\TestCase;
use Brain\Monkey\Functions;

pest()
    ->extend(TestCase::class)
    ->beforeEach(function (): void {
        // wp-mocks' wp_date() formats with date(), in PHP's default zone, and
        // ignores its $timezone argument. Event dates are formatted in the
        // event's own zone, so that argument is the behaviour under test:
        // honour it, as WordPress does.
        Functions\when('wp_date')->alias(
            static fn (string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string =>
                (new DateTimeImmutable('@' . ($timestamp ?? time())))
                    ->setTimezone($timezone ?? wp_timezone())
                    ->format($format)
        );

        // Not among wp-mocks' stubs. WordPress's own default; tests that care set their own.
        Functions\when('get_locale')->justReturn('en_US');
    })
    ->in('php');
