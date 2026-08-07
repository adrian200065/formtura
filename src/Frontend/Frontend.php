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
		// Submission and Notifications are constructed by Core so their AJAX
		// hooks are registered on admin-ajax.php requests too.
	}

	/**
	 * Enqueue frontend assets.
	 *
	 * @since 1.0.0
	 */
	public function enqueue_assets() {
		// Always enqueue the assets - they use delegated events so it's safe
		// even if no form is present. This fixes detection issues with
		// shortcodes in widgets, page builders, etc.

		$recaptcha = fta_get_recaptcha_config();
		$load_js   = fta_get_setting( 'load_js', true );

		// Enqueue frontend CSS.
		if ( fta_get_setting( 'load_css', true ) ) {
			wp_enqueue_style(
				'formtura-frontend',
				FORMTURA_PLUGIN_URL . 'assets/css/frontend.css',
				[],
				fta_asset_version( 'assets/css/frontend.css' )
			);
		}

		// Enqueue frontend JS.
		if ( $load_js ) {
			wp_enqueue_script(
				'formtura-frontend',
				FORMTURA_PLUGIN_URL . 'assets/js/frontend.js',
				[ 'jquery' ],
				fta_asset_version( 'assets/js/frontend.js' ),
				true
			);

			// Localize script.
			wp_localize_script(
				'formtura-frontend',
				'formturaFrontend',
				[
					'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
					'nonce'     => wp_create_nonce( 'formtura_frontend' ),
					'recaptcha' => $recaptcha['enabled']
						? [
							// Only the public half of the key pair goes to the browser.
							'siteKey' => $recaptcha['site_key'],
							'version' => $recaptcha['version'],
							'action'  => $recaptcha['action'],
						]
						: null,
					'currency'  => [
						'symbol' => fta_get_currency_symbol(),
					],
					'strings'   => [
						'submitting'       => __( 'Submitting...', FORMTURA_TEXTDOMAIN ),
						'error'            => __( 'An error occurred. Please try again.', FORMTURA_TEXTDOMAIN ),
						'recaptchaMissing' => __( 'Please confirm you are not a robot.', FORMTURA_TEXTDOMAIN ),
						'recaptchaError'   => __( 'reCAPTCHA could not be loaded. Please reload the page and try again.', FORMTURA_TEXTDOMAIN ),
						'signatureMissing' => __( 'Please add your signature.', FORMTURA_TEXTDOMAIN ),
						'couponApplied'    => __( 'Coupon applied.', FORMTURA_TEXTDOMAIN ),
						'couponInvalid'    => __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN ),
					],
				]
			);
		}

		// Enqueue reCAPTCHA if enabled. Google's API is only useful alongside our
		// own script, which is what drives the widget and the token request.
		if ( $recaptcha['enabled'] && $load_js ) {
			// v3 mints tokens on demand from the site key. v2 renders widgets
			// explicitly so each form's widget ID is known and can be reset
			// after a submission consumes its token.
			$recaptcha_url = 'v3' === $recaptcha['version']
				? add_query_arg( 'render', $recaptcha['site_key'], 'https://www.google.com/recaptcha/api.js' )
				: add_query_arg(
					[
						'render' => 'explicit',
						'onload' => 'formturaRecaptchaOnload',
					],
					'https://www.google.com/recaptcha/api.js'
				);

			wp_enqueue_script(
				'google-recaptcha',
				$recaptcha_url,
				// Depends on our script so the onload callback is defined by the
				// time Google's API runs it.
				[ 'formtura-frontend' ],
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
