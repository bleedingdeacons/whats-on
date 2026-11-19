// @vitest-environment jsdom
import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
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
function mount( { loop = 'off', autoplay = 0, slides = 6, slideWidth = 300, gap = 24, viewport = 948 } = {} ) {
	document.body.innerHTML = `
		<div class="whats-on" data-whats-on data-loop="${ loop }" data-autoplay="${ autoplay }">
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

	describe( 'auto scroll', () => {
		beforeEach( () => vi.useFakeTimers() );
		afterEach( () => vi.useRealTimers() );

		it( 'moves to the next event on every interval and wraps at the end', () => {
			const { root, track } = mount( { autoplay: 5000, slides: 3, slideWidth: 948, viewport: 948 } );
			new WhatsOnCarousel( root );

			vi.advanceTimersByTime( 4999 );
			expect( track.scrollLeft ).toBe( 0 );
			vi.advanceTimersByTime( 1 );
			expect( track.scrollLeft ).toBe( 972 );
			vi.advanceTimersByTime( 5000 );
			expect( track.scrollLeft ).toBe( 1944 );
			// Loop is off, but auto scroll still goes back to the first event.
			vi.advanceTimersByTime( 5000 );
			expect( track.scrollLeft ).toBe( 0 );
		} );

		it( 'does nothing when turned off', () => {
			const { root, track } = mount( { autoplay: 0 } );
			new WhatsOnCarousel( root );
			vi.advanceTimersByTime( 60000 );
			expect( track.scrollTo ).not.toHaveBeenCalled();
		} );

		it( 'pauses while hovered and resumes afterwards', () => {
			const { root, track } = mount( { autoplay: 5000 } );
			new WhatsOnCarousel( root );

			root.dispatchEvent( new MouseEvent( 'mouseenter' ) );
			vi.advanceTimersByTime( 20000 );
			expect( track.scrollLeft ).toBe( 0 );

			root.dispatchEvent( new MouseEvent( 'mouseleave' ) );
			vi.advanceTimersByTime( 5000 );
			expect( track.scrollLeft ).toBe( 324 );
		} );

		it( 'pauses while focus is inside the carousel', () => {
			const { root, track } = mount( { autoplay: 5000 } );
			new WhatsOnCarousel( root );

			track.dispatchEvent( new FocusEvent( 'focusin', { bubbles: true } ) );
			vi.advanceTimersByTime( 20000 );
			expect( track.scrollLeft ).toBe( 0 );

			track.dispatchEvent( new FocusEvent( 'focusout', { bubbles: true, relatedTarget: null } ) );
			vi.advanceTimersByTime( 5000 );
			expect( track.scrollLeft ).toBe( 324 );
		} );

		it( 'gives the event a visitor steps to its full time', () => {
			const { root, track, next } = mount( { autoplay: 5000 } );
			new WhatsOnCarousel( root );

			vi.advanceTimersByTime( 4000 );
			next.click();
			expect( track.scrollLeft ).toBe( 324 );
			vi.advanceTimersByTime( 4999 );
			expect( track.scrollLeft ).toBe( 324 );
			vi.advanceTimersByTime( 1 );
			expect( track.scrollLeft ).toBe( 648 );
		} );

		it( 'stops on destroy', () => {
			const { root, track } = mount( { autoplay: 5000 } );
			new WhatsOnCarousel( root ).destroy();
			vi.advanceTimersByTime( 20000 );
			expect( track.scrollTo ).not.toHaveBeenCalled();
		} );
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
