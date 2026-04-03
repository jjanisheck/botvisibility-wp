<?php
/**
 * PHPUnit bootstrap file for BotVisibility tests.
 *
 * Loads the WordPress test framework and activates the plugin.
 */

// Load the WP test framework.
$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
    $_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
    echo "Could not find {$_tests_dir}/includes/functions.php. Have you run wp-env start?" . PHP_EOL;
    exit( 1 );
}

require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually activate the plugin during test setup.
 */
function _manually_load_plugin() {
    // Try the wp-env activated plugin path first.
    $paths = array(
        WP_PLUGIN_DIR . '/botvisibility/botvisibility.php',
        dirname( __DIR__ ) . '/botvisibility/botvisibility.php',
    );
    foreach ( $paths as $path ) {
        if ( file_exists( $path ) ) {
            require $path;
            return;
        }
    }
}
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Load the base test case.
require_once __DIR__ . '/class-botvis-test-case.php';
