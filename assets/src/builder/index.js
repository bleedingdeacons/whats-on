/**
 * Visual Builder entry point: registers the What's On module with Divi 5's module library.
 */
import { addAction } from '@wordpress/hooks';
import { registerModule } from '@divi/module-library';
import { eventsCarouselModule } from './events-carousel';

// Styles come from assets/build/carousel.css, which Assets::registerBuilder() loads into the builder.

addAction( 'divi.moduleLibrary.registerModuleLibraryStore.after', 'whatsOn', () => {
	const { metadata, ...rest } = eventsCarouselModule;
	registerModule( metadata, rest );
} );
