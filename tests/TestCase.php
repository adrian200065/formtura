<?php
/**
 * Base Test Case for Formtura tests
 *
 * @package Formtura
 */

namespace Formtura\Tests;

use PHPUnit\Framework\TestCase as PHPUnit_TestCase;

/**
 * Base test case class
 */
class TestCase extends PHPUnit_TestCase {

	/**
	 * Setup before each test
	 */
	protected function setUp(): void {
		parent::setUp();

		// Reset any global state
		global $wpdb;
		$wpdb = null;
	}

	/**
	 * Teardown after each test
	 */
	protected function tearDown(): void {
		parent::tearDown();

		// Clean up
		global $wpdb;
		$wpdb = null;
	}

	/**
	 * Create a mock WordPress database object
	 *
	 * @return object Mock wpdb object
	 */
	protected function getMockWpdb() {
		return new class {
			public $prefix = 'wp_';
			public $last_query = '';
			public $last_error = '';
			public $insert_id = 0;

			public function prepare( $query, ...$args ) {
				$this->last_query = $query;
				return vsprintf( str_replace( '%s', "'%s'", str_replace( '%d', '%d', $query ) ), $args );
			}

			public function get_results( $query, $output = OBJECT ) {
				$this->last_query = $query;
				return [];
			}

			public function get_row( $query, $output = OBJECT, $y = 0 ) {
				$this->last_query = $query;
				return null;
			}

			public function get_var( $query = null, $x = 0, $y = 0 ) {
				$this->last_query = $query;
				return null;
			}

			public function query( $query ) {
				$this->last_query = $query;
				return false;
			}

			public function insert( $table, $data, $format = null ) {
				$this->insert_id++;
				return true;
			}

			public function update( $table, $data, $where, $format = null, $where_format = null ) {
				return true;
			}

			public function delete( $table, $where, $where_format = null ) {
				return true;
			}
		};
	}
}
