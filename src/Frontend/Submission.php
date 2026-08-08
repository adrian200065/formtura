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

		// Coupon validation for display-side totals. The submission path
		// re-validates independently; this endpoint only prevents the page
		// from ever carrying the code list.
		add_action( 'wp_ajax_fta_validate_coupon', [ $this, 'ajax_validate_coupon' ] );
		add_action( 'wp_ajax_nopriv_fta_validate_coupon', [ $this, 'ajax_validate_coupon' ] );
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
		$recaptcha_result = $this->validate_recaptcha();

		if ( is_wp_error( $recaptcha_result ) ) {
			wp_send_json_error( [
				'message'   => $recaptcha_result->get_error_message(),
				'recaptcha' => $recaptcha_result->get_error_code(),
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

		// Payment forms carry an authoritative server-side computed order.
		// Computed here, before any files are stored: compute() only reads
		// $form and $_POST and never touches disk, so a forged item
		// selection or coupon code is rejected before process_files() below
		// moves any upload or writes any signature PNG. Running this after
		// process_files() (as originally placed) would let a visitor post a
		// forged selection alongside a real file repeatedly to leak stored
		// files - the same leak process_signatures() was written to avoid,
		// reintroduced one step later.
		$payments = new PaymentTotals();
		$payment  = null;

		if ( $payments->form_has_payment_fields( $form ) ) {
			$payment = $payments->compute( $form, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			if ( is_wp_error( $payment ) ) {
				wp_send_json_error( [
					'message' => $payment->get_error_message(),
					'errors'  => $payment->get_error_data(),
				] );
			}
		}

		// Store uploaded files and signatures. Runs after the rest of the form
		// validates so a rejected submission does not leave files behind.
		$files = $this->process_files( $form );

		if ( is_wp_error( $files ) ) {
			wp_send_json_error( [
				'message' => $files->get_error_message(),
				'errors'  => $files->get_error_data(),
			] );
		}

		// Sanitize submission data.
		$entry_data = $this->sanitize_submission( $form, $_POST );

		// File records are produced by process_files(), already sanitized.
		foreach ( $files as $field_name => $records ) {
			$entry_data[ $field_name ] = $records;
		}

		if ( null !== $payment ) {
			// Reserved key: field names are field_<timestamp>_<suffix>, so
			// _payment cannot collide with real field data. Assigned after
			// sanitize_submission() builds $entry_data so the server-computed
			// value always wins, regardless of anything sanitize_submission()
			// put there.
			$entry_data['_payment'] = $payment;
		}

		// Save entry to database.
		$entry_id = fta_create_entry( [
			'form_id'    => $form_id,
			'data'       => $entry_data,
			'ip_address' => $this->get_user_ip(),
			'user_agent' => $this->get_user_agent(),
			'created_at' => current_time( 'mysql' ),
		] );

		if ( ! $entry_id ) {
			// The files above are already on disk, and the entry that would
			// have referenced them does not exist - so nothing will ever read
			// or delete them. Without this, every failed entry write (a full
			// disk, a locked table) leaves a permanent orphan behind, which is
			// the same leak already closed for uploads-then-signature failures
			// in process_signatures() and for a rejected payment recompute.
			Uploads::cleanup( $files );

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
	 * AJAX: validate a coupon code for display-side totals.
	 *
	 * @since 1.0.4
	 */
	public function ajax_validate_coupon() {
		check_ajax_referer( 'formtura_frontend', 'nonce' );

		// A cheap per-IP throttle. This endpoint has no reCAPTCHA and creates
		// no entry, so it is a far cheaper oracle to sweep through candidate
		// codes with than an actual submission - checked before doing any
		// lookup work at all, regardless of which form/field/code is named.
		// Only failed attempts (below) count against the budget: a visitor
		// who applies a genuinely valid code, or who shares a NAT/CGNAT
		// egress IP with other visitors doing the same, must never be told a
		// working code "is not valid" just because the window filled up on
		// successes.
		if ( $this->coupon_attempts_throttled() ) {
			wp_send_json_error( [
				'message' => __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$form_id  = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$field_id = isset( $_POST['field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) : '';
		$code     = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		$form = $form_id ? fta_get_form( $form_id ) : null;

		// An inactive form's codes must not be probeable through this
		// endpoint either, and the failure must look identical to a wrong
		// code - not a distinguishable "this form is inactive" message,
		// unlike ajax_submit_form() which can afford to say so because it is
		// not an oracle a visitor is meant to be able to sweep.
		if ( $form && isset( $form['status'] ) && 'active' !== $form['status'] ) {
			$form = null;
		}

		if ( ! $form || '' === $field_id || '' === $code ) {
			$this->record_failed_coupon_attempt();
			wp_send_json_error( [
				'message' => __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$coupon = null;

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['id'], $field['type'] ) && 'coupon' === $field['type'] && $field['id'] === $field_id ) {
				$coupon = PaymentTotals::find_coupon( $field, $code );
				break;
			}
		}

		if ( null === $coupon ) {
			$this->record_failed_coupon_attempt();
			wp_send_json_error( [
				'message' => __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		wp_send_json_success( $coupon );
	}

	/**
	 * Whether this IP is currently over the coupon-attempt budget.
	 *
	 * Read-only: does not itself count as an attempt. Only
	 * record_failed_coupon_attempt() increments the counter, so a
	 * successful validation never consumes budget.
	 *
	 * @since 1.0.4
	 * @return bool True when this IP is over the limit.
	 */
	private function coupon_attempts_throttled() {
		return (int) get_transient( $this->coupon_throttle_key() ) >= 20;
	}

	/**
	 * Record one failed coupon attempt against this IP's budget.
	 *
	 * Called only from the failure paths in ajax_validate_coupon() - after a
	 * lookup has already come back with no match, never before the lookup
	 * and never on a match. Twenty failures per five minutes comfortably
	 * covers a real visitor mistyping a code a handful of times, while still
	 * bounding an automated sweep - which is nothing but failures - to a few
	 * attempts per window per IP. The endpoint grants no discount on its own
	 * (PaymentTotals re-validates on submission), so the bar only needs to
	 * be "not free," not airtight.
	 *
	 * @since 1.0.4
	 */
	private function record_failed_coupon_attempt() {
		$key   = $this->coupon_throttle_key();
		$count = (int) get_transient( $key );

		// 5 minutes. Not MINUTE_IN_SECONDS - a spelled-out literal so the
		// window is legible without chasing a WordPress core constant.
		set_transient( $key, $count + 1, 5 * 60 );
	}

	/**
	 * Transient key for this request's coupon-attempt budget.
	 *
	 * Keyed on get_user_ip(), which - like the rest of this plugin - prefers
	 * client-supplied headers (HTTP_CLIENT_IP, HTTP_X_FORWARDED_FOR) over
	 * REMOTE_ADDR. Those headers are attacker-controlled: a request that
	 * rotates them gets a fresh budget each time, so this throttle raises
	 * the cost of a casual sweep but is not a hard guarantee against a
	 * determined one. The real backstop remains PaymentTotals re-validating
	 * independently on submission.
	 *
	 * @since 1.0.4
	 * @return string
	 */
	private function coupon_throttle_key() {
		return 'fta_coupon_attempts_' . md5( $this->get_user_ip() );
	}

	/**
	 * Store every file-producing field on the form: uploads, then signatures.
	 *
	 * Split from ajax_submit_form() so the cleanup-on-signature-failure
	 * behaviour below is reachable without going through the AJAX transport
	 * (check_ajax_referer(), wp_send_json_*()) in tests.
	 *
	 * @since 1.0.4
	 * @param array $form Form data.
	 * @return array|\WP_Error Map of field name => file records, or WP_Error.
	 */
	private function process_files( $form ) {
		$uploads = ( new Uploads() )->process_form_uploads( $form );

		if ( is_wp_error( $uploads ) ) {
			return $uploads;
		}

		return $this->process_signatures( $form, $uploads );
	}

	/**
	 * Store signature fields and merge them with already-stored uploads.
	 *
	 * Signatures run after uploads, so by the time this runs $uploads may
	 * already point at files moved to disk. If signatures fail, those upload
	 * files must be removed here - otherwise a rejected submission still
	 * leaves files behind, defeating the point of running signatures after
	 * uploads validate instead of before.
	 *
	 * @since 1.0.4
	 * @param array $form    Form data.
	 * @param array $uploads Already-stored upload file records.
	 * @return array|\WP_Error Map of field name => file records, or WP_Error.
	 */
	private function process_signatures( $form, $uploads ) {
		$signatures = ( new Signature() )->process_form_signatures( $form );

		if ( is_wp_error( $signatures ) ) {
			Uploads::cleanup( $uploads );

			return $signatures;
		}

		return array_merge( $uploads, $signatures );
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
			$field_name = fta_get_field_name( $field );

			if ( '' === $field_name || $this->is_presentational_field( $field ) ) {
				continue;
			}

			// File fields arrive in $_FILES and are handled by Uploads, and
			// signatures arrive as data URLs handled by Signature — both run
			// their own required check.
			$skip_type = isset( $field['type'] ) ? $field['type'] : '';

			if ( Uploads::is_file_field( $field ) || 'signature' === $skip_type ) {
				continue;
			}

			$field_value = isset( $submission[ $field_name ] ) ? $submission[ $field_name ] : '';

			$is_empty = $this->is_empty_value( $field_value );

			// Required field validation.
			if ( ! empty( $field['required'] ) && $is_empty ) {
				$errors[ $field_name ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', FORMTURA_TEXTDOMAIN ),
					isset( $field['label'] ) ? $field['label'] : $field_name
				);
				continue;
			}

			// Skip validation if field is empty and not required.
			if ( $is_empty ) {
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

		// Address posts an array of parts with its own completeness rule.
		if ( 'address' === $type ) {
			return $this->validate_address( $value, $field );
		}

		// Multi-value fields are validated per selected value.
		if ( is_array( $value ) ) {
			return true;
		}

		switch ( $type ) {
			case 'email':
				if ( ! is_email( $value ) ) {
					return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', FORMTURA_TEXTDOMAIN ) );
				}
				break;

			// The builder registers this type as `website`; `url` is kept for
			// forms saved before the field was renamed.
			case 'website':
			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return new \WP_Error( 'invalid_url', __( 'Please enter a valid URL.', FORMTURA_TEXTDOMAIN ) );
				}
				break;

			case 'number':
			case 'number-slider':
			case 'rating':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error( 'invalid_number', __( 'Please enter a valid number.', FORMTURA_TEXTDOMAIN ) );
				}
				break;
		}

		return true;
	}

	/**
	 * Validate an address field's parts.
	 *
	 * Required means street line 1, city, state and ZIP are all present.
	 * Line 2 and country are always optional.
	 *
	 * @since 1.0.4
	 * @param mixed $value Submitted value.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error
	 */
	private function validate_address( $value, $field ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'invalid_address', __( 'Please enter a valid address.', FORMTURA_TEXTDOMAIN ) );
		}

		if ( empty( $field['required'] ) ) {
			return true;
		}

		foreach ( [ 'line1', 'city', 'state', 'zip' ] as $part ) {
			if ( ! isset( $value[ $part ] ) || '' === trim( (string) $value[ $part ] ) ) {
				return new \WP_Error( 'incomplete_address', __( 'Please complete the address.', FORMTURA_TEXTDOMAIN ) );
			}
		}

		return true;
	}

	/**
	 * Check whether a submitted value counts as empty.
	 *
	 * Unlike empty(), a literal "0" is treated as a real answer so a zero
	 * rating or slider value satisfies a required field.
	 *
	 * @since 1.0.3
	 * @param mixed $value Submitted value.
	 * @return bool True when no answer was provided.
	 */
	private function is_empty_value( $value ) {
		if ( is_array( $value ) ) {
			return 0 === count( array_filter( $value, function( $item ) {
				return ! $this->is_empty_value( $item );
			} ) );
		}

		return '' === trim( (string) $value );
	}

	/**
	 * Check whether a field is display-only and carries no submitted value.
	 *
	 * @since 1.0.3
	 * @param array $field Field configuration.
	 * @return bool True for presentational fields.
	 */
	private function is_presentational_field( $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		return in_array(
			$type,
			[
				'html',
				'content',
				'page-break',
				'section-divider',
				'entry-preview',
				'layout',
				// The total field renders no input at all (see
				// templates/fields/total.php): its amount is recomputed from
				// the form definition by PaymentTotals. A saved form carrying
				// required:true on one - which the builder used to offer, and
				// older forms may still hold - would otherwise fail its
				// required check on every attempt, with the error attached to
				// a field the visitor has no way to fill in.
				'total',
			],
			true
		);
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
			$field_name = fta_get_field_name( $field );

			if ( '' === $field_name || $this->is_presentational_field( $field ) ) {
				continue;
			}

			// File fields arrive in $_FILES and are handled by Uploads, and
			// signatures arrive as data URLs handled by Signature — the raw
			// data URL must never be stored as sanitized text. A payment
			// form's running total is display-only and is never posted by
			// current markup (and is_presentational_field() already skips it),
			// but the skip is kept as defence-in-depth against a stale saved
			// form or hand-written template that does post one - the client's
			// claimed total must never land in entry data regardless.
			//
			// payment-single posts value="1" as an inclusion marker, not an
			// answer: PaymentTotals reads its price from the form definition
			// and records the line item under the entry's _payment key. Stored
			// as a field answer it would make entry views display "1" as the
			// visitor's response for that item.
			$skip_type = isset( $field['type'] ) ? $field['type'] : '';

			if ( Uploads::is_file_field( $field ) || in_array( $skip_type, [ 'signature', 'total', 'payment-single' ], true ) ) {
				continue;
			}

			$field_type  = isset( $field['type'] ) ? $field['type'] : 'text';
			$field_value = isset( $submission[ $field_name ] ) ? $submission[ $field_name ] : '';

			if ( is_array( $field_value ) ) {
				$sanitized[ $field_name ] = array_map(
					function( $item ) use ( $field_type ) {
						return fta_sanitize_field( $item, $field_type );
					},
					$field_value
				);

				continue;
			}

			$sanitized[ $field_name ] = fta_sanitize_field( $field_value, $field_type );
		}

		return $sanitized;
	}

	/**
	 * Validate reCAPTCHA.
	 *
	 * Verification fails closed: if Google cannot be reached the submission is
	 * rejected rather than let through unchecked.
	 *
	 * @since 1.0.0
	 * @return true|\WP_Error True if valid or not enabled, WP_Error otherwise.
	 */
	private function validate_recaptcha() {
		$config = fta_get_recaptcha_config();

		// If reCAPTCHA is not fully configured, skip validation.
		if ( ! $config['enabled'] ) {
			return true;
		}

		$token = isset( $_POST['g-recaptcha-response'] )
			? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) )
			: '';

		if ( '' === $token ) {
			return new \WP_Error(
				'recaptcha_missing',
				'v2' === $config['version']
					? __( 'Please confirm you are not a robot.', FORMTURA_TEXTDOMAIN )
					: __( 'reCAPTCHA verification could not be completed. Please reload the page and try again.', FORMTURA_TEXTDOMAIN )
			);
		}

		// Verify with Google.
		$response = wp_remote_post( 'https://www.google.com/recaptcha/api/siteverify', [
			'timeout' => 15,
			'body'    => [
				'secret'   => $config['secret_key'],
				'response' => $token,
				'remoteip' => $this->get_user_ip(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			fta_log( 'reCAPTCHA verification request failed: ' . $response->get_error_message(), 'error' );

			return new \WP_Error( 'recaptcha_unavailable', $this->recaptcha_failure_message() );
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( ! is_array( $body ) || empty( $body['success'] ) ) {
			$codes = isset( $body['error-codes'] ) && is_array( $body['error-codes'] )
				? implode( ', ', $body['error-codes'] )
				: 'unknown';

			fta_log( 'reCAPTCHA rejected the token: ' . $codes, 'warning' );

			return new \WP_Error( 'recaptcha_failed', $this->recaptcha_failure_message() );
		}

		// v2 responses carry no score or action, so those checks are v3 only.
		if ( 'v3' === $config['version'] ) {
			// A token minted elsewhere on the site must not be replayable here.
			if ( isset( $body['action'] ) && $body['action'] !== $config['action'] ) {
				fta_log( sprintf( 'reCAPTCHA action mismatch: expected "%s", got "%s".', $config['action'], $body['action'] ), 'warning' );

				return new \WP_Error( 'recaptcha_failed', $this->recaptcha_failure_message() );
			}

			if ( isset( $body['score'] ) && (float) $body['score'] < $config['score_threshold'] ) {
				fta_log( sprintf( 'reCAPTCHA score %s is below the %s threshold.', $body['score'], $config['score_threshold'] ), 'warning' );

				return new \WP_Error( 'recaptcha_failed', $this->recaptcha_failure_message() );
			}
		}

		return true;
	}

	/**
	 * Message shown when a token is present but does not check out.
	 *
	 * @since 1.0.4
	 * @return string
	 */
	private function recaptcha_failure_message() {
		return __( 'reCAPTCHA verification failed. Please try again.', FORMTURA_TEXTDOMAIN );
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
