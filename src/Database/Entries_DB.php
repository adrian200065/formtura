<?php
/**
 * Entries Database Class
 *
 * CRUD operations for form entries.
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
 * Entries_DB class.
 */
class Entries_DB {

	/**
	 * Entries table name.
	 *
	 * @var string
	 */
	private $table_name;

	/**
	 * Entry meta table name.
	 *
	 * @var string
	 */
	private $meta_table_name;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'fta_entries';
		$this->meta_table_name = $wpdb->prefix . 'fta_entry_meta';
	}

	/**
	 * Get an entry by ID.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return array|null Entry data or null if not found.
	 */
	public function get( $entry_id ) {
		global $wpdb;

		$entry = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$this->table_name} WHERE id = %d", $entry_id ),
			ARRAY_A
		);

		if ( ! $entry ) {
			return null;
		}

		// Get entry meta.
		$entry['data'] = $this->get_entry_meta( $entry_id );

		return $this->prepare_entry( $entry );
	}

	/**
	 * Get entries by form ID.
	 *
	 * @since 1.0.0
	 * @param int   $form_id Form ID.
	 * @param array $args Query arguments.
	 * @return array Array of entries.
	 */
	public function get_by_form( $form_id, $args = [] ) {
		global $wpdb;

		$defaults = [
			'is_read'  => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'limit'    => 20,
			'offset'   => 0,
			'page'     => 1,
			'per_page' => 20,
		];

		$args = wp_parse_args( $args, $defaults );

		// Calculate offset from page if provided.
		if ( $args['page'] > 1 ) {
			$args['offset'] = ( $args['page'] - 1 ) * $args['per_page'];
			$args['limit'] = $args['per_page'];
		}

		$where = $wpdb->prepare( 'form_id = %d', $form_id );

		if ( $args['is_read'] !== '' ) {
			$where .= $wpdb->prepare( ' AND is_read = %d', (int) $args['is_read'] );
		}

		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );
		$limit = absint( $args['limit'] );
		$offset = absint( $args['offset'] );

		$query = "SELECT * FROM {$this->table_name} WHERE {$where} ORDER BY {$orderby} LIMIT {$limit} OFFSET {$offset}";

		$entries = $wpdb->get_results( $query, ARRAY_A );

		// Get meta for each entry.
		foreach ( $entries as &$entry ) {
			$entry['data'] = $this->get_entry_meta( $entry['id'] );
			$entry = $this->prepare_entry( $entry );
		}

		return $entries;
	}

	/**
	 * Create a new entry.
	 *
	 * @since 1.0.0
	 * @param array $data Entry data.
	 * @return int|false Entry ID on success, false on failure.
	 */
	public function create( $data ) {
		global $wpdb;

		$defaults = [
			'form_id'    => 0,
			'user_id'    => get_current_user_id(),
			'data'       => [],
			'ip_address' => '',
			'user_agent' => '',
			'is_read'    => 0,
		];

		$data = wp_parse_args( $data, $defaults );

		// Store entry data separately.
		$entry_data = $data['data'];
		unset( $data['data'] );

		$insert_data = [
			'form_id'    => $data['form_id'],
			'user_id'    => $data['user_id'] ?: null,
			'ip_address' => $data['ip_address'],
			'user_agent' => $data['user_agent'],
			'is_read'    => $data['is_read'],
			'created_at' => current_time( 'mysql' ),
		];

		$result = $wpdb->insert( $this->table_name, $insert_data );

		if ( ! $result ) {
			return false;
		}

		$entry_id = $wpdb->insert_id;

		// Save entry meta.
		$this->save_entry_meta( $entry_id, $entry_data );

		return $entry_id;
	}

	/**
	 * Update an entry.
	 *
	 * @since 1.0.0
	 * @param int   $entry_id Entry ID.
	 * @param array $data Entry data.
	 * @return bool True on success, false on failure.
	 */
	public function update( $entry_id, $data ) {
		global $wpdb;

		$update_data = [];

		if ( isset( $data['is_read'] ) ) {
			$update_data['is_read'] = (int) $data['is_read'];
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$result = $wpdb->update(
			$this->table_name,
			$update_data,
			[ 'id' => $entry_id ]
		);

		// Update entry meta if provided.
		if ( isset( $data['data'] ) ) {
			$this->save_entry_meta( $entry_id, $data['data'] );
		}

		return $result !== false;
	}

	/**
	 * Delete an entry.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete( $entry_id ) {
		global $wpdb;

		// Delete entry meta.
		$wpdb->delete(
			$this->meta_table_name,
			[ 'entry_id' => $entry_id ]
		);

		// Delete entry.
		$result = $wpdb->delete(
			$this->table_name,
			[ 'id' => $entry_id ]
		);

		return $result !== false;
	}

	/**
	 * Delete all entries for a form.
	 *
	 * @since 1.0.0
	 * @param int $form_id Form ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_by_form( $form_id ) {
		global $wpdb;

		// Get all entry IDs for this form.
		$entry_ids = $wpdb->get_col(
			$wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE form_id = %d", $form_id )
		);

		if ( empty( $entry_ids ) ) {
			return true;
		}

		// Delete entry meta.
		$placeholders = implode( ',', array_fill( 0, count( $entry_ids ), '%d' ) );
		$wpdb->query(
			$wpdb->prepare( "DELETE FROM {$this->meta_table_name} WHERE entry_id IN ({$placeholders})", $entry_ids )
		);

		// Delete entries.
		$result = $wpdb->delete(
			$this->table_name,
			[ 'form_id' => $form_id ]
		);

		return $result !== false;
	}

	/**
	 * Get entry count for a form.
	 *
	 * @since 1.0.0
	 * @param int    $form_id Form ID.
	 * @param string $is_read Optional. Filter by read status.
	 * @return int Entry count.
	 */
	public function get_count( $form_id, $is_read = '' ) {
		global $wpdb;

		$where = $wpdb->prepare( 'form_id = %d', $form_id );

		if ( $is_read !== '' ) {
			$where .= $wpdb->prepare( ' AND is_read = %d', (int) $is_read );
		}

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where}" );
	}

	/**
	 * Get entry meta.
	 *
	 * @since 1.0.0
	 * @param int $entry_id Entry ID.
	 * @return array Entry meta data.
	 */
	private function get_entry_meta( $entry_id ) {
		global $wpdb;

		$meta = $wpdb->get_results(
			$wpdb->prepare( "SELECT meta_key, meta_value FROM {$this->meta_table_name} WHERE entry_id = %d", $entry_id ),
			ARRAY_A
		);

		$data = [];

		foreach ( $meta as $row ) {
			$data[ $row['meta_key'] ] = maybe_unserialize( $row['meta_value'] );
		}

		return $data;
	}

	/**
	 * Save entry meta.
	 *
	 * @since 1.0.0
	 * @param int   $entry_id Entry ID.
	 * @param array $data Meta data.
	 */
	private function save_entry_meta( $entry_id, $data ) {
		global $wpdb;

		// Delete existing meta.
		$wpdb->delete(
			$this->meta_table_name,
			[ 'entry_id' => $entry_id ]
		);

		// Insert new meta.
		foreach ( $data as $key => $value ) {
			$wpdb->insert(
				$this->meta_table_name,
				[
					'entry_id'   => $entry_id,
					'meta_key'   => $key,
					'meta_value' => maybe_serialize( $value ),
				]
			);
		}
	}

	/**
	 * Prepare entry data.
	 *
	 * @since 1.0.0
	 * @param array $entry Raw entry data from database.
	 * @return array Prepared entry data.
	 */
	private function prepare_entry( $entry ) {
		// Convert IDs to integers.
		if ( isset( $entry['id'] ) ) {
			$entry['id'] = (int) $entry['id'];
		}

		if ( isset( $entry['form_id'] ) ) {
			$entry['form_id'] = (int) $entry['form_id'];
		}

		if ( isset( $entry['user_id'] ) ) {
			$entry['user_id'] = (int) $entry['user_id'];
		}

		if ( isset( $entry['is_read'] ) ) {
			$entry['is_read'] = (bool) $entry['is_read'];
		}

		return $entry;
	}
}
