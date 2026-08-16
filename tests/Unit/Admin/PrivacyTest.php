<?php
/**
 * Formtura's WordPress Privacy API integration: finding, exporting, and
 * erasing entries for a requested email address, and purging entries past
 * the configured retention window.
 *
 * Entries aren't tied to a fixed "email" schema field, so matching unions two
 * strategies: the WordPress user account behind a logged-in submission, and
 * any email-type field's answer on a guest submission.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Privacy;
use Formtura\Tests\TestCase;

class PrivacyTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_users'], $GLOBALS['fta_test_options'], $GLOBALS['fta_test_actions'], $GLOBALS['fta_test_filters'] );

		parent::tearDown();
	}

	/**
	 * @return object A fake Entries_DB exposing exactly the methods Privacy
	 *         uses, so no database is involved.
	 */
	private function makeEntries() {
		return new class {
			public $byUser        = [];
			public $byMeta        = [];
			public $olderThan     = [];
			public $store         = [];
			public $deleted       = [];
			public $deleteResult  = true;
			// IDs that should fail deletion (delete() returns false and does
			// not mutate the match sources for these), even though
			// $deleteResult is true for everything else.
			public $failIds        = [];
			public $metaMatchCalls = [];
			public $olderThanCalls = [];

			public function get_ids_by_user( $user_id ) {
				return isset( $this->byUser[ $user_id ] ) ? $this->byUser[ $user_id ] : [];
			}

			public function get_ids_by_meta_match( $form_id, $meta_keys, $value ) {
				$this->metaMatchCalls[] = [ $form_id, $meta_keys, $value ];
				$key = $form_id . '|' . strtolower( $value );

				return isset( $this->byMeta[ $key ] ) ? $this->byMeta[ $key ] : [];
			}

			public function get_ids_older_than( $cutoff ) {
				$this->olderThanCalls[] = $cutoff;

				return $this->olderThan;
			}

			public function get( $id ) {
				return isset( $this->store[ $id ] ) ? $this->store[ $id ] : null;
			}

			/**
			 * Mirrors a real DB: a successful delete removes $id from
			 * whatever match source(s) it came from, so a subsequent
			 * matching_entry_ids() re-query (as erase_data() does on every
			 * page) reflects the deletion.
			 */
			public function delete( $id ) {
				$this->deleted[] = $id;

				if ( in_array( $id, $this->failIds, true ) || ! $this->deleteResult ) {
					return false;
				}

				foreach ( $this->byUser as &$ids ) {
					$ids = array_values( array_diff( $ids, [ $id ] ) );
				}
				unset( $ids );

				foreach ( $this->byMeta as &$ids ) {
					$ids = array_values( array_diff( $ids, [ $id ] ) );
				}
				unset( $ids );

				return true;
			}
		};
	}

	/**
	 * @param array $forms Forms in Forms_DB::get_all() shape.
	 * @return object
	 */
	private function makeForms( array $forms ) {
		return new class( $forms ) {
			private $forms;

			public function __construct( $forms ) {
				$this->forms = $forms;
			}

			public function get_all( $args = [] ) {
				return $this->forms;
			}
		};
	}

	public function test_exporter_matches_entries_by_wp_user_account() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$entries = $this->makeEntries();
		$entries->byUser[5]    = [ 101 ];
		$entries->store[101]   = [
			'id'         => 101,
			'form_id'    => 1,
			'created_at' => '2026-01-01 00:00:00',
			'ip_address' => '1.2.3.4',
			'user_agent' => 'UA',
			'data'       => [ 'name' => 'Owner' ],
		];

		$privacy = new Privacy( $entries, $this->makeForms( [] ) );
		$result  = $privacy->export_data( 'owner@example.com', 1 );

		$this->assertCount( 1, $result['data'] );
		$this->assertSame( 'formtura-entry-101', $result['data'][0]['item_id'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_exporter_matches_entries_by_an_email_type_field_case_insensitively() {
		$forms = [
			[
				'id'     => 2,
				'fields' => [
					[ 'type' => 'email', 'name' => 'contact_email' ],
					[ 'type' => 'text', 'name' => 'message' ],
				],
			],
		];

		$entries = $this->makeEntries();
		$entries->byMeta['2|jane@example.com'] = [ 55 ];
		$entries->store[55] = [
			'id'         => 55,
			'form_id'    => 2,
			'created_at' => '2026-01-02 00:00:00',
			'ip_address' => '5.6.7.8',
			'user_agent' => 'UA',
			'data'       => [ 'contact_email' => 'jane@example.com', 'message' => 'Hi' ],
		];

		$privacy = new Privacy( $entries, $this->makeForms( $forms ) );
		$result  = $privacy->export_data( 'JANE@EXAMPLE.COM', 1 );

		$this->assertCount( 1, $result['data'] );
		$this->assertSame( [ 2, [ 'contact_email' ], 'JANE@EXAMPLE.COM' ], $entries->metaMatchCalls[0] );
	}

	public function test_exporter_does_not_match_an_unrelated_email() {
		$forms = [
			[ 'id' => 2, 'fields' => [ [ 'type' => 'email', 'name' => 'contact_email' ] ] ],
		];

		$privacy = new Privacy( $this->makeEntries(), $this->makeForms( $forms ) );
		$result  = $privacy->export_data( 'nobody@example.com', 1 );

		$this->assertSame( [], $result['data'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_exporter_paginates_and_sets_done_on_the_final_page() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$entries = $this->makeEntries();
		$ids     = range( 1, 25 );
		$entries->byUser[5] = $ids;

		foreach ( $ids as $id ) {
			$entries->store[ $id ] = [
				'id'         => $id,
				'form_id'    => 1,
				'created_at' => '2026-01-01 00:00:00',
				'ip_address' => '1.2.3.4',
				'user_agent' => 'UA',
				'data'       => [],
			];
		}

		$privacy = new Privacy( $entries, $this->makeForms( [] ) );

		$page1 = $privacy->export_data( 'owner@example.com', 1 );
		$this->assertCount( 20, $page1['data'] );
		$this->assertFalse( $page1['done'] );

		$page2 = $privacy->export_data( 'owner@example.com', 2 );
		$this->assertCount( 5, $page2['data'] );
		$this->assertTrue( $page2['done'] );
	}

	public function test_matching_entry_ids_returns_a_stable_ascending_order_regardless_of_source_order() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$forms = [
			[ 'id' => 2, 'fields' => [ [ 'type' => 'email', 'name' => 'contact_email' ] ] ],
		];

		$entries = $this->makeEntries();
		// The WP-user match and the email-field match return IDs that, if
		// simply concatenated with no sort, would come back out of ascending
		// order - this is exactly the interleaving that a missing ORDER BY
		// (or a DISTINCT query plan change) could produce between two paged
		// calls to export_data().
		$entries->byUser[5]                       = [ 50, 10 ];
		$entries->byMeta['2|owner@example.com']    = [ 30, 5, 40 ];
		foreach ( [ 5, 10, 30, 40, 50 ] as $id ) {
			$entries->store[ $id ] = [
				'id'         => $id,
				'form_id'    => 2,
				'created_at' => '2026-01-01 00:00:00',
				'ip_address' => '1.2.3.4',
				'user_agent' => 'UA',
				'data'       => [],
			];
		}

		$privacy = new Privacy( $entries, $this->makeForms( $forms ) );

		$reflection = new \ReflectionMethod( Privacy::class, 'matching_entry_ids' );
		$reflection->setAccessible( true );

		$ids = $reflection->invoke( $privacy, 'owner@example.com' );

		$this->assertSame( [ 5, 10, 30, 40, 50 ], $ids );
	}

	public function test_eraser_deletes_matched_entries_and_reports_items_removed() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$entries = $this->makeEntries();
		$entries->byUser[5]  = [ 101, 102 ];
		$entries->store[101] = [ 'id' => 101, 'form_id' => 1, 'data' => [] ];
		$entries->store[102] = [ 'id' => 102, 'form_id' => 1, 'data' => [] ];

		$privacy = new Privacy( $entries, $this->makeForms( [] ) );
		$result  = $privacy->erase_data( 'owner@example.com', 1 );

		$this->assertSame( [ 101, 102 ], $entries->deleted );
		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_eraser_is_a_no_op_when_nothing_matches() {
		$privacy = new Privacy( $this->makeEntries(), $this->makeForms( [] ) );
		$result  = $privacy->erase_data( 'nobody@example.com', 1 );

		$this->assertSame( [], $result['messages'] );
		$this->assertFalse( $result['items_removed'] );
		$this->assertTrue( $result['done'] );
	}

	/**
	 * erase_data() is a mutating operation: deleting a page's entries shrinks
	 * the underlying match set, so matching_entry_ids() returns fewer IDs on
	 * the next page. WordPress increments $page independent of how much
	 * erasure removed, so an offset computed from $page (correct for the
	 * read-only exporter) would walk past entries that scrolled to the front
	 * of the now-shorter list. With 21 matches and PAGE_SIZE=20, an
	 * offset-based page 2 would slice the 1-item re-query at offset 20 -
	 * an empty result - silently leaving entry 21 un-erased while reporting
	 * done. This test only catches that because the fake's delete() removes
	 * the deleted ID from byUser, mirroring how a real DB's next query would
	 * reflect the deletion.
	 */
	public function test_eraser_paginates_correctly_when_deletion_shrinks_the_match_set() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$entries = $this->makeEntries();
		$entries->byUser[5] = range( 1, 21 );

		$privacy = new Privacy( $entries, $this->makeForms( [] ) );

		$page1 = $privacy->erase_data( 'owner@example.com', 1 );
		$this->assertSame( range( 1, 20 ), $entries->deleted );
		$this->assertFalse( $page1['done'] );

		$page2 = $privacy->erase_data( 'owner@example.com', 2 );
		$this->assertSame( range( 1, 21 ), $entries->deleted );
		$this->assertTrue( $page2['done'] );
	}

	public function test_eraser_reports_items_retained_when_a_deletion_fails() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$entries = $this->makeEntries();
		$entries->byUser[5] = [ 101, 102 ];
		$entries->failIds   = [ 102 ];

		$privacy = new Privacy( $entries, $this->makeForms( [] ) );
		$result  = $privacy->erase_data( 'owner@example.com', 1 );

		$this->assertSame( [ 101, 102 ], $entries->deleted );
		$this->assertTrue( $result['items_removed'] );
		$this->assertTrue( $result['items_retained'] );
	}

	public function test_purge_is_a_no_op_when_retention_is_disabled() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'entry_retention_days' => 0 ];

		$entries = $this->makeEntries();
		( new Privacy( $entries, $this->makeForms( [] ) ) )->purge_old_entries();

		$this->assertSame( [], $entries->olderThanCalls );
		$this->assertSame( [], $entries->deleted );
	}

	public function test_purge_deletes_entries_older_than_the_configured_window() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'entry_retention_days' => 30 ];

		$entries = $this->makeEntries();
		$entries->olderThan = [ 201, 202 ];

		$before = time();
		( new Privacy( $entries, $this->makeForms( [] ) ) )->purge_old_entries();
		$after  = time();

		$this->assertSame( [ 201, 202 ], $entries->deleted );

		$cutoff = strtotime( $entries->olderThanCalls[0] . ' UTC' );
		$this->assertGreaterThanOrEqual( $before - ( 30 * DAY_IN_SECONDS ) - 2, $cutoff );
		$this->assertLessThanOrEqual( $after - ( 30 * DAY_IN_SECONDS ) + 2, $cutoff );
	}

	public function test_constructor_registers_the_wp_privacy_hooks() {
		$GLOBALS['fta_test_actions'] = [];
		$GLOBALS['fta_test_filters'] = [];

		new Privacy( $this->makeEntries(), $this->makeForms( [] ) );

		$filterHooks = array_column( $GLOBALS['fta_test_filters'], 0 );
		$actionHooks = array_column( $GLOBALS['fta_test_actions'], 0 );

		$this->assertContains( 'wp_privacy_personal_data_exporters', $filterHooks );
		$this->assertContains( 'wp_privacy_personal_data_erasers', $filterHooks );
		$this->assertContains( 'fta_purge_old_entries_event', $actionHooks );
	}
}
