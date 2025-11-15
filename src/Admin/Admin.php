<?php
/**
 * Admin Class
 *
 * Handles all WordPress admin functionality.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin class.
 */
class Admin {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_hooks();
		$this->init_components();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Admin menu.
		add_action( 'admin_menu', [ $this, 'register_menu' ] );

		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );

		// Admin notices.
		add_action( 'admin_notices', [ $this, 'admin_notices' ] );
	}

	/**
	 * Initialize admin components.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		new Form_Builder();
		new Form_Entries();
		new Form_Templates();
		new Settings();
		new SMTP();
	}

	/**
	 * Register admin menu.
	 *
	 * @since 1.0.0
	 */
	public function register_menu() {
		// Main menu.
		add_menu_page(
			__( 'Formtura', FORMTURA_TEXTDOMAIN ),
			__( 'Formtura', FORMTURA_TEXTDOMAIN ),
			'manage_options',
			'formtura',
			[ $this, 'render_forms_page' ],
			'dashicons-feedback',
			30
		);

		// Forms submenu (default).
		add_submenu_page(
			'formtura',
			__( 'All Forms', FORMTURA_TEXTDOMAIN ),
			__( 'All Forms', FORMTURA_TEXTDOMAIN ),
			'manage_options',
			'formtura',
			[ $this, 'render_forms_page' ]
		);

		// Add New Form.
		add_submenu_page(
			'formtura',
			__( 'Add New Form', FORMTURA_TEXTDOMAIN ),
			__( 'Add New', FORMTURA_TEXTDOMAIN ),
			'manage_options',
			'formtura-new',
			[ $this, 'render_new_form_page' ]
		);

		// Form Builder (hidden from menu).
		add_submenu_page(
			null, // Hidden from menu
			__( 'Form Builder', FORMTURA_TEXTDOMAIN ),
			__( 'Form Builder', FORMTURA_TEXTDOMAIN ),
			'manage_options',
			'formtura-builder',
			[ $this, 'render_builder_page' ]
		);

		// Entries.
		add_submenu_page(
			'formtura',
			__( 'Entries', FORMTURA_TEXTDOMAIN ),
			__( 'Entries', FORMTURA_TEXTDOMAIN ),
			'manage_options',
			'formtura-entries',
			[ $this, 'render_entries_page' ]
		);

		// Settings.
		add_submenu_page(
			'formtura',
			__( 'Settings', FORMTURA_TEXTDOMAIN ),
			__( 'Settings', FORMTURA_TEXTDOMAIN ),
			'manage_options',
			'formtura-settings',
			[ $this, 'render_settings_page' ]
		);

		// SMTP.
		add_submenu_page(
			'formtura',
			__( 'SMTP', FORMTURA_TEXTDOMAIN ),
			__( 'SMTP', FORMTURA_TEXTDOMAIN ),
			'manage_options',
			'formtura-smtp',
			[ $this, 'render_smtp_page' ]
		);
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( $hook ) {
		// Only load on Formtura pages.
		if ( strpos( $hook, 'formtura' ) === false ) {
			return;
		}

		// Enqueue admin CSS.
		wp_enqueue_style(
			'formtura-admin',
			FORMTURA_PLUGIN_URL . 'assets/css/admin.css',
			[],
			FORMTURA_VERSION
		);

		// Enqueue admin JS.
		wp_enqueue_script(
			'formtura-admin',
			FORMTURA_PLUGIN_URL . 'assets/js/admin.js',
			[ 'jquery' ],
			FORMTURA_VERSION,
			true
		);

		// Localize script.
		wp_localize_script(
			'formtura-admin',
			'formturaAdmin',
			[
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'formtura_admin' ),
				'strings' => [
					'confirmDelete' => __( 'Are you sure you want to delete this item?', FORMTURA_TEXTDOMAIN ),
					'saving'        => __( 'Saving...', FORMTURA_TEXTDOMAIN ),
					'saved'         => __( 'Saved!', FORMTURA_TEXTDOMAIN ),
					'error'         => __( 'An error occurred.', FORMTURA_TEXTDOMAIN ),
				],
			]
		);

		// Enqueue builder assets on builder page.
		$is_builder_page = ( 
			$hook === 'formtura_page_formtura-builder' || 
			( isset( $_GET['page'] ) && $_GET['page'] === 'formtura-builder' ) ||
			( isset( $_GET['action'] ) && $_GET['action'] === 'edit' )
		);
		
		if ( $is_builder_page ) {
			// Use file modification time for cache busting in development
			$css_file = FORMTURA_PLUGIN_DIR . 'assets/css/builder.css';
			$js_file  = FORMTURA_PLUGIN_DIR . 'assets/js/builder.js';
			$css_version = file_exists( $css_file ) ? filemtime( $css_file ) : FORMTURA_VERSION;
			$js_version  = file_exists( $js_file ) ? filemtime( $js_file ) : FORMTURA_VERSION;
			
			// Enqueue React builder CSS
			wp_enqueue_style(
				'formtura-builder',
				FORMTURA_PLUGIN_URL . 'assets/css/builder.css',
				[],
				$css_version
			);

			// Enqueue React builder JS
			wp_enqueue_script(
				'formtura-builder',
				FORMTURA_PLUGIN_URL . 'assets/js/builder.js',
				[],
				$js_version,
				true
			);
		}
	}

	/**
	 * Display admin notices.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		// Check if Composer dependencies are installed.
		if ( ! file_exists( FORMTURA_PLUGIN_DIR . 'vendor/autoload.php' ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					printf(
						/* translators: %s: command to run */
						__( 'Formtura requires Composer dependencies. Please run %s in the plugin directory.', FORMTURA_TEXTDOMAIN ),
						'<code>composer install</code>'
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Render forms list page.
	 *
	 * @since 1.0.0
	 */
	public function render_forms_page() {
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/forms-list.php';
	}

	/**
	 * Render new form page.
	 *
	 * @since 1.0.0
	 */
	public function render_new_form_page() {
		$templates = new Form_Templates();
		$templates->render();
	}

	/**
	 * Render entries page.
	 *
	 * @since 1.0.0
	 */
	public function render_entries_page() {
		$entries = new Form_Entries();
		$entries->render();
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		$settings = new Settings();
		$settings->render();
	}

	/**
	 * Render SMTP page.
	 *
	 * @since 1.0.0
	 */
	public function render_smtp_page() {
		$smtp = new SMTP();
		$smtp->render();
	}

	/**
	 * Render form builder page.
	 *
	 * @since 1.0.0
	 */
	public function render_builder_page() {
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
		$form = null;

		if ( $form_id ) {
			$form = fta_get_form( $form_id );
		}

		$builder = new Form_Builder();
		$builder->render( $form_id );
	}
}
