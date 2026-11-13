<?php
/**
 * Wires the plugin into WordPress, Divi 5 and The Events Calendar.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn;

use WhatsOn\Events\EventRepository;

final class Plugin {

	public function boot(): void {
		add_action( 'init', [ $this, 'init' ] );
		add_action( 'rest_api_init', [ $this, 'registerRestRoutes' ] );

		// Divi 5: module registration (front end + builder) and the builder bundle.
		add_action( 'divi_module_library_modules_dependency_tree', [ $this, 'registerModules' ] );
		add_action( 'divi_visual_builder_assets_before_enqueue_scripts', [ Assets::class, 'registerBuilder' ] );

		add_action( 'admin_notices', [ $this, 'dependencyNotice' ] );

		/**
		 * Fires once What's On has hooked itself in.
		 */
		do_action( 'whats_on/loaded' );
	}

	public function init(): void {
		load_plugin_textdomain( 'whats-on', false, dirname( plugin_basename( WHATS_ON_FILE ) ) . '/languages' );
		Assets::registerFrontend();
	}

	public function registerRestRoutes(): void {
		( new Rest\EventsController() )->register();
	}

	/**
	 * @param object $dependencyTree ET\Builder\Framework\DependencyManagement\DependencyTree.
	 */
	public function registerModules( $dependencyTree ): void {
		$dependencyTree->add_dependency( new Divi\EventsCarouselModule() );
	}

	public function dependencyNotice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		$missing = [];
		if ( ! EventRepository::isCalendarActive() ) {
			$missing[] = 'The Events Calendar';
		}
		if ( ! function_exists( 'et_builder_d5_enabled' ) ) {
			$missing[] = 'Divi 5';
		}
		if ( [] === $missing ) {
			return;
		}

		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html(
				sprintf(
					/* translators: %s: list of missing plugins/themes */
					__( "What's On needs %s to be active.", 'whats-on' ),
					implode( ' and ', $missing )
				)
			)
		);
	}
}
