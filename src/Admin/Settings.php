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
		add_action( 'wp_ajax_fta_save_settings', array( $this, 'ajax_save_settings' ) );
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
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'formtura' ),
				)
			);
		}

		// Get settings data.
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- every field is sanitized below in sanitize_settings().
		$settings = isset( $_POST['settings'] ) ? wp_unslash( $_POST['settings'] ) : array();

		// Sanitize settings, then merge onto the existing option rather than
		// replacing it outright - this form only has controls for a subset
		// of fta_settings, and a full replace would silently drop every key
		// it doesn't submit (currency, asset loading, debug, license, etc.).
		$existing           = fta_get_setting();
		$sanitized_settings = array_merge( $existing, $this->sanitize_settings( $settings ) );

		// update_option() returns false both on failure and when the new
		// value is identical to the old one - only skip the write (and still
		// report success) in the latter case.
		$result = $sanitized_settings === $existing ? true : update_option( 'fta_settings', $sanitized_settings );

		if ( $result ) {
			wp_send_json_success(
				array(
					'message' => __( 'Settings saved successfully.', 'formtura' ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to save settings.', 'formtura' ),
				)
			);
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
		$sanitized = array();

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
			$sanitized['recaptcha_version'] = in_array( $settings['recaptcha_version'], array( 'v2', 'v3' ), true ) ? $settings['recaptcha_version'] : 'v2';
		}

		if ( isset( $settings['recaptcha_score_threshold'] ) ) {
			$threshold = is_numeric( $settings['recaptcha_score_threshold'] ) ? (float) $settings['recaptcha_score_threshold'] : 0.5;

			$sanitized['recaptcha_score_threshold'] = max( 0.0, min( 1.0, $threshold ) );
		}

		// Automatic entry retention. 0 means "never delete automatically" -
		// the only value that must never change an existing install's
		// behavior on upgrade, so it is the default.
		if ( isset( $settings['entry_retention_days'] ) ) {
			$sanitized['entry_retention_days'] = max( 0, (int) $settings['entry_retention_days'] );
		}

		// Currency settings.
		if ( isset( $settings['currency'] ) ) {
			$sanitized['currency'] = sanitize_text_field( $settings['currency'] );
		}

		// Uninstall settings.
		//
		// Assigned unconditionally: an unchecked checkbox is absent from the
		// request, so a guarded assignment would leave a previously saved
		// `true` in place and make opting back out impossible.
		$sanitized['delete_data_on_uninstall'] = ! empty( $settings['delete_data_on_uninstall'] );

		return $sanitized;
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array Default settings.
	 */
	public function get_defaults() {
		return array(
			'license_key'               => '',
			'load_css'                  => true,
			'load_js'                   => true,
			'debug_mode'                => false,
			'recaptcha_site_key'        => '',
			'recaptcha_secret_key'      => '',
			'recaptcha_version'         => 'v2',
			'recaptcha_score_threshold' => 0.5,
			'currency'                  => 'USD',
			'entry_retention_days'      => 0,
			'delete_data_on_uninstall'  => false,
		);
	}
}
