<?php
/**
 * Form Entries Class
 *
 * Handles the entry management interface.
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
 * Form_Entries class.
 */
class Form_Entries {

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
		add_action( 'wp_ajax_fta_get_entries', [ $this, 'ajax_get_entries' ] );
		add_action( 'wp_ajax_fta_delete_entry', [ $this, 'ajax_delete_entry' ] );
		add_action( 'wp_ajax_fta_export_entries', [ $this, 'ajax_export_entries' ] );
		add_action( 'wp_ajax_fta_mark_entry_read', [ $this, 'ajax_mark_entry_read' ] );
	}

	/**
	 * Render entries page.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		$forms = fta_get_forms();
		$selected_form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;

		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/entries-list.php';
	}

	/**
	 * AJAX handler to get entries.
	 *
	 * @since 1.0.0
	 */
	public function ajax_get_entries() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$form_id = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
		$per_page = isset( $_POST['per_page'] ) ? absint( $_POST['per_page'] ) : 20;

		$entries = fta_get_entries( $form_id, [
			'page'     => $page,
			'per_page' => $per_page,
		] );

		wp_send_json_success( [
			'entries' => $entries,
		] );
	}

	/**
	 * AJAX handler to delete entry.
	 *
	 * @since 1.0.0
	 */
	public function ajax_delete_entry() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;

		if ( ! $entry_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid entry ID.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$result = fta_delete_entry( $entry_id );

		if ( $result ) {
			wp_send_json_success( [
				'message' => __( 'Entry deleted successfully.', FORMTURA_TEXTDOMAIN ),
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to delete entry.', FORMTURA_TEXTDOMAIN ),
			] );
		}
	}

	/**
	 * AJAX handler to export entries.
	 *
	 * @since 1.0.0
	 */
	public function ajax_export_entries() {
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

		// Entry_Export pages through every entry rather than taking the
		// default first 20, flattens nested values instead of casting them to
		// the word "Array", and neutralizes cells a spreadsheet would
		// otherwise evaluate as formulas.
		$csv_data = ( new Entry_Export() )->for_form( $form_id, fta_get_form( $form_id ) );

		if ( '' === $csv_data ) {
			wp_send_json_error( [
				'message' => __( 'This form has no entries to export.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		wp_send_json_success( [
			'csv' => $csv_data,
			'filename' => 'formtura-entries-' . $form_id . '-' . gmdate( 'Y-m-d' ) . '.csv',
		] );
	}

	/**
	 * AJAX handler to mark entry as read/unread.
	 *
	 * @since 1.0.0
	 */
	public function ajax_mark_entry_read() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$entry_id = isset( $_POST['entry_id'] ) ? absint( $_POST['entry_id'] ) : 0;

		if ( ! $entry_id ) {
			wp_send_json_error( [
				'message' => __( 'Invalid entry ID.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Checked before writing: Entries_DB::update() reports success for an
		// UPDATE that matched no rows, so without this a request naming an
		// entry that no longer exists would be answered "status updated".
		if ( ! fta_get_entry( $entry_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Entry not found.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$is_read = $this->posted_flag( 'is_read', true );

		if ( ! fta_update_entry( $entry_id, [ 'is_read' => $is_read ? 1 : 0 ] ) ) {
			wp_send_json_error( [
				'message' => __( 'Failed to update the entry status.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		wp_send_json_success( [
			'message' => __( 'Entry status updated.', FORMTURA_TEXTDOMAIN ),
			'is_read' => $is_read,
		] );
	}

	/**
	 * Read a boolean flag out of the request.
	 *
	 * A plain (bool) cast reads the strings "false" and "off" as true, which
	 * is the opposite of what a caller sending them asked for - and every
	 * value in $_POST is a string.
	 *
	 * @since 1.0.6
	 * @param string $key     Request key.
	 * @param bool   $default Value to use when the key is absent.
	 * @return bool
	 */
	private function posted_flag( $key, $default = false ) {
		if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			return $default;
		}

		$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification

		return ! in_array( strtolower( trim( (string) $value ) ), [ '', '0', 'false', 'off', 'no' ], true );
	}
}
