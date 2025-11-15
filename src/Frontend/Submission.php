<?php
/**
 * Submission Class
 *
 * Handles form submission, validation, and saving.
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
 * Submission class.
 */
class Submission {

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
		// AJAX handler for form submission.
		add_action( 'wp_ajax_fta_submit_form', [ $this, 'ajax_submit_form' ] );
		add_action( 'wp_ajax_nopriv_fta_submit_form', [ $this, 'ajax_submit_form' ] );
	}

	/**
	 * AJAX handler for form submission.
	 *
	 * @since 1.0.0
	 */
	public function ajax_submit_form() {
		// Verify nonce.
		check_ajax_referer( 'formtura_frontend', 'nonce' );

		// Get form ID.
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

		if ( ! $form_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid form ID.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Get form.
		$form = fta_get_form( $form_id );

		if ( ! $form ) {
			wp_send_json_error( [
				'message' => __( 'Form not found.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Check if form is active.
		if ( isset( $form['status'] ) && $form['status'] !== 'active' ) {
			wp_send_json_error( [
				'message' => __( 'This form is currently inactive.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Validate reCAPTCHA if enabled.
		if ( ! $this->validate_recaptcha() ) {
			wp_send_json_error( [
				'message' => __( 'reCAPTCHA verification failed. Please try again.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Validate and sanitize form data.
		$validation_result = $this->validate_submission( $form, $_POST );

		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error( [
				'message' => $validation_result->get_error_message(),
				'errors'  => $validation_result->get_error_data(),
			] );
		}

		// Sanitize submission data.
		$entry_data = $this->sanitize_submission( $form, $_POST );

		// Save entry to database.
		$entry_id = fta_create_entry( [
			'form_id'    => $form_id,
			'data'       => $entry_data,
			'ip_address' => $this->get_user_ip(),
			'user_agent' => $this->get_user_agent(),
			'created_at' => current_time( 'mysql' ),
		] );

		if ( ! $entry_id ) {
			wp_send_json_error( [
				'message' => __( 'Failed to save form submission. Please try again.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Send notifications.
		do_action( 'fta_after_form_submission', $entry_id, $form, $entry_data );

		// Get success message and redirect URL.
		$success_message = isset( $form['settings']['success_message'] )
			? $form['settings']['success_message']
			: __( 'Thank you! Your form has been submitted successfully.', FORMTURA_TEXTDOMAIN );

		$redirect_url = isset( $form['settings']['redirect_url'] ) ? $form['settings']['redirect_url'] : '';

		wp_send_json_success( [
			'message'      => $success_message,
			'redirect_url' => $redirect_url,
			'entry_id'     => $entry_id,
		] );
	}

	/**
	 * Validate form submission.
	 *
	 * @since 1.0.0
	 * @param array $form Form data.
	 * @param array $submission Submitted data.
	 * @return true|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_submission( $form, $submission ) {
		$errors = [];

		if ( ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return new \WP_Error( 'invalid_form', __( 'Invalid form configuration.', FORMTURA_TEXTDOMAIN ) );
		}

		foreach ( $form['fields'] as $field ) {
			$field_name = isset( $field['name'] ) ? $field['name'] : '';
			$field_value = isset( $submission[ $field_name ] ) ? $submission[ $field_name ] : '';

			// Required field validation.
			if ( ! empty( $field['required'] ) && empty( $field_value ) ) {
				$errors[ $field_name ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', FORMTURA_TEXTDOMAIN ),
					$field['label']
				);
				continue;
			}

			// Skip validation if field is empty and not required.
			if ( empty( $field_value ) ) {
				continue;
			}

			// Type-specific validation.
			$validation_result = $this->validate_field_type( $field_value, $field );

			if ( is_wp_error( $validation_result ) ) {
				$errors[ $field_name ] = $validation_result->get_error_message();
			}

			// Custom validation rules.
			if ( isset( $field['validation'] ) && is_array( $field['validation'] ) ) {
				$custom_validation = fta_validate_field( $field_value, $field['validation'] );

				if ( $custom_validation !== true ) {
					$errors[ $field_name ] = $custom_validation;
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'validation_failed', __( 'Please correct the errors below.', FORMTURA_TEXTDOMAIN ), $errors );
		}

		return true;
	}

	/**
	 * Validate field based on type.
	 *
	 * @since 1.0.0
	 * @param mixed $value Field value.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error True if valid, WP_Error if invalid.
	 */
	private function validate_field_type( $value, $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		switch ( $type ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', FORMTURA_TEXTDOMAIN ) );
				}
				break;

			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return new \WP_Error( 'invalid_url', __( 'Please enter a valid URL.', FORMTURA_TEXTDOMAIN ) );
				}
				break;

			case 'number':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error( 'invalid_number', __( 'Please enter a valid number.', FORMTURA_TEXTDOMAIN ) );
				}
				break;
		}

		return true;
	}

	/**
	 * Sanitize form submission.
	 *
	 * @since 1.0.0
	 * @param array $form Form data.
	 * @param array $submission Submitted data.
	 * @return array Sanitized data.
	 */
	private function sanitize_submission( $form, $submission ) {
		$sanitized = [];

		if ( ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $sanitized;
		}

		foreach ( $form['fields'] as $field ) {
			$field_name = isset( $field['name'] ) ? $field['name'] : '';
			$field_type = isset( $field['type'] ) ? $field['type'] : 'text';
			$field_value = isset( $submission[ $field_name ] ) ? $submission[ $field_name ] : '';

			$sanitized[ $field_name ] = fta_sanitize_field( $field_value, $field_type );
		}

		return $sanitized;
	}

	/**
	 * Validate reCAPTCHA.
	 *
	 * @since 1.0.0
	 * @return bool True if valid or not enabled, false if invalid.
	 */
	private function validate_recaptcha() {
		$recaptcha_secret = fta_get_setting( 'recaptcha_secret_key', '' );

		// If reCAPTCHA is not configured, skip validation.
		if ( empty( $recaptcha_secret ) ) {
			return true;
		}

		$recaptcha_response = isset( $_POST['g-recaptcha-response'] ) ? $_POST['g-recaptcha-response'] : '';

		if ( empty( $recaptcha_response ) ) {
			return false;
		}

		// Verify with Google.
		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
			'body' => [
				'secret'   => $recaptcha_secret,
				'response' => $recaptcha_response,
				'remoteip' => $this->get_user_ip(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		return isset( $body['success'] ) && $body['success'] === true;
	}

	/**
	 * Get user IP address.
	 *
	 * @since 1.0.0
	 * @return string User IP address.
	 */
	private function get_user_ip() {
		$ip = '';

		if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return sanitize_text_field( $ip );
	}

	/**
	 * Get user agent.
	 *
	 * @since 1.0.0
	 * @return string User agent.
	 */
	private function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
	}
}
