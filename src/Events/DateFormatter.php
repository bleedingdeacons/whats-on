<?php
/**
 * Turns an event's start/end into the one line of text shown on its card.
 *
 *   Same day:      "Thu 20 Nov 2026 · 7:00 pm – 9:00 pm"
 *   All day:       "Thu 20 Nov 2026 · All day"
 *   Multi-day:     "Fri 21 Nov 2026 7:00 pm – Sun 23 Nov 2026 4:00 pm"
 *   Multi all-day: "Fri 21 Nov 2026 – Sun 23 Nov 2026"
 *   Time hidden:   date only (a range when the event spans days).
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Events;

use DateTimeImmutable;

final class DateFormatter {

	public function __construct(
		private readonly string $dateFormat,
		private readonly string $timeFormat,
		private readonly bool $showTime = true,
	) {}

	/**
	 * Use the site's own formats unless the module overrides the date format.
	 */
	public static function fromSettings( string $dateFormat = '', bool $showTime = true ): self {
		$dateFormat = trim( $dateFormat );

		return new self(
			'' !== $dateFormat ? $dateFormat : (string) get_option( 'date_format', 'j F Y' ),
			(string) get_option( 'time_format', 'g:i a' ),
			$showTime,
		);
	}

	public function format( EventCard $event ): string {
		$sameDay = $event->start->format( 'Y-m-d' ) === $event->end->format( 'Y-m-d' );

		$startDate = $this->date( $event->start, $this->dateFormat );
		$endDate   = $this->date( $event->end, $this->dateFormat );

		if ( $event->allDay || ! $this->showTime ) {
			if ( $sameDay ) {
				return $event->allDay && $this->showTime
					/* translators: %s: event date */
					? sprintf( __( '%s · All day', 'whats-on' ), $startDate )
					: $startDate;
			}

			return $startDate . ' – ' . $endDate;
		}

		$startTime = $this->date( $event->start, $this->timeFormat );
		$endTime   = $this->date( $event->end, $this->timeFormat );

		if ( $sameDay ) {
			if ( $startTime === $endTime ) {
				return $startDate . ' · ' . $startTime;
			}

			return $startDate . ' · ' . $startTime . ' – ' . $endTime;
		}

		return $startDate . ' ' . $startTime . ' – ' . $endDate . ' ' . $endTime;
	}

	/**
	 * Localised formatting in the event's own timezone.
	 */
	private function date( DateTimeImmutable $when, string $format ): string {
		return (string) wp_date( $format, $when->getTimestamp(), $when->getTimezone() );
	}
}
