<?php
/**
 * Form deletion AJAX handler.
 *
 * The forms screen rendered a Delete button (see forms-list.php) with no
 * JavaScript handler bound to it and no `wp_ajax_fta_delete_form` action
 * registered anywhere - fta_delete_form() existed and was fully tested at
 * the database layer (see FormFileCleanupTest), but nothing in the admin UI
 * could ever reach it. This covers the endpoint that closes that gap.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Form_Builder;
use Formtura\Tests\TestCase;

class FormDeleteActionTest extends TestCase {

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
	 * A $wpdb double serving a form with no entries, recording deletes.
	 */
	private function makeWpdb() {
		return new class {
			public $prefix     = 'wp_';
			public $deletes    = [];
			public $failDelete = false;

			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					if ( is_array( $arg ) ) {
						continue;
					}

					$query = preg_replace( '/%d|%s/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			public function get_col( $query, $x = 0 ) {
				// No entries for this form.
				return [];
			}

			public function get_var( $query = null, $x = 0, $y = 0 ) {
				return null;
			}

			public function query( $query ) {
				return true;
			}

			public function delete( $table, $where, $where_format = null ) {
				$this->deletes[] = [ $table, $where ];

				if ( $this->failDelete && false !== strpos( $table, 'fta_forms' ) ) {
					return false;
				}

				return 1;
			}
		};
	}

	/**
	 * Run a handler and capture the JSON response it terminated with.
	 *
	 * @param string $method Handler method name.
	 * @return \FTA_Test_Ajax_Response
	 */
	private function call( $method ) {
		try {
			( new Form_Builder() )->{$method}();
		} catch ( \FTA_Test_Ajax_Response $response ) {
			return $response;
		}

		$this->fail( $method . '() returned without sending a response.' );
	}

	public function test_deleting_a_form_removes_its_row() {
		$_POST = [ 'form_id' => '3' ];

		$response = $this->call( 'ajax_delete_form' );

		$this->assertTrue( $response->success );
		$this->assertSame( [ [ 'wp_fta_forms', [ 'id' => 3 ] ] ], $this->recordingWpdb->deletes );
	}

	public function test_deleting_without_a_form_id_fails() {
		$_POST = [];

		$response = $this->call( 'ajax_delete_form' );

		$this->assertFalse( $response->success );
		$this->assertSame( [], $this->recordingWpdb->deletes );
	}

	public function test_a_failed_delete_is_reported_as_a_failure() {
		$this->recordingWpdb->failDelete = true;

		$_POST = [ 'form_id' => '3' ];

		$this->assertFalse( $this->call( 'ajax_delete_form' )->success );
	}

	public function test_deleting_requires_the_manage_options_capability() {
		$GLOBALS['fta_test_current_user_can'] = false;

		$_POST = [ 'form_id' => '3' ];

		$this->assertFalse( $this->call( 'ajax_delete_form' )->success );
		$this->assertSame( [], $this->recordingWpdb->deletes );
	}

	public function test_deleting_requires_a_valid_nonce() {
		$GLOBALS['fta_test_ajax_referer_valid'] = false;

		$_POST = [ 'form_id' => '3' ];

		$this->assertFalse( $this->call( 'ajax_delete_form' )->success );
		$this->assertSame( [], $this->recordingWpdb->deletes );
	}
}
