<?php
/**
 * One event, reduced to what a carousel card shows.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Events;

use DateTimeImmutable;

final class EventCard {

	public function __construct(
		public readonly int $id,
		public readonly string $title,
		public readonly string $url,
		public readonly DateTimeImmutable $start,
		public readonly DateTimeImmutable $end,
		public readonly bool $allDay = false,
		public readonly ?EventImage $image = null,
	) {}

	/**
	 * Shape returned by the REST endpoint the Visual Builder previews from.
	 *
	 * @return array<string, mixed>
	 */
	public function toArray( string $dateText ): array {
		return [
			'id'       => $this->id,
			'title'    => $this->title,
			'url'      => $this->url,
			'start'    => $this->start->format( DATE_ATOM ),
			'end'      => $this->end->format( DATE_ATOM ),
			'allDay'   => $this->allDay,
			'dateText' => $dateText,
			'image'    => $this->image?->toArray(),
		];
	}
}
