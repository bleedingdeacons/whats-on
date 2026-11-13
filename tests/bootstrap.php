<?php
/**
 * Unit-test bootstrap: no WordPress, no Divi. WordPress functions are stubbed per test with
 * Brain Monkey; the few WordPress classes the code type-hints are declared here.
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! class_exists( 'WP_Post' ) ) {
	// phpcs:ignore Generic.Files.OneObjectStructurePerFile
	final class WP_Post {
		public int $ID = 0;
		public string $post_title = '';

		public function __construct( int $id = 0, string $title = '' ) {
			$this->ID         = $id;
			$this->post_title = $title;
		}
	}
}
