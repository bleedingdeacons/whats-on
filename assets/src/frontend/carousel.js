/**
 * Front-end entry point: initialise carousels once the DOM is ready.
 */
import { initAll, WhatsOnCarousel } from './carousel-core';
import './carousel.css';

window.WhatsOn = { initAll, WhatsOnCarousel };

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => initAll() );
} else {
	initAll();
}
