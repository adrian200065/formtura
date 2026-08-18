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
		// Runs on plugins_loaded, before init and before any AJAX action, so
		// stored forms are migrated before anything can read or re-save them.
		Database\Installer::maybe_update();

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
		add_action( 'init', array( $this, 'load_textdomain' ) );

		// Plugin action links.
		add_filter( 'plugin_action_links_' . FORMTURA_PLUGIN_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Initialize plugin components.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		// Submission handling registers wp_ajax_ hooks, which only fire on
		// admin-ajax.php - where is_admin() is true. Registering it behind the
		// frontend check below would mean the handler is never attached to the
		// request that actually submits a form.
		new Frontend\Submission();
		new Frontend\Notifications();

		// The only browser route to a stored file. Registered unconditionally
		// because admin-post.php runs with is_admin() true, and the handler
		// must be attached to that request.
		( new Frontend\File_Download() )->register();

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

		// Registers WP Privacy API exporter/eraser hooks and the retention
		// purge cron callback - must run in both admin and frontend
		// contexts, like Frontend\Submission above.
		new Admin\Privacy();

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
		$plugin_links = array(
			'<a href="' . admin_url( 'admin.php?page=formtura' ) . '">Forms</a>',
			'<a href="' . admin_url( 'admin.php?page=formtura-settings' ) . '">Settings</a>',
		);

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
	 * @throws \Exception Always, to block unserializing this singleton.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton' );
	}
}
