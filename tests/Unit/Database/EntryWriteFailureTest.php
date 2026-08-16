<?php
/**
 * Entry writes must succeed as a whole or not at all.
 *
 * create() used to insert the entry row, fire save_entry_meta() without
 * looking at what it returned, and hand back the new ID regardless. A meta
 * insert that failed - a full disk, a lock timeout, a value over
 * max_allowed_packet - therefore produced an entry row with missing or partial
 * field data that the caller was told was a complete success, so notifications
 * went out and uploaded files were retained for an entry whose answers were
 * gone.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Database\Entries_DB;
use Formtura\Tests\TestCase;

class EntryWriteFailureTest extends TestCase {

	/**
	 * @var object
	 */
	private $recordingWpdb;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb                = $this->makeWpdb();
		$this->recordingWpdb = $wpdb;
	}

	/**
	 * A $wpdb that records every statement and can be told which write fails.
	 */
	private function makeWpdb() {
		return new class {
			public $prefix    = 'wp_';
			public $insert_id = 0;

			/** @var bool Make the entries-table insert fail. */
			public $failEntryInsert = false;

			/** @var int|null Fail every meta insert after this many succeed. */
			public $metaInsertsBeforeFailure = null;

			/** @var bool Make the meta wipe that precedes a re-save fail. */
			public $failMetaDelete = false;

			/** @var bool Make the entries-table update fail. */
			public $failEntryUpdate = false;

			public $queries    = [];
			public $inserts    = [];
			public $deletes    = [];
			public $updates    = [];
			private $metaCount = 0;

			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					$query = preg_replace( '/%d|%s/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			public function query( $query ) {
				$this->queries[] = $query;

				return true;
			}

			public function get_row( $query, $output = ARRAY_A, $y = 0 ) {
				return null;
			}

			public function get_results( $query, $output = ARRAY_A ) {
				return [];
			}

			public function get_col( $query, $x = 0 ) {
				return [];
			}

			public function get_var( $query = null, $x = 0, $y = 0 ) {
				return null;
			}

			public function insert( $table, $data, $format = null ) {
				$this->inserts[] = [ $table, $data ];

				if ( false === strpos( $table, 'meta' ) ) {
					if ( $this->failEntryInsert ) {
						return false;
					}

					$this->insert_id = 77;

					return 1;
				}

				$this->metaCount++;

				if ( null !== $this->metaInsertsBeforeFailure && $this->metaCount > $this->metaInsertsBeforeFailure ) {
					return false;
				}

				return 1;
			}

			public function update( $table, $data, $where, $format = null, $where_format = null ) {
				$this->updates[] = [ $table, $data, $where ];

				return $this->failEntryUpdate ? false : 1;
			}

			public function delete( $table, $where, $where_format = null ) {
				$this->deletes[] = [ $table, $where ];

				if ( $this->failMetaDelete && false !== strpos( $table, 'meta' ) ) {
					return false;
				}

				return 1;
			}
		};
	}

	private function entries() {
		return new Entries_DB();
	}

	private function submission() {
		return [
			'form_id' => 3,
			'data'    => [
				'field_1' => 'Ada',
				'field_2' => 'ada@example.com',
				'field_3' => 'Hello',
			],
		];
	}

	/**
	 * Every statement issued against the database, in order.
	 *
	 * @return string[]
	 */
	private function statements() {
		return $this->recordingWpdb->queries;
	}

	public function test_successful_create_commits_and_returns_the_new_id() {
		$this->assertSame( 77, $this->entries()->create( $this->submission() ) );

		$this->assertSame( [ 'START TRANSACTION', 'COMMIT' ], $this->statements() );
	}

	/**
	 * The core of this blocker: a failed meta write must not be reported as a
	 * successful submission.
	 */
	public function test_failed_meta_insert_reports_failure() {
		$this->recordingWpdb->metaInsertsBeforeFailure = 1;

		$this->assertFalse( $this->entries()->create( $this->submission() ) );
	}

	public function test_failed_meta_insert_rolls_the_transaction_back() {
		$this->recordingWpdb->metaInsertsBeforeFailure = 1;

		$this->entries()->create( $this->submission() );

		$this->assertContains( 'ROLLBACK', $this->statements() );
		$this->assertNotContains( 'COMMIT', $this->statements() );
	}

	/**
	 * ROLLBACK is a no-op on a non-transactional storage engine, so the half
	 * written rows are also removed explicitly. Without this an entry row with
	 * no field data survives on MyISAM installs.
	 */
	public function test_failed_meta_insert_also_removes_the_orphaned_rows() {
		$this->recordingWpdb->metaInsertsBeforeFailure = 1;

		$this->entries()->create( $this->submission() );

		$tables = array_column( $this->recordingWpdb->deletes, 0 );

		$this->assertContains( 'wp_fta_entries', $tables );
		$this->assertContains( 'wp_fta_entry_meta', $tables );
	}

	public function test_meta_is_not_written_when_the_entry_row_fails() {
		$this->recordingWpdb->failEntryInsert = true;

		$this->assertFalse( $this->entries()->create( $this->submission() ) );
		$this->assertContains( 'ROLLBACK', $this->statements() );

		$meta_inserts = array_filter(
			$this->recordingWpdb->inserts,
			function ( $insert ) {
				return false !== strpos( $insert[0], 'meta' );
			}
		);

		$this->assertSame( [], $meta_inserts );
	}

	/**
	 * save_entry_meta() wipes the existing rows before re-inserting. A failed
	 * wipe would otherwise leave the old answers in place beside the new ones.
	 */
	public function test_failed_meta_wipe_reports_failure() {
		$this->recordingWpdb->failMetaDelete = true;

		$this->assertFalse( $this->entries()->create( $this->submission() ) );
	}

	public function test_failed_update_reports_failure_and_rolls_back() {
		$this->recordingWpdb->failEntryUpdate = true;

		$this->assertFalse( $this->entries()->update( 4, [ 'is_read' => 1 ] ) );
		$this->assertContains( 'ROLLBACK', $this->statements() );
	}

	public function test_failed_meta_write_during_update_reports_failure() {
		$this->recordingWpdb->metaInsertsBeforeFailure = 0;

		$this->assertFalse( $this->entries()->update( 4, [ 'data' => [ 'field_1' => 'Ada' ] ] ) );
		$this->assertContains( 'ROLLBACK', $this->statements() );
	}

	/**
	 * An update carrying only field data used to return false without writing
	 * anything, because the guard for "nothing to do" only looked at the
	 * entries-table columns.
	 */
	public function test_update_with_only_field_data_writes_the_meta() {
		$this->assertTrue( $this->entries()->update( 4, [ 'data' => [ 'field_1' => 'Ada' ] ] ) );

		$meta_inserts = array_filter(
			$this->recordingWpdb->inserts,
			function ( $insert ) {
				return false !== strpos( $insert[0], 'meta' );
			}
		);

		$this->assertCount( 1, $meta_inserts );
	}

	public function test_update_with_nothing_to_write_is_a_no_op() {
		$this->assertFalse( $this->entries()->update( 4, [] ) );
		$this->assertSame( [], $this->statements() );
	}
}
