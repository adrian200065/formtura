<?php
/**
 * Paging arguments must be honoured on every page.
 *
 * get_by_form() only applied per_page when page was greater than 1, so the
 * first page of any request came back capped at the default 20 rows however
 * many were asked for. Anything reading page one - the entries screen, the CSV
 * export paging through a form - silently got 20 entries and no indication
 * that its request had been ignored.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Database\Entries_DB;
use Formtura\Tests\TestCase;

class EntryPagingTest extends TestCase {

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

	private function makeWpdb() {
		return new class {
			public $prefix  = 'wp_';
			public $queries = [];

			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					$query = preg_replace( '/%d|%s/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			public function get_results( $query, $output = ARRAY_A ) {
				$this->queries[] = $query;

				return [];
			}

			public function get_row( $query, $output = ARRAY_A, $y = 0 ) {
				return null;
			}

			public function get_var( $query = null, $x = 0, $y = 0 ) {
				return null;
			}

			public function query( $query ) {
				return true;
			}
		};
	}

	/**
	 * The SELECT issued for a get_by_form() call.
	 *
	 * @param array $args Query arguments.
	 * @return string
	 */
	private function queryFor( array $args ) {
		( new Entries_DB() )->get_by_form( 7, $args );

		return end( $this->recordingWpdb->queries );
	}

	public function test_first_page_honours_the_requested_page_size() {
		$this->assertStringContainsString(
			'LIMIT 200 OFFSET 0',
			$this->queryFor( [ 'page' => 1, 'per_page' => 200 ] )
		);
	}

	public function test_later_pages_offset_by_the_requested_page_size() {
		$this->assertStringContainsString(
			'LIMIT 200 OFFSET 400',
			$this->queryFor( [ 'page' => 3, 'per_page' => 200 ] )
		);
	}

	public function test_explicit_limit_and_offset_still_work() {
		$this->assertStringContainsString(
			'LIMIT 5 OFFSET 10',
			$this->queryFor( [ 'limit' => 5, 'offset' => 10 ] )
		);
	}

	public function test_defaults_are_unchanged() {
		$this->assertStringContainsString( 'LIMIT 20 OFFSET 0', $this->queryFor( [] ) );
	}

	/**
	 * A zero or negative page size would otherwise become `LIMIT 0`, which
	 * returns nothing at all rather than the "everything" a caller passing it
	 * would expect.
	 */
	public function test_a_zero_page_size_never_becomes_an_empty_result() {
		$this->assertStringContainsString( 'LIMIT 20 OFFSET 0', $this->queryFor( [ 'per_page' => 0 ] ) );
	}
}
