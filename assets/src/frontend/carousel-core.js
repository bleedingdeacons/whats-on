/**
 * What's On carousel behaviour, shared by the front end and the Visual Builder preview.
 *
 * The track is a horizontally scrolling list with CSS scroll-snap, so it also works by
 * touch/trackpad swipe and without JavaScript. This adds the previous / next buttons
 * (one card per click), keyboard arrows, loop-around, and auto scroll.
 *
 * Auto scroll (`data-autoplay` = milliseconds, 0 = off) always wraps from the last event
 * to the first, and pauses while the carousel is hovered or focused, or the tab is hidden.
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
		this.timer = 0;
		this.pausedBy = new Set();

		// A visitor's own step restarts the countdown, so the next card gets its full time.
		this.onPrev = () => this.step( -1 );
		this.onNext = () => this.step( 1 );
		this.onScroll = () => this.schedule();
		this.onKey = ( event ) => {
			if ( event.key === 'ArrowLeft' ) {
				event.preventDefault();
				this.step( -1 );
			} else if ( event.key === 'ArrowRight' ) {
				event.preventDefault();
				this.step( 1 );
			}
		};
		this.onEnter = () => this.pause( 'hover' );
		this.onLeave = () => this.resume( 'hover' );
		this.onFocusIn = () => this.pause( 'focus' );
		this.onFocusOut = ( event ) => {
			if ( ! this.root.contains( event.relatedTarget ) ) {
				this.resume( 'focus' );
			}
		};
		this.onVisibility = () =>
			document.hidden ? this.pause( 'hidden' ) : this.resume( 'hidden' );

		if ( ! this.track ) {
			return;
		}

		this.prev?.addEventListener( 'click', this.onPrev );
		this.next?.addEventListener( 'click', this.onNext );
		this.track.addEventListener( 'scroll', this.onScroll, { passive: true } );
		this.track.addEventListener( 'keydown', this.onKey );
		root.addEventListener( 'mouseenter', this.onEnter );
		root.addEventListener( 'mouseleave', this.onLeave );
		root.addEventListener( 'focusin', this.onFocusIn );
		root.addEventListener( 'focusout', this.onFocusOut );
		document.addEventListener( 'visibilitychange', this.onVisibility );

		if ( typeof ResizeObserver !== 'undefined' ) {
			this.resizeObserver = new ResizeObserver( () => this.schedule() );
			this.resizeObserver.observe( this.track );
		}

		root.whatsOnCarousel = this;
		this.update();
		this.start();
	}

	get loop() {
		return this.root.dataset.loop === 'on';
	}

	/** Milliseconds between automatic steps; 0 when auto scroll is off. */
	get interval() {
		const ms = parseInt( this.root.dataset.autoplay ?? '0', 10 );
		return Number.isFinite( ms ) && ms > 0 ? ms : 0;
	}

	/** (Re)start the auto scroll countdown, unless it is off or paused. */
	start() {
		this.stop();
		if ( ! this.track || ! this.interval || this.pausedBy.size > 0 ) {
			return;
		}
		this.timer = setInterval( () => {
			if ( this.maxScroll() > 2 ) {
				this.go( 1, true );
			}
		}, this.interval );
	}

	stop() {
		if ( this.timer ) {
			clearInterval( this.timer );
			this.timer = 0;
		}
	}

	pause( reason ) {
		this.pausedBy.add( reason );
		this.stop();
	}

	resume( reason ) {
		this.pausedBy.delete( reason );
		this.start();
	}

	/** A step the visitor asked for. */
	step( direction ) {
		this.go( direction );
		this.start();
	}

	/** Width of one card including the gap between cards. */
	stepWidth() {
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

	/**
	 * @param {1|-1}    direction
	 * @param {boolean} wrap Wrap at the ends even when Loop is off (auto scroll does).
	 */
	go( direction, wrap = false ) {
		const left = targetScroll( {
			scrollLeft: this.track.scrollLeft,
			maxScroll: this.maxScroll(),
			step: this.stepWidth(),
			direction,
			loop: this.loop || wrap,
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
		this.stop();
		this.root.removeEventListener( 'mouseenter', this.onEnter );
		this.root.removeEventListener( 'mouseleave', this.onLeave );
		this.root.removeEventListener( 'focusin', this.onFocusIn );
		this.root.removeEventListener( 'focusout', this.onFocusOut );
		document.removeEventListener( 'visibilitychange', this.onVisibility );
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
