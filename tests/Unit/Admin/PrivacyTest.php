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

			public function delete( $id ) {
				$this->deleted[] = $id;

				return $this->deleteResult;
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
