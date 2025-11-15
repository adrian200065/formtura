<?php
/**
 * Core Plugin Class
 *
 * Main plugin class that initializes all components.
 * Implemented as a Singleton pattern.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Core class.
 */
class Core {

	/**
	 * Single instance of the class.
	 *
	 * @var Core
	 */
	private static $instance = null;

	/**
	 * Admin instance.
	 *
	 * @var Admin\Admin
	 */
	public $admin;

	/**
	 * Frontend instance.
	 *
	 * @var Frontend\Frontend
	 */
	public $frontend;

	/**
	 * Database installer instance.
	 *
	 * @var Database\Installer
	 */
	public $installer;

	/**
	 * Get single instance of the class.
	 *
	 * @since 1.0.0
	 * @return Core
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Localization.
		add_action( 'init', [ $this, 'load_textdomain' ] );

		// Plugin action links.
		add_filter( 'plugin_action_links_' . FORMTURA_PLUGIN_BASENAME, [ $this, 'plugin_action_links' ] );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		// Initialize admin.
		if ( is_admin() ) {
			$this->admin = new Admin\Admin();
		}

		// Initialize frontend.
		if ( ! is_admin() ) {
			$this->frontend = new Frontend\Frontend();
		}

		// Initialize blocks (available in both admin and frontend).
		new Blocks\Form_Selector();

		// Initialize integrations.
		new Integrations\Integrations();
	}

	/**
	 * Load plugin textdomain for translations.
	 *
	 * @since 1.0.0
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'formtura',
			false,
			dirname( FORMTURA_PLUGIN_BASENAME ) . '/languages'
		);
	}

	/**
	 * Add plugin action links.
	 *
	 * @since 1.0.0
	 * @param array $links Existing action links.
	 * @return array Modified action links.
	 */
	public function plugin_action_links( $links ) {
		// Use plain strings to avoid translation loading before init hook.
		$plugin_links = [
			'<a href="' . admin_url( 'admin.php?page=formtura' ) . '">Forms</a>',
			'<a href="' . admin_url( 'admin.php?page=formtura-settings' ) . '">Settings</a>',
		];

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Prevent cloning of the instance.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}

	/**
	 * Prevent unserializing of the instance.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
