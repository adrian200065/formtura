<?php
/**
 * Settings Class
 *
 * Handles the main settings page.
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
 * Settings class.
 */
class Settings {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// AJAX handlers.
		add_action( 'wp_ajax_fta_save_settings', [ $this, 'ajax_save_settings' ] );
	}

	/**
	 * Render settings page.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		$settings = fta_get_setting();
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/settings.php';
	}

	/**
	 * AJAX handler to save settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_settings() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Get settings data.
		$settings = isset( $_POST['settings'] ) ? $_POST['settings'] : [];

		// Sanitize settings.
		$sanitized_settings = $this->sanitize_settings( $settings );

		// Save settings.
		$result = update_option( 'fta_settings', $sanitized_settings );

		if ( $result ) {
			wp_send_json_success( [
				'message' => __( 'Settings saved successfully.', FORMTURA_TEXTDOMAIN ),
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to save settings.', FORMTURA_TEXTDOMAIN ),
			] );
		}
	}

	/**
	 * Sanitize settings data.
	 *
	 * @since 1.0.0
	 * @param array $settings Settings data.
	 * @return array Sanitized settings.
	 */
	private function sanitize_settings( $settings ) {
		$sanitized = [];

		// General settings.
		if ( isset( $settings['license_key'] ) ) {
			$sanitized['license_key'] = sanitize_text_field( $settings['license_key'] );
		}

		if ( isset( $settings['load_css'] ) ) {
			$sanitized['load_css'] = (bool) $settings['load_css'];
		}

		if ( isset( $settings['load_js'] ) ) {
			$sanitized['load_js'] = (bool) $settings['load_js'];
		}

		if ( isset( $settings['debug_mode'] ) ) {
			$sanitized['debug_mode'] = (bool) $settings['debug_mode'];
		}

		// CAPTCHA settings.
		if ( isset( $settings['recaptcha_site_key'] ) ) {
			$sanitized['recaptcha_site_key'] = sanitize_text_field( $settings['recaptcha_site_key'] );
		}

		if ( isset( $settings['recaptcha_secret_key'] ) ) {
			$sanitized['recaptcha_secret_key'] = sanitize_text_field( $settings['recaptcha_secret_key'] );
		}

		if ( isset( $settings['recaptcha_version'] ) ) {
			$sanitized['recaptcha_version'] = in_array( $settings['recaptcha_version'], [ 'v2', 'v3' ], true ) ? $settings['recaptcha_version'] : 'v2';
		}

		// Currency settings.
		if ( isset( $settings['currency'] ) ) {
			$sanitized['currency'] = sanitize_text_field( $settings['currency'] );
		}

		// Uninstall settings.
		if ( isset( $settings['keep_data_on_uninstall'] ) ) {
			$sanitized['keep_data_on_uninstall'] = (bool) $settings['keep_data_on_uninstall'];
		}

		return $sanitized;
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array Default settings.
	 */
	public function get_defaults() {
		return [
			'license_key'            => '',
			'load_css'               => true,
			'load_js'                => true,
			'debug_mode'             => false,
			'recaptcha_site_key'     => '',
			'recaptcha_secret_key'   => '',
			'recaptcha_version'      => 'v2',
			'currency'               => 'USD',
			'keep_data_on_uninstall' => false,
		];
	}
}
