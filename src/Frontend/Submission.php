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
	 * POST key of the honeypot field templates/form-wrapper.php renders,
	 * hidden from real visitors and never posted with a value except by
	 * something filling in every input it finds. A real field's submitted
	 * name is always field_<id> (see fta_get_field_name()), so this constant
	 * cannot collide with a form's own data.
	 *
	 * @var string
	 */
	const HONEYPOT_FIELD = 'fta_hp';

	/**
	 * Private storage service shared by the file-producing steps.
	 *
	 * @var File_Storage
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param File_Storage|null $storage Optional storage service. Injected by
	 *                                   tests so the whole file pipeline reads
	 *                                   and writes one temporary vault.
	 */
	public function __construct( $storage = null ) {
		$this->storage = $storage instanceof File_Storage ? $storage : new File_Storage();

		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// AJAX handler for form submission.
		add_action( 'wp_ajax_fta_submit_form', array( $this, 'ajax_submit_form' ) );
		add_action( 'wp_ajax_nopriv_fta_submit_form', array( $this, 'ajax_submit_form' ) );

		// Coupon validation for display-side totals. The submission path
		// re-validates independently; this endpoint only prevents the page
		// from ever carrying the code list.
		add_action( 'wp_ajax_fta_validate_coupon', array( $this, 'ajax_validate_coupon' ) );
		add_action( 'wp_ajax_nopriv_fta_validate_coupon', array( $this, 'ajax_validate_coupon' ) );
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
			wp_send_json_error(
				array(
					'message' => __( 'Invalid form ID.', 'formtura' ),
				)
			);
		}

		// Per-IP submission throttle, checked before any form lookup or
		// validation work so a flood costs this endpoint as little as
		// possible. reCAPTCHA is the primary defence when configured, but
		// this applies regardless - including when reCAPTCHA is disabled,
		// the case it does nothing for.
		if ( $this->submission_rate_limited() ) {
			fta_log( sprintf( 'Submission rate limit exceeded for IP %s (form %d).', $this->get_user_ip(), $form_id ), 'warning' );

			wp_send_json_error(
				array(
					'message' => __( 'You are submitting too quickly. Please wait a moment and try again.', 'formtura' ),
				)
			);
		}

		$this->record_submission_attempt();

		// Get form.
		$form = fta_get_form( $form_id );

		if ( ! $form ) {
			wp_send_json_error(
				array(
					'message' => __( 'Form not found.', 'formtura' ),
				)
			);
		}

		// Check if form is active.
		if ( isset( $form['status'] ) && 'active' !== $form['status'] ) {
			wp_send_json_error(
				array(
					'message' => __( 'This form is currently inactive.', 'formtura' ),
				)
			);
		}

		// Honeypot: a hidden field no real visitor can see or fill in (see
		// templates/form-wrapper.php). A non-empty value means something
		// filled in every input it found, not a person - reported as an
		// ordinary success, with no entry, file, or notification produced,
		// so the sender has no signal that it was caught.
		if ( $this->honeypot_tripped() ) {
			fta_log( sprintf( 'Honeypot triggered for IP %s (form %d).', $this->get_user_ip(), $form_id ), 'warning' );

			wp_send_json_success( $this->build_success_response( $form, null ) );
		}

		// Validate reCAPTCHA if enabled.
		$recaptcha_result = $this->validate_recaptcha();

		if ( is_wp_error( $recaptcha_result ) ) {
			wp_send_json_error(
				array(
					'message'   => $recaptcha_result->get_error_message(),
					'recaptcha' => $recaptcha_result->get_error_code(),
				)
			);
		}

		// Validate and sanitize form data.
		$validation_result = $this->validate_submission( $form, $_POST );

		if ( is_wp_error( $validation_result ) ) {
			wp_send_json_error(
				array(
					'message' => $validation_result->get_error_message(),
					'errors'  => $validation_result->get_error_data(),
				)
			);
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
				wp_send_json_error(
					array(
						'message' => $payment->get_error_message(),
						'errors'  => $payment->get_error_data(),
					)
				);
			}
		}

		// Store uploaded files and signatures. Runs after the rest of the form
		// validates so a rejected submission does not leave files behind.
		$files = $this->process_files( $form );

		if ( is_wp_error( $files ) ) {
			wp_send_json_error(
				array(
					'message' => $files->get_error_message(),
					'errors'  => $files->get_error_data(),
				)
			);
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
		$entry_id = fta_create_entry(
			array(
				'form_id'    => $form_id,
				'data'       => $entry_data,
				'ip_address' => $this->get_user_ip(),
				'user_agent' => $this->get_user_agent(),
				'created_at' => current_time( 'mysql' ),
			)
		);

		if ( ! $entry_id ) {
			// The files above are already on disk, and the entry that would
			// have referenced them does not exist - so nothing will ever read
			// or delete them. Without this, every failed entry write (a full
			// disk, a locked table) leaves a permanent orphan behind, which is
			// the same leak already closed for uploads-then-signature failures
			// in process_signatures() and for a rejected payment recompute.
			( new Uploads( $this->storage ) )->cleanup( $files );

			wp_send_json_error(
				array(
					'message' => __( 'Failed to save form submission. Please try again.', 'formtura' ),
				)
			);
		}

		// Send notifications.
		do_action( 'fta_after_form_submission', $entry_id, $form, $entry_data );

		wp_send_json_success( $this->build_success_response( $form, $entry_id ) );
	}

	/**
	 * Build the JSON payload sent back for a successful submission.
	 *
	 * Shared with the honeypot path in ajax_submit_form(), which reports a
	 * real submission's success shape - message and redirect included - with
	 * $entry_id null, so a caught sender sees nothing distinguishing it from
	 * a genuine one.
	 *
	 * @since 1.0.9
	 * @param array    $form     Form data.
	 * @param int|null $entry_id Created entry id, or null when no entry was
	 *                           created.
	 * @return array
	 */
	private function build_success_response( $form, $entry_id ) {
		// The builder stores this under the camelCase key it posts (see
		// Form_Builder::sanitize_settings_data()); snake_case here never
		// matched a saved setting, so a custom message silently fell back to
		// the default below on every submission.
		$success_message = isset( $form['settings']['successMessage'] )
			? $form['settings']['successMessage']
			: __( 'Thank you! Your form has been submitted successfully.', 'formtura' );

		$redirect_url = isset( $form['settings']['redirect_url'] ) ? $form['settings']['redirect_url'] : '';

		return array(
			'message'      => $success_message,
			'redirect_url' => $redirect_url,
			'entry_id'     => $entry_id,
		);
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
			wp_send_json_error(
				array(
					'message' => __( 'This coupon code is not valid.', 'formtura' ),
				)
			);
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
			wp_send_json_error(
				array(
					'message' => __( 'This coupon code is not valid.', 'formtura' ),
				)
			);
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
			wp_send_json_error(
				array(
					'message' => __( 'This coupon code is not valid.', 'formtura' ),
				)
			);
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
	 * Keyed on get_user_ip(), which only reflects a client-supplied
	 * X-Forwarded-For value when the request arrives from an administrator-
	 * configured trusted proxy (see Settings' `trusted_proxies`) - otherwise
	 * it is the raw connecting address, which a client cannot spoof. A
	 * request from behind an untrusted or unconfigured proxy is keyed on
	 * that proxy's own address instead, so this throttle's precision is only
	 * as good as that configuration.
	 *
	 * @since 1.0.4
	 * @return string
	 */
	private function coupon_throttle_key() {
		return 'fta_coupon_attempts_' . md5( $this->get_user_ip() );
	}

	/**
	 * Whether this IP is currently over the submission budget.
	 *
	 * Unlike the coupon throttle above, every attempt counts here - not just
	 * failures - because a submission is expensive regardless of whether it
	 * ultimately validates: it can write a database row, move an uploaded
	 * file to disk, and send an email. A limit of 0 disables the throttle
	 * entirely (see Settings::get_defaults()).
	 *
	 * @since 1.0.9
	 * @return bool True when this IP is over the limit.
	 */
	private function submission_rate_limited() {
		$limit = (int) fta_get_setting( 'submission_rate_limit', 10 );

		if ( $limit <= 0 ) {
			return false;
		}

		return (int) get_transient( $this->submission_throttle_key() ) >= $limit;
	}

	/**
	 * Record one submission attempt against this IP's budget.
	 *
	 * Called once per request that reaches this point, regardless of how the
	 * submission is ultimately handled (honeypot, validation failure, or a
	 * real entry) - see the note on submission_rate_limited().
	 *
	 * @since 1.0.9
	 */
	private function record_submission_attempt() {
		$key   = $this->submission_throttle_key();
		$count = (int) get_transient( $key );

		// 10 minutes. Not MINUTE_IN_SECONDS - a spelled-out literal so the
		// window is legible without chasing a WordPress core constant.
		set_transient( $key, $count + 1, 10 * 60 );
	}

	/**
	 * Transient key for this request's submission budget.
	 *
	 * @since 1.0.9
	 * @return string
	 */
	private function submission_throttle_key() {
		return 'fta_submission_attempts_' . md5( $this->get_user_ip() );
	}

	/**
	 * Whether the hidden honeypot field was filled in.
	 *
	 * @since 1.0.9
	 * @return bool
	 */
	private function honeypot_tripped() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce already verified in ajax_submit_form() before this method runs.
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- no sanitizer applies; only presence/emptiness of the raw value is checked, and it is never stored or displayed.
		return isset( $_POST[ self::HONEYPOT_FIELD ] ) && '' !== trim( (string) wp_unslash( $_POST[ self::HONEYPOT_FIELD ] ) );
		// phpcs:enable
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
		$uploads = ( new Uploads( $this->storage ) )->process_form_uploads( $form );

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
		$signatures = ( new Signature( $this->storage ) )->process_form_signatures( $form );

		if ( is_wp_error( $signatures ) ) {
			( new Uploads( $this->storage ) )->cleanup( $uploads );

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
		$errors = array();

		if ( ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return new \WP_Error( 'invalid_form', __( 'Invalid form configuration.', 'formtura' ) );
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

			// Required field validation. A field hidden by its own
			// conditional logic is exempt - recomputed here straight from
			// $submission (the same source a trigger field's own value comes
			// from), not trusted from any client-supplied hidden state, so
			// this can't be bypassed by a client that skips running the
			// frontend JS.
			if ( ! empty( $field['required'] ) && $is_empty && $this->is_field_conditionally_visible( $field, $submission ) ) {
				$errors[ $field_name ] = sprintf(
					/* translators: %s: field label */
					__( '%s is required.', 'formtura' ),
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

				if ( true !== $custom_validation ) {
					$errors[ $field_name ] = $custom_validation;
				}
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error( 'validation_failed', __( 'Please correct the errors below.', 'formtura' ), $errors );
		}

		return true;
	}

	/**
	 * Whether a field is visible given its own conditional logic and the
	 * submitted values of its trigger fields. Mirrors
	 * evaluateConditionalLogic() in assets/js/frontend.js so a required
	 * field the frontend hides doesn't also get enforced as required here.
	 *
	 * @since 1.0.8
	 * @param array $field      Field configuration.
	 * @param array $submission Submitted data, keyed by field name.
	 * @return bool
	 */
	private function is_field_conditionally_visible( $field, $submission ) {
		$logic = null;

		if ( ! empty( $field['conditionalLogic'] ) && is_array( $field['conditionalLogic'] ) ) {
			$logic = $field['conditionalLogic'];
		} elseif ( ! empty( $field['conditional_logic'] ) && is_array( $field['conditional_logic'] ) ) {
			$logic = $field['conditional_logic'];
		}

		if ( ! $logic || empty( $logic['enabled'] ) ) {
			return true;
		}

		$conditions = isset( $logic['conditions'] ) && is_array( $logic['conditions'] ) ? $logic['conditions'] : array();
		$match_any  = isset( $logic['match'] ) && 'any' === $logic['match'];

		$conditions_met = $match_any ? false : true;

		foreach ( $conditions as $condition ) {
			$met = $this->conditional_logic_condition_met( $condition, $submission );

			if ( $match_any ) {
				if ( $met ) {
					$conditions_met = true;
					break;
				}
			} elseif ( ! $met ) {
				$conditions_met = false;
				break;
			}
		}

		$show = ! isset( $logic['action'] ) || 'hide' !== $logic['action'];

		return ( $show && $conditions_met ) || ( ! $show && ! $conditions_met );
	}

	/**
	 * Evaluate a single conditional logic condition against the submission.
	 *
	 * @since 1.0.8
	 * @param array $condition  Condition, with `field`, `operator`, `value`.
	 * @param array $submission Submitted data, keyed by field name.
	 * @return bool
	 */
	private function conditional_logic_condition_met( $condition, $submission ) {
		$trigger_name  = isset( $condition['field'] ) ? $condition['field'] : '';
		$trigger_value = isset( $submission[ $trigger_name ] ) ? $submission[ $trigger_name ] : '';
		$operator      = isset( $condition['operator'] ) ? $condition['operator'] : 'is';
		$value         = isset( $condition['value'] ) ? (string) $condition['value'] : '';

		// A checkbox group's trigger value is the array of its checked
		// values; only membership operators make sense against it.
		if ( is_array( $trigger_value ) ) {
			switch ( $operator ) {
				case 'is':
				case 'contains':
					return in_array( $value, $trigger_value, true );
				case 'is_not':
					return ! in_array( $value, $trigger_value, true );
				default:
					return false;
			}
		}

		$trigger_value = (string) $trigger_value;

		switch ( $operator ) {
			case 'is':
				return $trigger_value === $value;
			case 'is_not':
				return $trigger_value !== $value;
			case 'contains':
				return false !== strpos( $trigger_value, $value );
			case 'greater_than':
				return (float) $trigger_value > (float) $value;
			case 'less_than':
				return (float) $trigger_value < (float) $value;
			default:
				return false;
		}
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
					return new \WP_Error( 'invalid_email', __( 'Please enter a valid email address.', 'formtura' ) );
				}
				break;

			// The builder registers this type as `website`; `url` is kept for
			// forms saved before the field was renamed.
			case 'website':
			case 'url':
				if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return new \WP_Error( 'invalid_url', __( 'Please enter a valid URL.', 'formtura' ) );
				}
				break;

			case 'number':
			case 'number-slider':
			case 'rating':
				if ( ! is_numeric( $value ) ) {
					return new \WP_Error( 'invalid_number', __( 'Please enter a valid number.', 'formtura' ) );
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
			return new \WP_Error( 'invalid_address', __( 'Please enter a valid address.', 'formtura' ) );
		}

		if ( empty( $field['required'] ) ) {
			return true;
		}

		foreach ( array( 'line1', 'city', 'state', 'zip' ) as $part ) {
			if ( ! isset( $value[ $part ] ) || '' === trim( (string) $value[ $part ] ) ) {
				return new \WP_Error( 'incomplete_address', __( 'Please complete the address.', 'formtura' ) );
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
			return 0 === count(
				array_filter(
					$value,
					function ( $item ) {
						return ! $this->is_empty_value( $item );
					}
				)
			);
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
			array(
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
			),
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
		$sanitized = array();

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

			if ( Uploads::is_file_field( $field ) || in_array( $skip_type, array( 'signature', 'total', 'payment-single' ), true ) ) {
				continue;
			}

			$field_type  = isset( $field['type'] ) ? $field['type'] : 'text';
			$field_value = isset( $submission[ $field_name ] ) ? $submission[ $field_name ] : '';

			if ( is_array( $field_value ) ) {
				$sanitized[ $field_name ] = array_map(
					function ( $item ) use ( $field_type ) {
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

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce already verified in ajax_submit_form() before this method runs.
		$token = isset( $_POST['g-recaptcha-response'] )
			? sanitize_text_field( wp_unslash( $_POST['g-recaptcha-response'] ) )
			: '';
		// phpcs:enable

		if ( '' === $token ) {
			return new \WP_Error(
				'recaptcha_missing',
				'v2' === $config['version']
					? __( 'Please confirm you are not a robot.', 'formtura' )
					: __( 'reCAPTCHA verification could not be completed. Please reload the page and try again.', 'formtura' )
			);
		}

		// Verify with Google.
		$response = wp_remote_post(
			'https://www.google.com/recaptcha/api/siteverify',
			array(
				'timeout' => 15,
				'body'    => array(
					'secret'   => $config['secret_key'],
					'response' => $token,
					'remoteip' => $this->get_user_ip(),
				),
			)
		);

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
		return __( 'reCAPTCHA verification failed. Please try again.', 'formtura' );
	}

	/**
	 * Get the request's IP address.
	 *
	 * REMOTE_ADDR - the actual TCP peer - is always what's returned, unless
	 * that peer is an administrator-configured trusted proxy (Settings'
	 * `trusted_proxies`), in which case the leftmost address in
	 * X-Forwarded-For is used instead. Both HTTP_CLIENT_IP and an untrusted
	 * X-Forwarded-For are attacker-controlled - a request can set either to
	 * anything - so honoring them unconditionally let a client defeat
	 * anything keyed on this value (the submission and coupon throttles,
	 * abuse log entries) just by rotating the header per request.
	 *
	 * @since 1.0.0
	 * @return string User IP address.
	 */
	private function get_user_ip() {
		$remote_addr = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		if ( '' === $remote_addr || ! isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) || ! $this->ip_is_trusted_proxy( $remote_addr ) ) {
			return $remote_addr;
		}

		$forwarded_for = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
		$client_ip     = trim( explode( ',', $forwarded_for )[0] );

		return filter_var( $client_ip, FILTER_VALIDATE_IP ) ? $client_ip : $remote_addr;
	}

	/**
	 * Whether an address is one of the administrator-configured trusted
	 * proxies (see Settings::sanitize_trusted_proxies()).
	 *
	 * @since 1.0.9
	 * @param string $ip Address to check (the request's REMOTE_ADDR).
	 * @return bool
	 */
	private function ip_is_trusted_proxy( $ip ) {
		$trusted = (string) fta_get_setting( 'trusted_proxies', '' );

		if ( '' === trim( $trusted ) ) {
			return false;
		}

		foreach ( preg_split( '/[\r\n]+/', $trusted ) as $entry ) {
			$entry = trim( $entry );

			if ( '' !== $entry && $this->ip_in_range( $ip, $entry ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether an address falls within a single trusted-proxy entry - either
	 * an exact address or a CIDR range.
	 *
	 * @since 1.0.9
	 * @param string $ip    Address to test.
	 * @param string $range A trusted_proxies entry: an IP or "ip/prefix".
	 * @return bool
	 */
	private function ip_in_range( $ip, $range ) {
		if ( false === strpos( $range, '/' ) ) {
			return $ip === $range;
		}

		list( $subnet, $prefix ) = explode( '/', $range, 2 );

		$ip_bin     = @inet_pton( $ip ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- inet_pton() warns on malformed input; failure is handled below via its false return.
		$subnet_bin = @inet_pton( $subnet ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( false === $ip_bin || false === $subnet_bin || strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
			return false;
		}

		$bits           = max( 0, min( strlen( $ip_bin ) * 8, (int) $prefix ) );
		$bytes          = intdiv( $bits, 8 );
		$remainder_bits = $bits % 8;

		if ( $bytes > 0 && substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
			return false;
		}

		if ( 0 === $remainder_bits ) {
			return true;
		}

		$mask = ~( 0xFF >> $remainder_bits ) & 0xFF;

		return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $subnet_bin[ $bytes ] ) & $mask );
	}

	/**
	 * Get user agent.
	 *
	 * @since 1.0.0
	 * @return string User agent.
	 */
	private function get_user_agent() {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';
	}
}
