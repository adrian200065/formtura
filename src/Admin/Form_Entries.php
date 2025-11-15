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

		$entries = fta_get_entries( $form_id );
		$csv_data = $this->generate_csv( $entries );

		wp_send_json_success( [
			'csv' => $csv_data,
			'filename' => 'formtura-entries-' . $form_id . '-' . date( 'Y-m-d' ) . '.csv',
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
		$is_read = isset( $_POST['is_read'] ) ? (bool) $_POST['is_read'] : true;

		// Implementation will be in Entries_DB class.
		wp_send_json_success( [
			'message' => __( 'Entry status updated.', FORMTURA_TEXTDOMAIN ),
		] );
	}

	/**
	 * Generate CSV from entries.
	 *
	 * @since 1.0.0
	 * @param array $entries Entries data.
	 * @return string CSV data.
	 */
	private function generate_csv( $entries ) {
		if ( empty( $entries ) ) {
			return '';
		}

		$output = fopen( 'php://temp', 'r+' );

		// Get headers from first entry.
		$first_entry = reset( $entries );
		$headers = array_keys( $first_entry );
		fputcsv( $output, $headers );

		// Add data rows.
		foreach ( $entries as $entry ) {
			fputcsv( $output, $entry );
		}

		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output );

		return $csv;
	}
}
