<?php

declare(strict_types=1);

// PHPUnit bootstrap.
//
// The WordPress surface What's On touches (options, post meta, permalinks,
// thumbnails and attachments, escaping, i18n, wp_date, wp_timezone) comes from
// bleedingdeacons/wp-mocks, the suite's shared test doubles, rather than being
// stubbed by hand in each test. Tests seed WpState and assert on the result.
//
// wp-mocks' bootstrap loads Patchwork before anything patchable, so anything
// below that defines WordPress functions or classes of its own must stay after
// the Bootstrap::load() call, not before it.
//
// Only the `wordpress` group is loaded: the REST controller is exercised on the
// dev site rather than here, and What's On uses no ACF and no Sentinel logger.

use BleedingDeacons\WpMocks\Bootstrap;
use BleedingDeacons\WpMocks\WpState;

require_once dirname(__DIR__) . '/vendor/autoload.php';

Bootstrap::load(['wordpress']);

// Makes plugins_url()/plugin_dir_url() answer with What's On's own path.
WpState::$pluginSlug = 'whats-on';

// Every source file begins with `if (!defined('ABSPATH')) { exit; }`, so the
// constant has to exist before any of them is loaded.
if (!defined('ABSPATH')) {
    define('ABSPATH', sys_get_temp_dir() . '/whats-on-test-wp/');
}
