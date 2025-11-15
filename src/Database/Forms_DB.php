<?php
/**
 * Forms Database Class
 *
 * CRUD operations for forms.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Forms_DB class.
 */
class Forms_DB {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'fta_forms';
	}

	/**
	 * Get a form by ID.
	 *
	 * @since 1.0.0
	 * @param int $form_id Form ID.
	 * @return array|null Form data or null if not found.
	 */
	public function get( $form_id ) {
		global $wpdb;

		$form = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $form_id ),
			ARRAY_A
		);

		if ( ! $form ) {
			return null;
		}

		return $this->prepare_form( $form );
	}

	/**
	 * Get all forms.
	 *
	 * @since 1.0.0
	 * @param array $args Query arguments.
	 * @return array Array of forms.
	 */
	public function get_all( $args = [] ) {
		global $wpdb;

		$defaults = [
			'status'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'limit'    => 20,
			'offset'   => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$query = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} LIMIT {$limit} OFFSET {$offset}";

		$forms = $wpdb->get_results( $query, ARRAY_A );

		return array_map( [ $this, 'prepare_form' ], $forms );
	}

	/**
	 * Create a new form.
	 *
	 * @since 1.0.0
	 * @param array $data Form data.
	 * @return int|false Form ID on success, false on failure.
	 */
	public function create( $data ) {
		global $wpdb;

		$defaults = [
			'title'       => __( 'Untitled Form', FORMTURA_TEXTDOMAIN ),
			'description' => '',
			'fields'      => [],
			'settings'    => [],
			'status'      => 'active',
		];

		$data = wp_parse_args( $data, $defaults );

		$insert_data = [
			'title'       => $data['title'],
			'description' => $data['description'],
			'fields'      => wp_json_encode( $data['fields'] ),
			'settings'    => wp_json_encode( $data['settings'] ),
			'status'      => $data['status'],
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		];

		$result = $wpdb->insert( $this->table_name, $insert_data );

		if ( $result ) {
			return $wpdb->insert_id;
		}

		return false;
	}

	/**
	 * Update a form.
	 *
	 * @since 1.0.0
	 * @param int   $form_id Form ID.
	 * @param array $data Form data.
	 * @return bool True on success, false on failure.
	 */
	public function update( $form_id, $data ) {
		global $wpdb;

		$update_data = [];

		if ( isset( $data['title'] ) ) {
			$update_data['title'] = $data['title'];
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = $data['description'];
		}

		if ( isset( $data['fields'] ) ) {
			$update_data['fields'] = wp_json_encode( $data['fields'] );
		}

		if ( isset( $data['settings'] ) ) {
			$update_data['settings'] = wp_json_encode( $data['settings'] );
		}

		if ( isset( $data['status'] ) ) {
			$update_data['status'] = $data['status'];
		}

		$update_data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			[ 'id' => $form_id ]
		);

		return $result !== false;
	}

	/**
	 * Delete a form.
	 *
	 * @since 1.0.0
	 * @param int $form_id Form ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $form_id ) {
		global $wpdb;

		// Delete associated entries.
		$entries_db = new Entries_DB();
		$entries_db->delete_by_form( $form_id );

		// Delete form.
		$result = $wpdb->delete(
			$this->table_name,
			[ 'id' => $form_id ]
		);

		return $result !== false;
	}

	/**
	 * Get form count.
	 *
	 * @since 1.0.0
	 * @param string $status Optional. Filter by status.
	 * @return int Form count.
	 */
	public function get_count( $status = '' ) {
		global $wpdb;

		$where = '1=1';

		if ( ! empty( $status ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $status );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}" );
	}

	/**
	 * Prepare form data.
	 *
	 * @since 1.0.0
	 * @param array $form Raw form data from database.
	 * @return array Prepared form data.
	 */
	private function prepare_form( $form ) {
		// Decode JSON fields.
		if ( isset( $form['fields'] ) && ! empty( $form['fields'] ) ) {
			$decoded_fields = json_decode( $form['fields'], true );
			$form['fields'] = is_array( $decoded_fields ) ? $decoded_fields : [];
		} else {
			$form['fields'] = [];
		}

		if ( isset( $form['settings'] ) && ! empty( $form['settings'] ) ) {
			$decoded_settings = json_decode( $form['settings'], true );
			$form['settings'] = is_array( $decoded_settings ) ? $decoded_settings : [];
		} else {
			$form['settings'] = [];
		}

		// Convert ID to integer.
		if ( isset( $form['id'] ) ) {
			$form['id'] = (int) $form['id'];
		}

		return $form;
	}
}
