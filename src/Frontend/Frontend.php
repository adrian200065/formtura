<?php
/**
 * Frontend Class
 *
 * Handles front-facing form display and functionality.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Frontend class.
 */
class Frontend {

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
		// Register shortcode.
		add_shortcode( FORMTURA_TEXTDOMAIN, [ $this, 'render_form_shortcode' ] );

		// Enqueue frontend scripts and styles.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	/**
	 * Initialize frontend components.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		new Submission();
		new Notifications();
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		// Only enqueue if there's a form on the page.
		if ( ! $this->has_form_on_page() ) {
			return;
		}

		// Enqueue frontend CSS.
		if ( fta_get_setting( 'load_css', true ) ) {
			wp_enqueue_style(
				'formtura-frontend',
				FORMTURA_PLUGIN_URL . 'assets/css/frontend.css',
				[],
				FORMTURA_VERSION
			);
		}

		// Enqueue frontend JS.
		if ( fta_get_setting( 'load_js', true ) ) {
			wp_enqueue_script(
				'formtura-frontend',
				FORMTURA_PLUGIN_URL . 'assets/js/frontend.js',
				[ 'jquery' ],
				FORMTURA_VERSION,
				true
			);

			// Localize script.
			wp_localize_script(
				'formtura-frontend',
				'formturaFrontend',
				[
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce'   => wp_create_nonce( 'formtura_frontend' ),
					'strings' => [
						'submitting' => __( 'Submitting...', FORMTURA_TEXTDOMAIN ),
						'error'      => __( 'An error occurred. Please try again.', FORMTURA_TEXTDOMAIN ),
					],
				]
			);
		}

		// Enqueue reCAPTCHA if enabled.
		$recaptcha_site_key = fta_get_setting( 'recaptcha_site_key', '' );
		if ( ! empty( $recaptcha_site_key ) ) {
			$recaptcha_version = fta_get_setting( 'recaptcha_version', 'v2' );
			$recaptcha_url = $recaptcha_version === 'v3'
				? "https://www.google.com/recaptcha/api.js?render={$recaptcha_site_key}"
				: 'https://www.google.com/recaptcha/api.js';

			wp_enqueue_script(
				'google-recaptcha',
				$recaptcha_url,
				[],
				null,
				true
			);
		}
	}

	/**
	 * Check if the current page has a Formtura form.
	 *
	 * @since 1.0.0
	 * @return bool True if form exists on page.
	 */
	private function has_form_on_page() {
		global $post;

		if ( ! $post ) {
			return false;
		}

		// Check for shortcode.
		if ( has_shortcode( $post->post_content, FORMTURA_TEXTDOMAIN ) ) {
			return true;
		}

		// Check for Gutenberg block.
		if ( has_block( 'formtura/form-selector', $post ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Render form shortcode.
	 *
	 * @since 1.0.0
	 * @param array $atts Shortcode attributes.
	 * @return string Form HTML.
	 */
	public function render_form_shortcode( $atts ) {
		$atts = shortcode_atts( [
			'id' => 0,
		], $atts, FORMTURA_TEXTDOMAIN );

		$form_id = absint( $atts['id'] );

		if ( ! $form_id ) {
			return '<p>' . __( 'Please provide a valid form ID.', FORMTURA_TEXTDOMAIN ) . '</p>';
		}

		return fta_render_form( $form_id );
	}
}
