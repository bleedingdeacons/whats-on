/**
 * What's On carousel behaviour, shared by the front end and the Visual Builder preview.
 *
 * The track is a horizontally scrolling list with CSS scroll-snap, so it also works by
 * touch/trackpad swipe and without JavaScript. This adds the previous / next buttons
 * (one card per click), keyboard arrows, and loop-around.
 */

/**
 * Where the track should scroll to for one step.
 *
 * @param {object}  p
 * @param {number}  p.scrollLeft Current scroll position.
 * @param {number}  p.maxScroll  scrollWidth - clientWidth.
 * @param {number}  p.step       Width of one card plus the gap.
 * @param {1|-1}    p.direction  1 = next, -1 = previous.
 * @param {boolean} p.loop       Wrap around at the ends.
 * @returns {number}
 */
export function targetScroll( { scrollLeft, maxScroll, step, direction, loop } ) {
	const EPSILON = 2;
	if ( maxScroll <= EPSILON || step <= 0 ) {
		return 0;
	}

	const atStart = scrollLeft <= EPSILON;
	const atEnd = scrollLeft >= maxScroll - EPSILON;

	if ( direction > 0 && atEnd ) {
		return loop ? 0 : maxScroll;
	}
	if ( direction < 0 && atStart ) {
		return loop ? maxScroll : 0;
	}

	// Snap to the card boundary, so repeated clicks never drift between cards.
	const index = Math.round( scrollLeft / step ) + direction;
	return Math.min( maxScroll, Math.max( 0, index * step ) );
}

/**
 * Which buttons are usable at a scroll position.
 *
 * @returns {{ prev: boolean, next: boolean, scrollable: boolean }}
 */
export function navState( { scrollLeft, maxScroll, loop } ) {
	const EPSILON = 2;
	const scrollable = maxScroll > EPSILON;
	if ( ! scrollable ) {
		return { prev: false, next: false, scrollable };
	}
	if ( loop ) {
		return { prev: true, next: true, scrollable };
	}
	return {
		prev: scrollLeft > EPSILON,
		next: scrollLeft < maxScroll - EPSILON,
		scrollable,
	};
}

export class WhatsOnCarousel {
	/**
	 * @param {HTMLElement} root Element carrying `data-whats-on`.
	 */
	constructor( root ) {
		this.root = root;
		this.track = root.querySelector( '.whats-on__track' );
		this.prev = root.querySelector( '[data-whats-on-prev]' );
		this.next = root.querySelector( '[data-whats-on-next]' );
		this.frame = 0;

		this.onPrev = () => this.go( -1 );
		this.onNext = () => this.go( 1 );
		this.onScroll = () => this.schedule();
		this.onKey = ( event ) => {
			if ( event.key === 'ArrowLeft' ) {
				event.preventDefault();
				this.go( -1 );
			} else if ( event.key === 'ArrowRight' ) {
				event.preventDefault();
				this.go( 1 );
			}
		};

		if ( ! this.track ) {
			return;
		}

		this.prev?.addEventListener( 'click', this.onPrev );
		this.next?.addEventListener( 'click', this.onNext );
		this.track.addEventListener( 'scroll', this.onScroll, { passive: true } );
		this.track.addEventListener( 'keydown', this.onKey );

		if ( typeof ResizeObserver !== 'undefined' ) {
			this.resizeObserver = new ResizeObserver( () => this.schedule() );
			this.resizeObserver.observe( this.track );
		}

		root.whatsOnCarousel = this;
		this.update();
	}

	get loop() {
		return this.root.dataset.loop === 'on';
	}

	/** Width of one card including the gap between cards. */
	step() {
		const slides = this.track.children;
		if ( slides.length === 0 ) {
			return 0;
		}
		if ( slides.length > 1 ) {
			const delta = slides[ 1 ].offsetLeft - slides[ 0 ].offsetLeft;
			if ( delta > 0 ) {
				return delta;
			}
		}
		return slides[ 0 ].offsetWidth;
	}

	maxScroll() {
		return Math.max( 0, this.track.scrollWidth - this.track.clientWidth );
	}

	go( direction ) {
		const left = targetScroll( {
			scrollLeft: this.track.scrollLeft,
			maxScroll: this.maxScroll(),
			step: this.step(),
			direction,
			loop: this.loop,
		} );

		const reduceMotion =
			typeof window.matchMedia === 'function' &&
			window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;

		if ( typeof this.track.scrollTo === 'function' ) {
			this.track.scrollTo( { left, behavior: reduceMotion ? 'auto' : 'smooth' } );
		} else {
			this.track.scrollLeft = left;
		}
		this.schedule();
	}

	schedule() {
		if ( this.frame ) {
			return;
		}
		const raf = window.requestAnimationFrame || ( ( fn ) => setTimeout( fn, 16 ) );
		this.frame = raf( () => {
			this.frame = 0;
			this.update();
		} );
	}

	update() {
		const state = navState( {
			scrollLeft: this.track.scrollLeft,
			maxScroll: this.maxScroll(),
			loop: this.loop,
		} );

		this.root.classList.toggle( 'whats-on--static', ! state.scrollable );
		if ( this.prev ) {
			this.prev.disabled = ! state.prev;
		}
		if ( this.next ) {
			this.next.disabled = ! state.next;
		}
	}

	destroy() {
		this.prev?.removeEventListener( 'click', this.onPrev );
		this.next?.removeEventListener( 'click', this.onNext );
		this.track?.removeEventListener( 'scroll', this.onScroll );
		this.track?.removeEventListener( 'keydown', this.onKey );
		this.resizeObserver?.disconnect();
		delete this.root.whatsOnCarousel;
	}
}

/**
 * Initialise every carousel under `scope` that has not been initialised yet.
 *
 * @param {ParentNode} scope
 * @returns {WhatsOnCarousel[]}
 */
export function initAll( scope = document ) {
	return Array.from( scope.querySelectorAll( '[data-whats-on]' ) )
		.filter( ( root ) => ! root.whatsOnCarousel )
		.map( ( root ) => new WhatsOnCarousel( root ) );
}
