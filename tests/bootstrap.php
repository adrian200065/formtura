<?php
/**
 * PHPUnit bootstrap file for Formtura plugin tests
 *
 * @package Formtura
 */

// Define test environment
define( 'FORMTURA_TESTS', true );

// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Define WordPress constants (mock for unit tests)
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Define plugin constants
define( 'FORMTURA_VERSION', '1.0.2' );
define( 'FORMTURA_PLUGIN_FILE', dirname( __DIR__ ) . '/formtura.php' );
define( 'FORMTURA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'FORMTURA_PLUGIN_URL', 'https://example.com/wp-content/plugins/formtura/' );
define( 'FORMTURA_PLUGIN_BASENAME', 'formtura/formtura.php' );
define( 'FORMTURA_TEXTDOMAIN', 'formtura' );

// Load test base class
require_once __DIR__ . '/TestCase.php';
