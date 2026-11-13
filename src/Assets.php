<?php
/**
 * Script and style registration for the front end and the Divi Visual Builder.
 *
 * @package WhatsOn
 */

declare(strict_types=1);

namespace WhatsOn;

final class Assets {

	public const FRONTEND_HANDLE = 'whats-on-carousel';
	public const BUILDER_HANDLE  = 'whats-on-builder';

	/**
	 * Register (not enqueue) the front-end carousel; the module enqueues it when it renders.
	 */
	public static function registerFrontend(): void {
		wp_register_style(
			self::FRONTEND_HANDLE,
			WHATS_ON_URL . 'assets/build/carousel.css',
			[],
			self::version( 'assets/build/carousel.css' )
		);
		wp_register_script(
			self::FRONTEND_HANDLE,
			WHATS_ON_URL . 'assets/build/carousel.js',
			[],
			self::version( 'assets/build/carousel.js' ),
			[
				'in_footer' => true,
				'strategy'  => 'defer',
			]
		);
	}

	public static function enqueueFrontend(): void {
		if ( ! wp_script_is( self::FRONTEND_HANDLE, 'registered' ) ) {
			self::registerFrontend();
		}
		wp_enqueue_style( self::FRONTEND_HANDLE );
		wp_enqueue_script( self::FRONTEND_HANDLE );
	}

	/**
	 * Visual Builder bundle, loaded into the builder's app window only.
	 * Divi exposes the data as `window.WhatsOnBuilderData`.
	 */
	public static function registerBuilder(): void {
		if ( ! function_exists( 'et_core_is_fb_enabled' ) || ! et_core_is_fb_enabled() ) {
			return;
		}
		if ( ! class_exists( \ET\Builder\VisualBuilder\Assets\PackageBuildManager::class ) ) {
			return;
		}

		\ET\Builder\VisualBuilder\Assets\PackageBuildManager::register_package_build(
			[
				'name'    => self::BUILDER_HANDLE,
				'version' => self::version( 'assets/build/builder.js' ),
				'script'  => [
					'src'                => WHATS_ON_URL . 'assets/build/builder.js',
					'deps'               => [ 'divi-module-library', 'divi-module', 'divi-vendor-wp-hooks' ],
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
					'data_app_window'    => [
						'restUrl'          => esc_url_raw( rest_url( Rest\EventsController::NAMESPACE . '/events' ) ),
						'nonce'            => wp_create_nonce( 'wp_rest' ),
						'calendarActive'   => Events\EventRepository::isCalendarActive(),
					],
				],
				'style'   => [
					'src'                => WHATS_ON_URL . 'assets/build/carousel.css',
					'deps'               => [],
					'enqueue_top_window' => false,
					'enqueue_app_window' => true,
				],
			]
		);
	}

	/**
	 * Cache-bust with the file's mtime in development, the plugin version otherwise.
	 */
	private static function version( string $relative ): string {
		$path = WHATS_ON_DIR . $relative;
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_readable( $path ) ) {
			return WHATS_ON_VERSION . '.' . (string) filemtime( $path );
		}

		return WHATS_ON_VERSION;
	}
}
