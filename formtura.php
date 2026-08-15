<?php
/**
 * Plugin Name: Formtura
 * Plugin URI: https://formtura.com
 * Description: A modern, intuitive, and powerful form builder for WordPress with a beautiful drag-and-drop interface.
 * Version: 1.0.5
 * Author: Formtura Team
 * Author URI: https://formtura.com
 * Text Domain: formtura
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package Formtura
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'FORMTURA_VERSION', '1.0.5' );
define( 'FORMTURA_PLUGIN_FILE', __FILE__ );
define( 'FORMTURA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'FORMTURA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'FORMTURA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
define( 'FORMTURA_TEXTDOMAIN', 'formtura' );

// Require Composer autoloader.
//
// A missing autoloader means this is a source checkout without `composer
// install`, or an archive built from source rather than by
// scripts/build-release.sh. Every class below is autoloaded, so continuing
// would fatal on the first class reference and take the whole site down.
// Surface an administrator notice and stop instead.
if ( ! file_exists( FORMTURA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
	add_action( 'admin_notices', 'fta_missing_autoloader_notice' );

	/**
	 * Tell an administrator why the plugin is inert.
	 *
	 * @since 1.0.5
	 */
	function fta_missing_autoloader_notice() {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		echo '<div class="notice notice-error"><p><strong>Formtura</strong> could not start: its dependencies are missing. ';
		echo 'Run <code>composer install</code> in the plugin directory, or install an official release package built with <code>scripts/build-release.sh</code>. ';
		echo 'A source archive downloaded from the repository is not an installable package.</p></div>';
	}

	return;
}

require_once FORMTURA_PLUGIN_DIR . 'vendor/autoload.php';

// Load global helper functions.
//
// Deliberately required here rather than through Composer's `files` autoload:
// Functions.php exits when ABSPATH is undefined, which would abort any process
// that loads the autoloader outside WordPress - PHPUnit included.
require_once FORMTURA_PLUGIN_DIR . 'src/Functions.php';

/**
 * Initialize the plugin.
 *
 * @since 1.0.0
 */
function fta_init() {
	// Initialize the main plugin class.
	\Formtura\Core::instance();
}
add_action( 'plugins_loaded', 'fta_init' );

/**
 * Plugin activation hook.
 *
 * @since 1.0.0
 */
function fta_activate() {
	// Run activation tasks.
	if ( class_exists( '\Formtura\Database\Installer' ) ) {
		\Formtura\Database\Installer::activate();
	}
	
	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'fta_activate' );

/**
 * Plugin deactivation hook.
 *
 * @since 1.0.0
 */
function fta_deactivate() {
	// Flush rewrite rules.
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'fta_deactivate' );
