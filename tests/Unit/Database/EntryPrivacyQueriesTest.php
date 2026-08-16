<?php
/**
 * Query methods used by the WordPress Privacy API integration to find and
 * age-out entries without loading full rows.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Database\Entries_DB;
use Formtura\Tests\TestCase;

class EntryPrivacyQueriesTest extends TestCase {

	/**
	 * @var object
	 */
	private $wpdbDouble;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb             = $this->makeWpdb();
		$this->wpdbDouble = $wpdb;
	}

	private function makeWpdb() {
		return new class {
			public $prefix     = 'wp_';
			public $queries    = [];
			public $col_result = [];

			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					$query = preg_replace( '/%d|%s/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			public function get_col( $query, $x = 0 ) {
				$this->queries[] = $query;

				return $this->col_result;
			}
		};
	}

	public function test_get_ids_by_user_queries_by_user_id_and_returns_integers() {
		$this->wpdbDouble->col_result = [ '3', '7' ];

		$ids = ( new Entries_DB() )->get_ids_by_user( 42 );

		$this->assertSame( [ 3, 7 ], $ids );
		$this->assertStringContainsString( 'user_id = 42', end( $this->wpdbDouble->queries ) );
	}

	public function test_get_ids_by_meta_match_scopes_to_the_given_form_and_matches_case_insensitively() {
		$this->wpdbDouble->col_result = [ '9' ];

		$ids = ( new Entries_DB() )->get_ids_by_meta_match( 5, [ 'email', 'work_email' ], 'Jane@Example.com' );

		$this->assertSame( [ 9 ], $ids );

		$query = end( $this->wpdbDouble->queries );
		$this->assertStringContainsString( 'form_id = 5', $query );
		$this->assertStringContainsString( "IN ('email','work_email')", $query );
		$this->assertStringContainsString( "LOWER(m.meta_value) = LOWER('Jane@Example.com')", $query );
	}

	/**
	 * A field name in one form must never match an entry belonging to a
	 * different form that happens to reuse the same field name.
	 */
	public function test_get_ids_by_meta_match_is_scoped_per_form() {
		( new Entries_DB() )->get_ids_by_meta_match( 5, [ 'email' ], 'jane@example.com' );

		$this->assertStringContainsString( 'e.form_id = 5', end( $this->wpdbDouble->queries ) );
	}

	public function test_get_ids_by_meta_match_returns_empty_without_querying_when_no_email_fields() {
		$ids = ( new Entries_DB() )->get_ids_by_meta_match( 5, [], 'jane@example.com' );

		$this->assertSame( [], $ids );
		$this->assertSame( [], $this->wpdbDouble->queries );
	}

	public function test_get_ids_older_than_queries_by_created_at_and_returns_integers() {
		$this->wpdbDouble->col_result = [ '101', '102' ];

		$ids = ( new Entries_DB() )->get_ids_older_than( '2026-01-01 00:00:00' );

		$this->assertSame( [ 101, 102 ], $ids );
		$this->assertStringContainsString( "created_at < '2026-01-01 00:00:00'", end( $this->wpdbDouble->queries ) );
	}
}
