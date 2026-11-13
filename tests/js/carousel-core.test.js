// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { targetScroll, navState, WhatsOnCarousel, initAll } from '../../assets/src/frontend/carousel-core';

describe( 'targetScroll', () => {
	const base = { maxScroll: 900, step: 300, loop: false };

	it( 'moves one card forward and back', () => {
		expect( targetScroll( { ...base, scrollLeft: 0, direction: 1 } ) ).toBe( 300 );
		expect( targetScroll( { ...base, scrollLeft: 600, direction: -1 } ) ).toBe( 300 );
	} );

	it( 'snaps to card boundaries when part-way between cards', () => {
		expect( targetScroll( { ...base, scrollLeft: 290, direction: 1 } ) ).toBe( 600 );
		expect( targetScroll( { ...base, scrollLeft: 310, direction: -1 } ) ).toBe( 0 );
	} );

	it( 'clamps at the ends without loop', () => {
		expect( targetScroll( { ...base, scrollLeft: 900, direction: 1 } ) ).toBe( 900 );
		expect( targetScroll( { ...base, scrollLeft: 0, direction: -1 } ) ).toBe( 0 );
		expect( targetScroll( { ...base, maxScroll: 750, scrollLeft: 600, direction: 1 } ) ).toBe( 750 );
	} );

	it( 'wraps around with loop', () => {
		expect( targetScroll( { ...base, loop: true, scrollLeft: 900, direction: 1 } ) ).toBe( 0 );
		expect( targetScroll( { ...base, loop: true, scrollLeft: 0, direction: -1 } ) ).toBe( 900 );
	} );

	it( 'does nothing when everything fits', () => {
		expect( targetScroll( { ...base, maxScroll: 0, scrollLeft: 0, direction: 1 } ) ).toBe( 0 );
	} );
} );

describe( 'navState', () => {
	it( 'disables previous at the start and next at the end', () => {
		expect( navState( { scrollLeft: 0, maxScroll: 900, loop: false } ) ).toEqual( { prev: false, next: true, scrollable: true } );
		expect( navState( { scrollLeft: 900, maxScroll: 900, loop: false } ) ).toEqual( { prev: true, next: false, scrollable: true } );
		expect( navState( { scrollLeft: 450, maxScroll: 900, loop: false } ) ).toEqual( { prev: true, next: true, scrollable: true } );
	} );

	it( 'keeps both enabled with loop', () => {
		expect( navState( { scrollLeft: 0, maxScroll: 900, loop: true } ) ).toEqual( { prev: true, next: true, scrollable: true } );
	} );

	it( 'reports not scrollable when all cards fit', () => {
		expect( navState( { scrollLeft: 0, maxScroll: 0, loop: true } ) ).toEqual( { prev: false, next: false, scrollable: false } );
	} );
} );

/** jsdom has no layout, so give the track and slides fixed geometry. */
function mount( { loop = 'off', slides = 6, slideWidth = 300, gap = 24, viewport = 948 } = {} ) {
	document.body.innerHTML = `
		<div class="whats-on" data-whats-on data-loop="${ loop }">
			<div class="whats-on__viewport"><ul class="whats-on__track">
				${ Array.from( { length: slides }, ( _, i ) => `<li class="whats-on__slide"><a class="whats-on__card" href="/event/${ i }/">${ i }</a></li>` ).join( '' ) }
			</ul></div>
			<button data-whats-on-prev></button><button data-whats-on-next></button>
		</div>`;

	const root = document.querySelector( '[data-whats-on]' );
	const track = root.querySelector( '.whats-on__track' );
	Array.from( track.children ).forEach( ( slide, i ) => {
		Object.defineProperty( slide, 'offsetLeft', { value: i * ( slideWidth + gap ) } );
		Object.defineProperty( slide, 'offsetWidth', { value: slideWidth } );
	} );
	Object.defineProperty( track, 'clientWidth', { value: viewport } );
	Object.defineProperty( track, 'scrollWidth', { value: slides * slideWidth + ( slides - 1 ) * gap } );
	track.scrollTo = vi.fn( ( { left } ) => {
		track.scrollLeft = left;
	} );

	return { root, track, prev: root.querySelector( '[data-whats-on-prev]' ), next: root.querySelector( '[data-whats-on-next]' ) };
}

describe( 'WhatsOnCarousel', () => {
	beforeEach( () => {
		window.requestAnimationFrame = ( fn ) => {
			fn();
			return 1;
		};
		window.matchMedia = () => ( { matches: false } );
	} );

	it( 'starts with previous disabled and next enabled', () => {
		const { root, prev, next } = mount();
		new WhatsOnCarousel( root );
		expect( prev.disabled ).toBe( true );
		expect( next.disabled ).toBe( false );
	} );

	it( 'scrolls one card per click', () => {
		const { root, track, next, prev } = mount();
		new WhatsOnCarousel( root );

		next.click();
		expect( track.scrollTo ).toHaveBeenLastCalledWith( { left: 324, behavior: 'smooth' } );
		expect( prev.disabled ).toBe( false );

		prev.click();
		expect( track.scrollLeft ).toBe( 0 );
	} );

	it( 'loops from the last card back to the first', () => {
		const { root, track, next } = mount( { loop: 'on' } );
		new WhatsOnCarousel( root );
		track.scrollLeft = track.scrollWidth - track.clientWidth;

		next.click();
		expect( track.scrollLeft ).toBe( 0 );
	} );

	it( 'hides the buttons when every card fits', () => {
		const { root } = mount( { slides: 2 } );
		new WhatsOnCarousel( root );
		expect( root.classList.contains( 'whats-on--static' ) ).toBe( true );
	} );

	it( 'supports arrow keys on the track', () => {
		const { root, track } = mount();
		new WhatsOnCarousel( root );
		track.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight', bubbles: true } ) );
		expect( track.scrollLeft ).toBe( 324 );
	} );

	it( 'initialises each carousel once and cleans up on destroy', () => {
		const { root, track, next } = mount();
		expect( initAll() ).toHaveLength( 1 );
		expect( initAll() ).toHaveLength( 0 );

		root.whatsOnCarousel.destroy();
		next.click();
		expect( track.scrollTo ).not.toHaveBeenCalled();
	} );
} );
