/**
 * Reads builder attributes into plain options. Mirrors src/Render/CarouselOptions.php and
 * src/Events/QueryOptions.php so the builder preview matches the front end.
 */

const HEADING_LEVELS = [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'p', 'div', 'span' ];

const desktop = ( attr ) => attr?.desktop?.value;

export const isOn = ( value, fallback = false ) => {
	if ( value === undefined || value === null || value === '' ) {
		return fallback;
	}
	if ( typeof value === 'boolean' ) {
		return value;
	}
	return [ 'on', '1', 'true', 'yes' ].includes( String( value ).toLowerCase() );
};

export const toInt = ( value, fallback, min, max ) => {
	if ( value === undefined || value === null || value === '' ) {
		return fallback;
	}
	const number = parseInt( String( value ).replace( /[^0-9-]/g, '' ), 10 );
	if ( Number.isNaN( number ) ) {
		return fallback;
	}
	return Math.min( max, Math.max( min, number ) );
};

export function builderOptions( attrs = {} ) {
	const events = attrs?.events?.advanced ?? {};
	const perView = attrs?.carousel?.advanced?.slidesPerView ?? {};

	const perViewDesktop = toInt( perView?.desktop?.value, 3, 1, 6 );
	const perViewTablet = toInt( perView?.tablet?.value, Math.min( perViewDesktop, 2 ), 1, 6 );
	const perViewPhone = toInt( perView?.phone?.value, 1, 1, 6 );

	const heading = String(
		attrs?.title?.decoration?.font?.font?.desktop?.value?.headingLevel ?? 'h3'
	).toLowerCase();

	return {
		count: toInt( desktop( events.count ), 9, 1, 50 ),
		categories: String( desktop( events.categories ) ?? '' ),
		featuredOnly: isOn( desktop( events.featuredOnly ) ),
		dateFormat: String( desktop( events.dateFormat ) ?? '' ),
		showTime: isOn( desktop( events.showTime ), true ),
		noEventsText: String( desktop( events.noEventsText ) ?? '' ).trim(),
		loop: isOn( desktop( attrs?.carousel?.advanced?.loop ) ),
		showArrows: isOn( desktop( attrs?.arrows?.advanced?.show ), true ),
		headingLevel: HEADING_LEVELS.includes( heading ) ? heading : 'h3',
		style: {
			'--whats-on-per-view': perViewDesktop,
			'--whats-on-per-view-tablet': perViewTablet,
			'--whats-on-per-view-phone': perViewPhone,
		},
	};
}

export function queryString( options ) {
	return new URLSearchParams( {
		count: String( options.count ),
		categories: options.categories,
		featuredOnly: options.featuredOnly ? 'on' : 'off',
		dateFormat: options.dateFormat,
		showTime: options.showTime ? 'on' : 'off',
	} ).toString();
}
