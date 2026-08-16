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
	 * Private storage service, used to clean up an entry's files.
	 *
	 * @var \Formtura\Frontend\File_Storage|null
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param \Formtura\Frontend\File_Storage|null $storage Optional storage
	 *        service. Injected by tests so deletion works against a temporary
	 *        vault.
	 */
	public function __construct( $storage = null ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'fta_entries';
		$this->meta_table_name = $wpdb->prefix . 'fta_entry_meta';

		$this->storage = $storage instanceof \Formtura\Frontend\File_Storage ? $storage : null;
	}

	/**
	 * The storage service, created on demand.
	 *
	 * @since 1.0.5
	 * @return \Formtura\Frontend\File_Storage
	 */
	private function storage() {
		if ( null === $this->storage ) {
			$this->storage = new \Formtura\Frontend\File_Storage();
		}

		return $this->storage;
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

		$requested = is_array( $args ) ? $args : [];
		$args      = wp_parse_args( $args, $defaults );

		// Page size and offset come from page/per_page when the caller uses
		// them, and from limit/offset otherwise. The old rule only applied
		// per_page once page was past 1, so a first-page request for any size
		// silently came back capped at the 20-row default.
		$limit = isset( $requested['per_page'] )
			? absint( $requested['per_page'] )
			: absint( $args['limit'] );

		// A zero page size would become `LIMIT 0` - no rows at all, which is
		// never what a caller asking for a page meant.
		if ( $limit < 1 ) {
			$limit = absint( $defaults['per_page'] );
		}

		$offset = isset( $requested['page'] )
			? ( max( 1, absint( $requested['page'] ) ) - 1 ) * $limit
			: absint( $args['offset'] );

		$where = $wpdb->prepare( 'form_id = %d', $form_id );

		if ( $args['is_read'] !== '' ) {
			$where .= $wpdb->prepare( ' AND is_read = %d', (int) $args['is_read'] );
		}

		// Returns false for anything that is not a plain column list, which
		// would otherwise be interpolated as an empty ORDER BY and break the
		// query outright.
		$orderby = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" );

		if ( false === $orderby ) {
			$orderby = "{$defaults['orderby']} {$defaults['order']}";
		}

		$orderby = $this->make_order_total( $orderby, $args['order'] );

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
	 * Add a primary-key tiebreaker so the sort is a total order.
	 *
	 * LIMIT/OFFSET paging only makes sense over a total order. created_at has
	 * second granularity, so any form taking more than one submission in the
	 * same second has tied rows, and a tied set has no defined order - the
	 * database may serve one row on two consecutive pages and another on
	 * neither.
	 *
	 * This is not theoretical. Against 24 entries sharing a timestamp, page
	 * two came back holding four rows already served on page one, and four
	 * entries appeared on no page at all. The screen shows the duplicates;
	 * the CSV export, which pages the same way, silently drops the rest -
	 * which is the exact failure the export was fixed to stop doing.
	 *
	 * The primary key is unique, so appending it breaks every remaining tie.
	 *
	 * @since 1.0.6
	 * @param string $orderby Sanitized ORDER BY fragment.
	 * @param string $order   Requested direction.
	 * @return string
	 */
	private function make_order_total( $orderby, $order ) {
		foreach ( explode( ',', $orderby ) as $clause ) {
			$column = strtok( trim( $clause ), ' ' );

			// Compared as a whole token, not a substring: form_id and user_id
			// both end in "id" without being unique, and treating either as a
			// tiebreaker would leave the order partial.
			if ( 'id' === strtolower( trim( (string) $column, '`' ) ) ) {
				return $orderby;
			}
		}

		return $orderby . ', id ' . ( 'ASC' === strtoupper( (string) $order ) ? 'ASC' : 'DESC' );
	}

	/**
	 * Create a new entry.
	 *
	 * All or nothing: the entry row and every one of its meta rows are written
	 * inside one transaction, and a failure anywhere returns false. A partial
	 * write reported as success is worse than an outright failure - the caller
	 * sends notifications, keeps the visitor's uploads, and shows a "thank
	 * you" for an entry whose answers are missing.
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

		$wpdb->query( 'START TRANSACTION' );

		$result = $wpdb->insert( $this->table_name, $insert_data );

		if ( ! $result ) {
			$wpdb->query( 'ROLLBACK' );

			return false;
		}

		$entry_id = (int) $wpdb->insert_id;

		// Save entry meta.
		if ( ! $this->save_entry_meta( $entry_id, $entry_data ) ) {
			$wpdb->query( 'ROLLBACK' );
			$this->discard_rows( $entry_id );

			return false;
		}

		$wpdb->query( 'COMMIT' );

		return $entry_id;
	}

	/**
	 * Remove an entry's rows after a rolled-back write.
	 *
	 * ROLLBACK above already undoes the work on InnoDB, which is the WordPress
	 * default. On a site whose tables were created as MyISAM - an older host,
	 * an imported database - the rollback silently does nothing, so the same
	 * cleanup is issued explicitly. Deleting rows that a working rollback
	 * already removed is harmless; leaving an entry row with no field data
	 * behind is not.
	 *
	 * @since 1.0.6
	 * @param int $entry_id Entry ID.
	 */
	private function discard_rows( $entry_id ) {
		global $wpdb;

		$wpdb->delete( $this->meta_table_name, [ 'entry_id' => $entry_id ] );
		$wpdb->delete( $this->table_name, [ 'id' => $entry_id ] );
	}

	/**
	 * Update an entry.
	 *
	 * Transactional for the same reason as create(): the entries row and the
	 * meta rows describe one entry, and half of an update is not a state any
	 * caller can make sense of.
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

		$has_meta = isset( $data['data'] ) && is_array( $data['data'] );

		// Only bail when there is genuinely nothing to write. Testing
		// $update_data alone used to reject an update that carried field data
		// and no column change, returning false without saving the meta.
		if ( empty( $update_data ) && ! $has_meta ) {
			return false;
		}

		$wpdb->query( 'START TRANSACTION' );

		if ( ! empty( $update_data ) ) {
			$result = $wpdb->update(
				$this->table_name,
				$update_data,
				[ 'id' => $entry_id ]
			);

			if ( false === $result ) {
				$wpdb->query( 'ROLLBACK' );

				return false;
			}
		}

		// Update entry meta if provided.
		if ( $has_meta && ! $this->save_entry_meta( $entry_id, $data['data'] ) ) {
			$wpdb->query( 'ROLLBACK' );

			return false;
		}

		$wpdb->query( 'COMMIT' );

		return true;
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

		// Captured before the rows go: once the meta is deleted there is no
		// way left to discover which files this entry owned.
		$entry = $this->get( $entry_id );

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

		if ( false === $result ) {
			// The rows are still there, so the files must be too - otherwise
			// the entry would keep displaying files that no longer exist.
			return false;
		}

		if ( ! empty( $entry['data'] ) ) {
			$this->storage()->delete_records( $entry['data'] );
		}

		return true;
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

		// Captured before the rows go, for the same reason as delete().
		$captured = [];

		foreach ( $entry_ids as $entry_id ) {
			$entry = $this->get( $entry_id );

			if ( ! empty( $entry['data'] ) ) {
				$captured[] = $entry['data'];
			}
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

		if ( false === $result ) {
			return false;
		}

		foreach ( $captured as $data ) {
			$this->storage()->delete_records( $data );
		}

		return true;
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
	 * Entry IDs belonging to a WordPress user.
	 *
	 * Used by the Privacy API integration to find entries submitted by a
	 * logged-in requester.
	 *
	 * @since 1.0.6
	 * @param int $user_id WordPress user ID.
	 * @return int[] Entry IDs.
	 */
	public function get_ids_by_user( $user_id ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE user_id = %d", $user_id )
			)
		);
	}

	/**
	 * Entry IDs, scoped to one form, whose meta value under any of the given
	 * keys case-insensitively matches a value.
	 *
	 * Used by the Privacy API integration to find guest entries by an
	 * email-type field's answer. Scoped to a single form so a field name
	 * reused across forms cannot cross-match another form's entries.
	 *
	 * @since 1.0.6
	 * @param int      $form_id   Form ID.
	 * @param string[] $meta_keys Meta keys (field names) to check.
	 * @param string   $value     Value to match, case-insensitively.
	 * @return int[] Entry IDs.
	 */
	public function get_ids_by_meta_match( $form_id, array $meta_keys, $value ) {
		global $wpdb;

		if ( empty( $meta_keys ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		$args = [ $form_id ];
		$args = array_merge( $args, $meta_keys );
		$args[] = $value;

		$query = $wpdb->prepare(
			"SELECT DISTINCT m.entry_id FROM {$this->meta_table_name} m
			INNER JOIN {$this->table_name} e ON e.id = m.entry_id
			WHERE e.form_id = %d AND m.meta_key IN ({$placeholders}) AND LOWER(m.meta_value) = LOWER(%s)",
			...$args
		);

		return array_map( 'intval', $wpdb->get_col( $query ) );
	}

	/**
	 * Entry IDs older than a cutoff, across every form.
	 *
	 * Used by the automatic retention purge.
	 *
	 * @since 1.0.6
	 * @param string $cutoff MySQL datetime string.
	 * @return int[] Entry IDs.
	 */
	public function get_ids_older_than( $cutoff ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE created_at < %s", $cutoff )
			)
		);
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
	 * Every write is checked and the first failure stops the run. The caller
	 * is inside a transaction and turns a false here into a rollback, so
	 * carrying on after a failed insert would only widen the damage.
	 *
	 * @since 1.0.0
	 * @param int   $entry_id Entry ID.
	 * @param array $data Meta data.
	 * @return bool True when every row was written, false otherwise.
	 */
	private function save_entry_meta( $entry_id, $data ) {
		global $wpdb;

		// Delete existing meta. A failure here matters: the old answers would
		// otherwise survive alongside the new ones.
		$deleted = $wpdb->delete(
			$this->meta_table_name,
			[ 'entry_id' => $entry_id ]
		);

		if ( false === $deleted ) {
			return false;
		}

		// Insert new meta.
		foreach ( $data as $key => $value ) {
			$inserted = $wpdb->insert(
				$this->meta_table_name,
				[
					'entry_id'   => $entry_id,
					'meta_key'   => $key,
					'meta_value' => maybe_serialize( $value ),
				]
			);

			if ( ! $inserted ) {
				return false;
			}
		}

		return true;
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
