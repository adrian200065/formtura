<?php
/**
 * Tests for the destructive-uninstall guard.
 *
 * The routine this replaces read `fta_keep_data_on_uninstall`, an option the
 * settings UI never wrote. get_option() therefore always returned false, the
 * "keep data" branch was never taken, and every uninstall dropped every table
 * regardless of what the administrator had chosen. These tests pin the
 * inverted, fail-safe contract: delete only on an explicit opt-in.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit;

use Formtura\Tests\TestCase;
use Formtura\Uninstall;

class UninstallTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['fta_test_options']         = [];
		$GLOBALS['fta_test_deleted_options'] = [];
		$GLOBALS['fta_test_dropped_tables']  = [];

		global $wpdb;
		$wpdb = $this->getRecordingWpdb();
	}

	protected function tearDown(): void {
		unset(
			$GLOBALS['fta_test_options'],
			$GLOBALS['fta_test_deleted_options'],
			$GLOBALS['fta_test_dropped_tables']
		);

		parent::tearDown();
	}

	/**
	 * A $wpdb whose query() captures the table name of any DROP TABLE.
	 */
	private function getRecordingWpdb() {
		return new class {
			public $prefix = 'wp_';

			public function query( $query ) {
				if ( preg_match( '/DROP TABLE IF EXISTS\s+(\S+)/i', $query, $matches ) ) {
					$GLOBALS['fta_test_dropped_tables'][] = $matches[1];
				}

				return true;
			}
		};
	}

	public function test_missing_setting_retains_all_data() {
		$GLOBALS['fta_test_options']['fta_settings'] = [];

		Uninstall::run();

		$this->assertSame( [], $GLOBALS['fta_test_dropped_tables'] );
		$this->assertSame( [], $GLOBALS['fta_test_deleted_options'] );
	}

	public function test_absent_settings_option_retains_all_data() {
		Uninstall::run();

		$this->assertSame( [], $GLOBALS['fta_test_dropped_tables'] );
		$this->assertSame( [], $GLOBALS['fta_test_deleted_options'] );
	}

	public function test_false_setting_retains_all_data() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'delete_data_on_uninstall' => false ];

		Uninstall::run();

		$this->assertSame( [], $GLOBALS['fta_test_dropped_tables'] );
		$this->assertSame( [], $GLOBALS['fta_test_deleted_options'] );
	}

	/**
	 * The obsolete standalone option must no longer drive the decision, even
	 * when a stale value is still present in the database.
	 */
	public function test_legacy_keep_data_option_does_not_trigger_deletion() {
		$GLOBALS['fta_test_options']['fta_keep_data_on_uninstall'] = false;
		$GLOBALS['fta_test_options']['fta_settings']               = [];

		Uninstall::run();

		$this->assertSame( [], $GLOBALS['fta_test_dropped_tables'] );
	}

	public function test_true_setting_deletes_formtura_data() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'delete_data_on_uninstall' => true ];

		Uninstall::run();

		$this->assertSame(
			[ 'wp_fta_forms', 'wp_fta_entries', 'wp_fta_entry_meta' ],
			$GLOBALS['fta_test_dropped_tables']
		);
		$this->assertContains( 'fta_settings', $GLOBALS['fta_test_deleted_options'] );
		$this->assertContains( 'fta_db_version', $GLOBALS['fta_test_deleted_options'] );
	}

	/**
	 * The obsolete option is removed only on an explicitly destructive run.
	 */
	public function test_true_setting_removes_obsolete_legacy_option() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'delete_data_on_uninstall' => true ];

		Uninstall::run();

		$this->assertContains( 'fta_keep_data_on_uninstall', $GLOBALS['fta_test_deleted_options'] );
	}
}
