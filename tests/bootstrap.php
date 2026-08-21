<?php
/**
 * PHPUnit bootstrap file for Formtura plugin tests
 *
 * @package Formtura
 */

// Define test environment
define( 'FORMTURA_TESTS', true );

// Define WordPress constants (mock for unit tests).
//
// This must happen BEFORE the autoloader runs. Composer's `files` autoload
// pulls in src/Functions.php, which exits immediately when ABSPATH is
// undefined - taking the entire test run with it, silently and with status 0.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}

if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}

// Load Composer autoloader
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load WordPress function stubs before anything that calls them.
require_once __DIR__ . '/wp-stubs.php';

// Load global helper functions (not Composer-autoloaded - see formtura.php).
require_once dirname( __DIR__ ) . '/src/Functions.php';

// Define plugin constants
define( 'FORMTURA_VERSION', '1.0.9' );
define( 'FORMTURA_PLUGIN_FILE', dirname( __DIR__ ) . '/formtura.php' );
define( 'FORMTURA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
define( 'FORMTURA_PLUGIN_URL', 'https://example.com/wp-content/plugins/formtura/' );
define( 'FORMTURA_PLUGIN_BASENAME', 'formtura/formtura.php' );
define( 'FORMTURA_TEXTDOMAIN', 'formtura' );

// Load test base class
require_once __DIR__ . '/TestCase.php';
