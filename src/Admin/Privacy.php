<?php
/**
 * Privacy Class
 *
 * WordPress Privacy API integration: exports and erases Formtura entries for
 * a requested email address, and purges entries past the configured
 * retention window.
 *
 * @package Formtura
 * @since 1.0.6
 */

namespace Formtura\Admin;

use Formtura\Database\Entries_DB;
use Formtura\Database\Forms_DB;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy class.
 */
class Privacy {

	/**
	 * Entries returned per exporter/eraser page, per the WP Privacy API
	 * convention.
	 */
	const PAGE_SIZE = 20;

	/**
	 * Entries source.
	 *
	 * @var object|null
	 */
	private $entries;

	/**
	 * Forms source.
	 *
	 * @var object|null
	 */
	private $forms;

	/**
	 * Constructor.
	 *
	 * @since 1.0.6
	 * @param object|null $entries Optional entries source. Anything exposing
	 *        get_ids_by_user(); injected by tests.
	 * @param object|null $forms   Optional forms source. Anything exposing
	 *        get_all(); injected by tests.
	 */
	public function __construct( $entries = null, $forms = null ) {
		$this->entries = ( is_object( $entries ) && method_exists( $entries, 'get_ids_by_user' ) ) ? $entries : null;
		$this->forms   = ( is_object( $forms ) && method_exists( $forms, 'get_all' ) ) ? $forms : null;

		$this->init_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.6
	 */
	private function init_hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
		add_action( 'fta_purge_old_entries_event', [ $this, 'purge_old_entries' ] );
	}

	/**
	 * The entries source, created on demand.
	 *
	 * @since 1.0.6
	 * @return object
	 */
	private function entries() {
		if ( null === $this->entries ) {
			$this->entries = new Entries_DB();
		}

		return $this->entries;
	}

	/**
	 * The forms source, created on demand.
	 *
	 * @since 1.0.6
	 * @return object
	 */
	private function forms() {
		if ( null === $this->forms ) {
			$this->forms = new Forms_DB();
		}

		return $this->forms;
	}

	/**
	 * Register Formtura's exporter with WordPress's Privacy Tools.
	 *
	 * @since 1.0.6
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['formtura-entries'] = [
			'exporter_friendly_name' => __( 'Formtura Form Entries', FORMTURA_TEXTDOMAIN ),
			'callback'               => [ $this, 'export_data' ],
		];

		return $exporters;
	}

	/**
	 * Register Formtura's eraser with WordPress's Privacy Tools.
	 *
	 * @since 1.0.6
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['formtura-entries'] = [
			'eraser_friendly_name' => __( 'Formtura Form Entries', FORMTURA_TEXTDOMAIN ),
			'callback'              => [ $this, 'erase_data' ],
		];

		return $erasers;
	}

	/**
	 * Entry IDs (deduplicated) matching a requested email address.
	 *
	 * Unions two strategies: the WordPress user account behind a logged-in
	 * submission, and any email-type field's answer on a guest submission -
	 * entries have no fixed schema, so there is no single reliable column to
	 * match on.
	 *
	 * @since 1.0.6
	 * @param string $email Requested email address.
	 * @return int[] Entry IDs.
	 */
	private function matching_entry_ids( $email ) {
		$email = trim( (string) $email );

		if ( '' === $email ) {
			return [];
		}

		$ids = [];

		$user = get_user_by( 'email', $email );

		if ( $user ) {
			$ids = array_merge( $ids, $this->entries()->get_ids_by_user( $user->ID ) );
		}

		// A high, explicit limit: Forms_DB::get_all()'s default caps at 20,
		// which would silently miss forms past the first page on any site
		// with more than 20 forms.
		foreach ( $this->forms()->get_all( [ 'limit' => 100000 ] ) as $form ) {
			$email_fields = $this->email_field_names( $form );

			if ( empty( $email_fields ) ) {
				continue;
			}

			$ids = array_merge(
				$ids,
				$this->entries()->get_ids_by_meta_match( $form['id'], $email_fields, $email )
			);
		}

		$ids = array_values( array_unique( $ids ) );

		// None of the sources above guarantee a stable order (no ORDER BY on
		// the user-match query, a DISTINCT join for the meta-match query, and
		// a created_at DESC form loop with no tiebreaker) - export_data() and
		// erase_data() call this fresh on every page, so an unsorted list can
		// silently skip or repeat entries across pages if the order shifts
		// between calls.
		sort( $ids );

		return $ids;
	}

	/**
	 * Names (storage keys) of a form's email-type fields.
	 *
	 * @since 1.0.6
	 * @param array $form Form, as Forms_DB returns it.
	 * @return string[]
	 */
	private function email_field_names( $form ) {
		$names = [];

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $names;
		}

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['type'] ) && 'email' === $field['type'] ) {
				$name = fta_get_field_name( $field );

				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}

		return $names;
	}

	/**
	 * WP Privacy API exporter callback.
	 *
	 * @since 1.0.6
	 * @param string $email_address Requested email address.
	 * @param int    $page          1-indexed page number.
	 * @return array{data: array[], done: bool}
	 */
	public function export_data( $email_address, $page = 1 ) {
		$page = max( 1, (int) $page );
		$ids  = $this->matching_entry_ids( $email_address );

		$offset   = ( $page - 1 ) * self::PAGE_SIZE;
		$page_ids = array_slice( $ids, $offset, self::PAGE_SIZE );

		$items = [];

		foreach ( $page_ids as $entry_id ) {
			$entry = $this->entries()->get( $entry_id );

			if ( $entry ) {
				$items[] = $this->export_item( $entry );
			}
		}

		return [
			'data' => $items,
			'done' => ( $offset + count( $page_ids ) ) >= count( $ids ),
		];
	}

	/**
	 * Build one entry's export item.
	 *
	 * @since 1.0.6
	 * @param array $entry Entry, as Entries_DB::get() returns it.
	 * @return array
	 */
	private function export_item( $entry ) {
		$data = [
			[ 'name' => __( 'Submitted', FORMTURA_TEXTDOMAIN ), 'value' => isset( $entry['created_at'] ) ? $entry['created_at'] : '' ],
			[ 'name' => __( 'IP Address', FORMTURA_TEXTDOMAIN ), 'value' => isset( $entry['ip_address'] ) ? $entry['ip_address'] : '' ],
			[ 'name' => __( 'User Agent', FORMTURA_TEXTDOMAIN ), 'value' => isset( $entry['user_agent'] ) ? $entry['user_agent'] : '' ],
		];

		$answers = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : [];

		foreach ( $answers as $key => $value ) {
			$data[] = [
				'name'  => (string) $key,
				'value' => is_array( $value ) ? wp_json_encode( $value ) : (string) $value,
			];
		}

		return [
			'group_id'    => 'formtura-entries',
			'group_label' => __( 'Form Entries', FORMTURA_TEXTDOMAIN ),
			'item_id'     => 'formtura-entry-' . $entry['id'],
			'data'        => $data,
		];
	}

	/**
	 * WP Privacy API eraser callback.
	 *
	 * Deletion goes through Entries_DB::delete(), which already removes the
	 * entry row, its meta, and any uploaded files or signatures - no
	 * separate file-cleanup step is needed here.
	 *
	 * @since 1.0.6
	 * @param string $email_address Requested email address.
	 * @param int    $page          1-indexed page number.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase_data( $email_address, $page = 1 ) {
		// Deliberately not using $page to compute an offset: this is a
		// mutating operation, so the match set shrinks as we delete. WordPress
		// increments $page on every call regardless of how much erasure
		// removed, so an offset derived from $page walks past entries that
		// have already scrolled to the front of the now-shorter list -
		// skipping them entirely. Always taking the front slice of the live
		// (re-queried) result on every call is what converges correctly
		// under mutation.
		$ids      = $this->matching_entry_ids( $email_address );
		$page_ids = array_slice( $ids, 0, self::PAGE_SIZE );

		$removed = 0;

		foreach ( $page_ids as $entry_id ) {
			if ( $this->entries()->delete( $entry_id ) ) {
				$removed++;
			}
		}

		$failed = count( $page_ids ) - $removed;

		return [
			'items_removed'  => $removed > 0,
			'items_retained' => $failed > 0,
			'messages'       => $removed > 0
				? [ sprintf( __( '%d form entries removed.', FORMTURA_TEXTDOMAIN ), $removed ) ]
				: [],
			'done'           => count( $page_ids ) >= count( $ids ),
		];
	}

	/**
	 * Delete every entry, across all forms, older than the configured
	 * retention window. A no-op when retention is disabled (the default).
	 *
	 * Registered against the daily fta_purge_old_entries_event cron action
	 * (scheduled in formtura.php on plugin activation). The event stays
	 * scheduled regardless of whether retention is currently enabled - this
	 * check is what makes re-enabling it later require no re-scheduling.
	 *
	 * @since 1.0.6
	 */
	public function purge_old_entries() {
		$days = (int) fta_get_setting( 'entry_retention_days', 0 );

		if ( $days <= 0 ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( $days * DAY_IN_SECONDS ) );

		foreach ( $this->entries()->get_ids_older_than( $cutoff ) as $entry_id ) {
			$this->entries()->delete( $entry_id );
		}
	}
}
