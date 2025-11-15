<?php
/**
 * Form Builder Class
 *
 * Handles the drag-and-drop form builder interface.
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
 * Form_Builder class.
 */
class Form_Builder {

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
		add_action( 'wp_ajax_fta_save_form', [ $this, 'ajax_save_form' ] );
		add_action( 'wp_ajax_fta_get_form', [ $this, 'ajax_get_form' ] );
		add_action( 'wp_ajax_fta_duplicate_form', [ $this, 'ajax_duplicate_form' ] );
	}

	/**
	 * Render form builder page.
	 *
	 * @since 1.0.0
	 * @param int $form_id Form ID to edit.
	 */
	public function render( $form_id = 0 ) {
		$form = null;

		if ( $form_id ) {
			$form = fta_get_form( $form_id );
		}

		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/form-builder.php';
	}

	/**
	 * AJAX handler to save form.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_form() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Get form data.
		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$form_data_json = isset( $_POST['form_data'] ) ? stripslashes( $_POST['form_data'] ) : '';
		$form_data = json_decode( $form_data_json, true );

		if ( empty( $form_data ) || ! is_array( $form_data ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid form data.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Extract fields and settings from the form_data structure.
		$fields = isset( $form_data['fields'] ) ? $form_data['fields'] : [];
		$settings = isset( $form_data['settings'] ) ? $form_data['settings'] : [];

		// Prepare data for database.
		$db_data = [
			'fields'   => $fields,
			'settings' => $settings,
		];

		// Add title from settings if available.
		if ( isset( $settings['title'] ) && ! empty( $settings['title'] ) ) {
			$db_data['title'] = $settings['title'];
		}

		// Add description from settings if available.
		if ( isset( $settings['description'] ) && ! empty( $settings['description'] ) ) {
			$db_data['description'] = $settings['description'];
		}

		// Sanitize form data.
		$sanitized_data = $this->sanitize_form_data( $db_data );

		// Save or update form.
		if ( $form_id ) {
			$result = fta_update_form( $form_id, $sanitized_data );
			$message = __( 'Form updated successfully.', FORMTURA_TEXTDOMAIN );
		} else {
			$result = fta_create_form( $sanitized_data );
			$form_id = $result;
			$message = __( 'Form created successfully.', FORMTURA_TEXTDOMAIN );
		}

		if ( $result ) {
			wp_send_json_success( [
				'message' => $message,
				'form_id' => $form_id,
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to save form.', FORMTURA_TEXTDOMAIN ),
			] );
		}
	}

	/**
	 * AJAX handler to get form data.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_form() {
		// Verify nonce.
		$nonce = isset( $_REQUEST['nonce'] ) ? $_REQUEST['nonce'] : '';
		if ( ! wp_verify_nonce( $nonce, 'formtura_admin' ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid nonce.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$form_id = isset( $_REQUEST['form_id'] ) ? absint( $_REQUEST['form_id'] ) : 0;

		if ( ! $form_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid form ID.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$form = fta_get_form( $form_id );

		if ( $form ) {
			// Prepare form_data structure for React app.
			$form_data = [
				'fields'   => isset( $form['fields'] ) ? $form['fields'] : [],
				'settings' => isset( $form['settings'] ) ? $form['settings'] : [],
			];

			// Return form data in the format React expects.
			$response = [
				'id'        => $form['id'],
				'title'     => $form['title'],
				'form_data' => wp_json_encode( $form_data ),
			];

			wp_send_json_success( $response );
		} else {
			wp_send_json_error( [
				'message' => __( 'Form not found.', FORMTURA_TEXTDOMAIN ),
			] );
		}
	}

	/**
	 * AJAX handler to duplicate form.
	 *
	 * @since 1.0.0
	 */
	public function ajax_duplicate_form() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;

		if ( ! $form_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid form ID.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$form = fta_get_form( $form_id );

		if ( ! $form ) {
			wp_send_json_error( [
				'message' => __( 'Form not found.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Create duplicate.
		$duplicate_data = $form;
		$duplicate_data['title'] = $form['title'] . ' ' . __( '(Copy)', FORMTURA_TEXTDOMAIN );
		unset( $duplicate_data['id'] );

		$new_form_id = fta_create_form( $duplicate_data );

		if ( $new_form_id ) {
			wp_send_json_success( [
				'message' => __( 'Form duplicated successfully.', FORMTURA_TEXTDOMAIN ),
				'form_id' => $new_form_id,
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to duplicate form.', FORMTURA_TEXTDOMAIN ),
			] );
		}
	}

	/**
	 * Sanitize form data.
	 *
	 * @since 1.0.0
	 * @param array $data Form data.
	 * @return array Sanitized data.
	 */
	private function sanitize_form_data( $data ) {
		$sanitized = [];

		// Sanitize title.
		if ( isset( $data['title'] ) ) {
			$sanitized['title'] = sanitize_text_field( $data['title'] );
		}

		// Sanitize description.
		if ( isset( $data['description'] ) ) {
			$sanitized['description'] = wp_kses_post( $data['description'] );
		}

		// Sanitize fields.
		if ( isset( $data['fields'] ) && is_array( $data['fields'] ) ) {
			$sanitized['fields'] = array_map( [ $this, 'sanitize_field_data' ], $data['fields'] );
		}

		// Sanitize settings.
		if ( isset( $data['settings'] ) && is_array( $data['settings'] ) ) {
			$sanitized['settings'] = $this->sanitize_settings_data( $data['settings'] );
		}

		// Sanitize status.
		if ( isset( $data['status'] ) ) {
			$sanitized['status'] = in_array( $data['status'], [ 'active', 'inactive' ], true ) ? $data['status'] : 'active';
		}

		return $sanitized;
	}

	/**
	 * Sanitize field data.
	 *
	 * @since 1.0.0
	 * @param array $field Field data.
	 * @return array Sanitized field data.
	 */
	private function sanitize_field_data( $field ) {
		$sanitized = [];

		// Sanitize field ID.
		if ( isset( $field['id'] ) ) {
			$sanitized['id'] = sanitize_text_field( $field['id'] );
		}

		// Sanitize field type.
		if ( isset( $field['type'] ) ) {
			$sanitized['type'] = sanitize_key( $field['type'] );
		}

		// Sanitize label.
		if ( isset( $field['label'] ) ) {
			$sanitized['label'] = sanitize_text_field( $field['label'] );
		}

		// Sanitize placeholder.
		if ( isset( $field['placeholder'] ) ) {
			$sanitized['placeholder'] = sanitize_text_field( $field['placeholder'] );
		}

		// Sanitize description.
		if ( isset( $field['description'] ) ) {
			$sanitized['description'] = sanitize_text_field( $field['description'] );
		}

		// Sanitize required.
		if ( isset( $field['required'] ) ) {
			$sanitized['required'] = (bool) $field['required'];
		}

		// Sanitize field size.
		if ( isset( $field['fieldSize'] ) ) {
			$sanitized['fieldSize'] = sanitize_text_field( $field['fieldSize'] );
		}

		// Sanitize hide label.
		if ( isset( $field['hideLabel'] ) ) {
			$sanitized['hideLabel'] = (bool) $field['hideLabel'];
		}

		// Sanitize CSS classes.
		if ( isset( $field['cssClasses'] ) ) {
			$sanitized['cssClasses'] = sanitize_text_field( $field['cssClasses'] );
		}

		// Sanitize read-only.
		if ( isset( $field['readOnly'] ) ) {
			$sanitized['readOnly'] = (bool) $field['readOnly'];
		}

		// Sanitize enable autocomplete.
		if ( isset( $field['enableAutocomplete'] ) ) {
			$sanitized['enableAutocomplete'] = (bool) $field['enableAutocomplete'];
		}

		// Sanitize rows (for textarea).
		if ( isset( $field['rows'] ) ) {
			$sanitized['rows'] = absint( $field['rows'] );
		}

		// Sanitize options (for select, radio, checkbox).
		if ( isset( $field['options'] ) && is_array( $field['options'] ) ) {
			$sanitized['options'] = array_map( 'sanitize_text_field', $field['options'] );
		}

		// Sanitize validation rules.
		if ( isset( $field['validation'] ) && is_array( $field['validation'] ) ) {
			$sanitized['validation'] = $field['validation'];
		}

		// Sanitize conditional logic.
		if ( isset( $field['conditional_logic'] ) && is_array( $field['conditional_logic'] ) ) {
			$sanitized['conditional_logic'] = $field['conditional_logic'];
		}

		return $sanitized;
	}

	/**
	 * Sanitize settings data.
	 *
	 * @since 1.0.0
	 * @param array $settings Settings data.
	 * @return array Sanitized settings data.
	 */
	private function sanitize_settings_data( $settings ) {
		$sanitized = [];

		// Sanitize title (from React camelCase).
		if ( isset( $settings['title'] ) ) {
			$sanitized['title'] = sanitize_text_field( $settings['title'] );
		}

		// Sanitize description (from React camelCase).
		if ( isset( $settings['description'] ) ) {
			$sanitized['description'] = wp_kses_post( $settings['description'] );
		}

		// Sanitize submit button text (React uses camelCase: submitButtonText).
		if ( isset( $settings['submitButtonText'] ) ) {
			$sanitized['submitButtonText'] = sanitize_text_field( $settings['submitButtonText'] );
		} elseif ( isset( $settings['submit_button_text'] ) ) {
			$sanitized['submitButtonText'] = sanitize_text_field( $settings['submit_button_text'] );
		}

		// Sanitize success message (React uses camelCase: successMessage).
		if ( isset( $settings['successMessage'] ) ) {
			$sanitized['successMessage'] = wp_kses_post( $settings['successMessage'] );
		} elseif ( isset( $settings['success_message'] ) ) {
			$sanitized['successMessage'] = wp_kses_post( $settings['success_message'] );
		}

		// Sanitize redirect URL.
		if ( isset( $settings['redirect_url'] ) ) {
			$sanitized['redirect_url'] = esc_url_raw( $settings['redirect_url'] );
		}

		// Sanitize notification settings.
		if ( isset( $settings['notifications'] ) && is_array( $settings['notifications'] ) ) {
			$sanitized['notifications'] = $settings['notifications'];
		}

		return $sanitized;
	}
}
