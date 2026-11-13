<?php
/**
 * Plugin Name:       What's On
 * Description:       Divi 5 module that shows upcoming events from The Events Calendar as a carousel of cards (featured image, title, date and time) with previous / next buttons.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Requires Plugins:  the-events-calendar
 * GitHub Plugin URI: https://github.com/bleedingdeacons/whats-on
 * GitHub Branch:     main
 * Author:            The Bleeding Deacons
 * Author URI:        https://github.com/bleedingdeacons/whats-on
 * Contact:           thebleedingdeacons@gmail.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       whats-on
 * Domain Path:       /languages
 *
 * @package WhatsOn
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Kill switch: define( 'WHATS_ON_KILL', true ); in wp-config.php to stand the plugin down without deactivating it.
if ( defined( 'WHATS_ON_KILL' ) && WHATS_ON_KILL ) {
	return;
}

define( 'WHATS_ON_VERSION', '0.1.0' );
define( 'WHATS_ON_FILE', __FILE__ );
define( 'WHATS_ON_DIR', plugin_dir_path( __FILE__ ) );
define( 'WHATS_ON_URL', plugin_dir_url( __FILE__ ) );

// Composer autoloader when present (dev checkouts), otherwise a minimal PSR-4 loader so a
// release zip does not need vendor/.
if ( is_readable( WHATS_ON_DIR . 'vendor/autoload.php' ) ) {
	require_once WHATS_ON_DIR . 'vendor/autoload.php';
} else {
	spl_autoload_register(
		static function ( string $class ): void {
			if ( ! str_starts_with( $class, 'WhatsOn\\' ) ) {
				return;
			}
			$file = WHATS_ON_DIR . 'src/' . str_replace( '\\', '/', substr( $class, strlen( 'WhatsOn\\' ) ) ) . '.php';
			if ( is_readable( $file ) ) {
				require_once $file;
			}
		}
	);
}

( new WhatsOn\Plugin() )->boot();
