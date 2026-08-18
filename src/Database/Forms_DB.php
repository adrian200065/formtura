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
	 * @param \Formtura\Frontend\File_Storage|null $storage Optional storage
	 *        service, passed through to entry deletion.
	 */
	public function __construct( $storage = null ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'fta_forms';

		$this->storage = $storage instanceof \Formtura\Frontend\File_Storage ? $storage : null;
	}

	/**
	 * Private storage service, passed through to entry deletion.
	 *
	 * @var \Formtura\Frontend\File_Storage|null
	 */
	private $storage;

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
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal constant ($wpdb->prefix . 'fta_forms'), not user input.
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
	public function get_all( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'DESC',
			'limit'   => 20,
			'offset'  => 0,
		);

		$args = wp_parse_args( $args, $defaults );

		$where = '1=1';

		if ( ! empty( $args['status'] ) ) {
			$where .= $wpdb->prepare( ' AND status = %s', $args['status'] );
		}

		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );
		$limit   = absint( $args['limit'] );
		$offset  = absint( $args['offset'] );

		$query = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} LIMIT {$limit} OFFSET {$offset}";

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where is built from $wpdb->prepare() fragments above, $orderby is whitelisted via sanitize_sql_orderby(), $limit/$offset are absint()'d; nothing here is unprepared user input.
		$forms = $wpdb->get_results( $query, ARRAY_A );

		return array_map( array( $this, 'prepare_form' ), $forms );
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

		$defaults = array(
			'title'       => __( 'Untitled Form', 'formtura' ),
			'description' => '',
			'fields'      => array(),
			'settings'    => array(),
			'status'      => 'active',
		);

		$data = wp_parse_args( $data, $defaults );

		$insert_data = array(
			'title'       => $data['title'],
			'description' => $data['description'],
			'fields'      => wp_json_encode( $data['fields'] ),
			'settings'    => wp_json_encode( $data['settings'] ),
			'status'      => $data['status'],
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		);

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

		$update_data = array();

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
			array( 'id' => $form_id )
		);

		return false !== $result;
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

		// Delete associated entries first, and stop if that fails: deleting
		// the form anyway would strand those entries under a form that no
		// longer exists, leaving their files unreachable by any cleanup path
		// that starts from a form.
		$entries_db = new Entries_DB( $this->storage );

		if ( ! $entries_db->delete_by_form( $form_id ) ) {
			return false;
		}

		// Delete form.
		$result = $wpdb->delete(
			$this->table_name,
			array( 'id' => $form_id )
		);

		return false !== $result;
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

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a fixed internal constant; $where is built from $wpdb->prepare() fragments above.
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
			$form['fields'] = is_array( $decoded_fields ) ? $decoded_fields : array();
		} else {
			$form['fields'] = array();
		}

		if ( isset( $form['settings'] ) && ! empty( $form['settings'] ) ) {
			$decoded_settings = json_decode( $form['settings'], true );
			$form['settings'] = is_array( $decoded_settings ) ? $decoded_settings : array();
		} else {
			$form['settings'] = array();
		}

		// Convert ID to integer.
		if ( isset( $form['id'] ) ) {
			$form['id'] = (int) $form['id'];
		}

		return $form;
	}
}
