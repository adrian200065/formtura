<?php
/**
 * Entry management AJAX handlers.
 *
 * Mark-as-read was a stub: it verified the nonce and the capability, then
 * returned "Entry status updated." without touching the database, so the
 * screen reported a change that never happened and the row reverted on the
 * next load. Export handed fta_get_entries() its default arguments, capping
 * every export at 20 rows.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Form_Entries;
use Formtura\Tests\TestCase;

class EntryActionsTest extends TestCase {

	/**
	 * @var object
	 */
	private $recordingWpdb;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb                = $this->makeWpdb();
		$this->recordingWpdb = $wpdb;

		$GLOBALS['fta_test_current_user_can'] = true;
		$_POST                                = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_current_user_can'], $GLOBALS['fta_test_ajax_referer_valid'] );
		$_POST = [];

		parent::tearDown();
	}

	/**
	 * A $wpdb serving one form, one entry and its meta, recording updates.
	 */
	private function makeWpdb() {
		return new class {
			public $prefix     = 'wp_';
			public $insert_id  = 0;
			public $updates    = [];
			public $entryRow   = null;
			public $entryMeta  = [];
			public $entryRows  = [];
			public $formRow    = null;
			public $failUpdate = false;

			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					$query = preg_replace( '/%d|%s/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			public function get_row( $query, $output = ARRAY_A, $y = 0 ) {
				return false !== strpos( $query, 'fta_forms' ) ? $this->formRow : $this->entryRow;
			}

			public function get_results( $query, $output = ARRAY_A ) {
				if ( false !== strpos( $query, 'fta_entry_meta' ) ) {
					return $this->entryMeta;
				}

				// Entries are served on the first page only, so the exporter's
				// paging loop terminates.
				if ( false !== strpos( $query, 'OFFSET 0' ) ) {
					return $this->entryRows;
				}

				return [];
			}

			public function get_col( $query, $x = 0 ) {
				return [];
			}

			public function get_var( $query = null, $x = 0, $y = 0 ) {
				return null;
			}

			public function query( $query ) {
				return true;
			}

			public function insert( $table, $data, $format = null ) {
				return 1;
			}

			public function update( $table, $data, $where, $format = null, $where_format = null ) {
				$this->updates[] = [ $table, $data, $where ];

				return $this->failUpdate ? false : 1;
			}

			public function delete( $table, $where, $where_format = null ) {
				return 1;
			}
		};
	}

	private function seedEntry( $entry_id = 4, $is_read = 0 ) {
		$this->recordingWpdb->entryRow = [
			'id'         => $entry_id,
			'form_id'    => 7,
			'user_id'    => 0,
			'ip_address' => '203.0.113.9',
			'user_agent' => 'Mozilla/5.0',
			'is_read'    => $is_read,
			'created_at' => '2026-08-15 09:30:00',
		];

		$this->recordingWpdb->entryMeta = [
			[ 'meta_key' => 'field_1', 'meta_value' => maybe_serialize( 'Ada' ) ],
		];
	}

	/**
	 * Run a handler and capture the JSON response it terminated with.
	 *
	 * @param string $method Handler method name.
	 * @return \FTA_Test_Ajax_Response
	 */
	private function call( $method ) {
		try {
			( new Form_Entries() )->{$method}();
		} catch ( \FTA_Test_Ajax_Response $response ) {
			return $response;
		}

		$this->fail( $method . '() returned without sending a response.' );
	}

	/**
	 * Every update issued against the entries table.
	 *
	 * @return array[]
	 */
	private function entryUpdates() {
		return array_values(
			array_filter(
				$this->recordingWpdb->updates,
				function ( $update ) {
					return false === strpos( $update[0], 'meta' );
				}
			)
		);
	}

	public function test_mark_as_read_writes_the_new_status() {
		$this->seedEntry( 4 );

		$_POST = [ 'entry_id' => '4', 'is_read' => '1' ];

		$response = $this->call( 'ajax_mark_entry_read' );

		$this->assertTrue( $response->success );

		$updates = $this->entryUpdates();

		$this->assertCount( 1, $updates );
		$this->assertSame( [ 'is_read' => 1 ], $updates[0][1] );
		$this->assertSame( [ 'id' => 4 ], $updates[0][2] );
	}

	public function test_mark_as_unread_writes_the_new_status() {
		$this->seedEntry( 4, 1 );

		$_POST = [ 'entry_id' => '4', 'is_read' => '0' ];

		$this->assertTrue( $this->call( 'ajax_mark_entry_read' )->success );
		$this->assertSame( [ 'is_read' => 0 ], $this->entryUpdates()[0][1] );
	}

	/**
	 * jQuery serialises booleans as "false", which a plain (bool) cast reads
	 * as true - the opposite of what was asked for.
	 */
	public function test_a_string_false_marks_the_entry_unread() {
		$this->seedEntry( 4, 1 );

		$_POST = [ 'entry_id' => '4', 'is_read' => 'false' ];

		$this->call( 'ajax_mark_entry_read' );

		$this->assertSame( [ 'is_read' => 0 ], $this->entryUpdates()[0][1] );
	}

	public function test_the_new_status_is_returned_so_the_row_can_be_redrawn() {
		$this->seedEntry( 4 );

		$_POST = [ 'entry_id' => '4', 'is_read' => '1' ];

		$this->assertTrue( $this->call( 'ajax_mark_entry_read' )->data['is_read'] );
	}

	public function test_marking_an_unknown_entry_fails() {
		$this->recordingWpdb->entryRow = null;

		$_POST = [ 'entry_id' => '999', 'is_read' => '1' ];

		$this->assertFalse( $this->call( 'ajax_mark_entry_read' )->success );
		$this->assertSame( [], $this->entryUpdates() );
	}

	public function test_marking_without_an_entry_id_fails() {
		$_POST = [ 'is_read' => '1' ];

		$this->assertFalse( $this->call( 'ajax_mark_entry_read' )->success );
		$this->assertSame( [], $this->entryUpdates() );
	}

	public function test_a_failed_write_is_reported_as_a_failure() {
		$this->seedEntry( 4 );
		$this->recordingWpdb->failUpdate = true;

		$_POST = [ 'entry_id' => '4', 'is_read' => '1' ];

		$this->assertFalse( $this->call( 'ajax_mark_entry_read' )->success );
	}

	public function test_marking_requires_the_manage_options_capability() {
		$this->seedEntry( 4 );
		$GLOBALS['fta_test_current_user_can'] = false;

		$_POST = [ 'entry_id' => '4', 'is_read' => '1' ];

		$this->assertFalse( $this->call( 'ajax_mark_entry_read' )->success );
		$this->assertSame( [], $this->entryUpdates() );
	}

	public function test_export_produces_a_flattened_csv() {
		$this->recordingWpdb->formRow = [
			'id'         => 7,
			'title'      => 'Contact',
			'fields'     => wp_json_encode( [ [ 'id' => 'field_1', 'type' => 'text', 'label' => 'Your name' ] ] ),
			'settings'   => wp_json_encode( [] ),
			'status'     => 'active',
			'created_at' => '2026-08-01 00:00:00',
		];

		$this->recordingWpdb->entryRows = [
			[
				'id'         => 4,
				'form_id'    => 7,
				'user_id'    => 0,
				'ip_address' => '203.0.113.9',
				'user_agent' => 'Mozilla/5.0',
				'is_read'    => 0,
				'created_at' => '2026-08-15 09:30:00',
			],
		];

		$this->recordingWpdb->entryMeta = [
			[ 'meta_key' => 'field_1', 'meta_value' => maybe_serialize( 'Ada' ) ],
		];

		$_POST = [ 'form_id' => '7' ];

		$response = $this->call( 'ajax_export_entries' );

		$this->assertTrue( $response->success );
		$this->assertStringContainsString( 'Your name', $response->data['csv'] );
		$this->assertStringContainsString( 'Ada', $response->data['csv'] );
		$this->assertStringNotContainsString( 'Array', $response->data['csv'] );
		$this->assertStringEndsWith( '.csv', $response->data['filename'] );
	}

	public function test_export_of_a_form_with_no_entries_fails_cleanly() {
		$_POST = [ 'form_id' => '7' ];

		$this->assertFalse( $this->call( 'ajax_export_entries' )->success );
	}

	public function test_export_requires_the_manage_options_capability() {
		$GLOBALS['fta_test_current_user_can'] = false;

		$_POST = [ 'form_id' => '7' ];

		$this->assertFalse( $this->call( 'ajax_export_entries' )->success );
	}
}
