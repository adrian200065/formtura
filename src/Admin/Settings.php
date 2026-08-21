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
			$sanitized['recaptcha_secret_key'] = $this->encrypted_secret( $settings['recaptcha_secret_key'], 'recaptcha_secret_key' );
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

		// Same reasoning as delete_data_on_uninstall above.
		$sanitized['disable_default_css'] = ! empty( $settings['disable_default_css'] );

		if ( isset( $settings['from_email'] ) ) {
			$sanitized['from_email'] = sanitize_email( $settings['from_email'] );
		}

		if ( isset( $settings['from_name'] ) ) {
			$sanitized['from_name'] = sanitize_text_field( $settings['from_name'] );
		}

		// Anti-abuse settings.
		if ( isset( $settings['submission_rate_limit'] ) ) {
			$sanitized['submission_rate_limit'] = max( 0, (int) $settings['submission_rate_limit'] );
		}

		if ( isset( $settings['trusted_proxies'] ) ) {
			$sanitized['trusted_proxies'] = $this->sanitize_trusted_proxies( $settings['trusted_proxies'] );
		}

		return $sanitized;
	}

	/**
	 * Sanitize the trusted-proxy list.
	 *
	 * One IP or CIDR per line, invalid entries dropped rather than stored -
	 * Submission::get_user_ip() trusts every line here to decide whether
	 * X-Forwarded-For is honored at all, so a malformed entry must not
	 * silently become "trust everything."
	 *
	 * @since 1.0.9
	 * @param string $raw Raw textarea input.
	 * @return string Newline-separated list of valid entries.
	 */
	private function sanitize_trusted_proxies( $raw ) {
		$lines = preg_split( '/[\r\n]+/', (string) $raw );
		$valid = array();

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line ) {
				continue;
			}

			$address = explode( '/', $line, 2 )[0];

			if ( filter_var( $address, FILTER_VALIDATE_IP ) ) {
				$valid[] = $line;
			}
		}

		return implode( "\n", $valid );
	}

	/**
	 * Encrypt a submitted secret for storage, or keep the one already saved.
	 *
	 * The settings screen never re-renders a saved secret's real value (see
	 * the reCAPTCHA secret field in settings.php), so the form always
	 * resubmits it blank unless the administrator is actively changing it.
	 * Encrypting an empty submission would overwrite - and lose - a working
	 * credential every time any other field on the form is saved.
	 *
	 * @since 1.0.7
	 * @param mixed  $submitted Raw submitted value.
	 * @param string $key       Setting key, used to look up the current value.
	 * @return string
	 */
	private function encrypted_secret( $submitted, $key ) {
		$submitted = (string) $submitted;

		if ( '' === $submitted ) {
			return fta_get_setting( $key, '' );
		}

		return Secret_Crypto::encrypt( $submitted );
	}

	/**
	 * Get default settings.
	 *
	 * @since 1.0.0
	 * @return array Default settings.
	 */
	public function get_defaults() {
		return array(
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
			'submission_rate_limit'     => 10,
			'trusted_proxies'           => '',
		);
	}
}
