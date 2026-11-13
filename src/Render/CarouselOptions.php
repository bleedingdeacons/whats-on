<?php
/**
 * How the carousel looks and behaves, parsed from module attributes.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn\Render;

use WhatsOn\Events\QueryOptions;

final class CarouselOptions {

	private const HEADING_LEVELS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ];
	private const MAX_PER_VIEW   = 6;

	public function __construct(
		public readonly string $headingLevel = 'h3',
		public readonly bool $loop = false,
		public readonly bool $showArrows = true,
		public readonly string $noEventsText = '',
		public readonly int $perViewDesktop = 3,
		public readonly int $perViewTablet = 2,
		public readonly int $perViewPhone = 1,
	) {}

	/**
	 * @param array<string, mixed> $attrs Module attributes.
	 */
	public static function fromAttrs( array $attrs ): self {
		$heading = strtolower( (string) ( $attrs['title']['decoration']['font']['font']['desktop']['value']['headingLevel'] ?? 'h3' ) );
		$perView = $attrs['carousel']['advanced']['slidesPerView'] ?? [];

		// Unset breakpoints fall back to the module's defaults (3 / 2 / 1), never wider than desktop.
		$desktop = self::perView( $perView['desktop']['value'] ?? null, 3 );
		$tablet  = self::perView( $perView['tablet']['value'] ?? null, min( $desktop, 2 ) );
		$phone   = self::perView( $perView['phone']['value'] ?? null, 1 );

		return new self(
			headingLevel: in_array( $heading, self::HEADING_LEVELS, true ) ? $heading : 'h3',
			loop: QueryOptions::parseBool( $attrs['carousel']['advanced']['loop']['desktop']['value'] ?? 'off' ),
			showArrows: QueryOptions::parseBool( $attrs['arrows']['advanced']['show']['desktop']['value'] ?? 'on' ),
			noEventsText: trim( (string) ( $attrs['events']['advanced']['noEventsText']['desktop']['value'] ?? '' ) ),
			perViewDesktop: $desktop,
			perViewTablet: $tablet,
			perViewPhone: $phone,
		);
	}

	/**
	 * Inline custom properties the stylesheet reads per breakpoint.
	 */
	public function styleAttribute(): string {
		return sprintf(
			'--whats-on-per-view:%d;--whats-on-per-view-tablet:%d;--whats-on-per-view-phone:%d',
			$this->perViewDesktop,
			$this->perViewTablet,
			$this->perViewPhone
		);
	}

	private static function perView( mixed $value, int $fallback ): int {
		if ( null === $value || '' === $value ) {
			return $fallback;
		}

		$number = (int) filter_var( (string) $value, FILTER_SANITIZE_NUMBER_INT );

		return max( 1, min( self::MAX_PER_VIEW, $number ) );
	}
}
