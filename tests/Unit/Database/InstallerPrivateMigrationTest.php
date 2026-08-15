<?php
/**
 * Installer private-file migration coordination tests.
 *
 * The database version must advance only when the file migration actually
 * completed. Advancing past a failed migration would record the upgrade as
 * done while the old uploads are still sitting in the public directory, and
 * nothing would ever retry the move.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Database\Installer;
use Formtura\Frontend\File_Storage;
use Formtura\Tests\TestCase;

/**
 * A storage service whose migration outcome is dictated by the test.
 */
class ControlledMigrationStorage extends File_Storage {

	private $result;

	public function __construct( $result ) {
		parent::__construct( sys_get_temp_dir() . '/formtura-controlled-' . uniqid( '', true ) );

		$this->result = $result;
	}

	public function migrate_legacy_files() {
		return $this->result;
	}
}

class InstallerPrivateMigrationTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['fta_test_options'] = [ 'fta_db_version' => '1.0.4' ];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );

		parent::tearDown();
	}

	private function runMigrations( $storage ) {
		$method = new \ReflectionMethod( Installer::class, 'run_migrations' );
		$method->setAccessible( true );

		return $method->invoke( null, $storage );
	}

	public function test_private_file_migration_failure_is_reported_for_retry() {
		$this->assertFalse( $this->runMigrations( new ControlledMigrationStorage( false ) ) );
	}

	public function test_private_file_migration_success_allows_version_advance() {
		$this->assertTrue( $this->runMigrations( new ControlledMigrationStorage( true ) ) );
	}

	/**
	 * A failure must leave a marker for the administrator notice, and success
	 * must clear it so a resolved problem stops being reported.
	 */
	public function test_failure_records_and_success_clears_the_admin_marker() {
		$this->runMigrations( new ControlledMigrationStorage( false ) );
		$this->assertTrue( (bool) get_option( 'fta_private_migration_failed' ) );

		$this->runMigrations( new ControlledMigrationStorage( true ) );
		$this->assertFalse( (bool) get_option( 'fta_private_migration_failed' ) );
	}

	/**
	 * An install already at the current version has nothing to migrate, and
	 * must not be reported as failed.
	 */
	public function test_current_version_skips_the_migration() {
		$GLOBALS['fta_test_options']['fta_db_version'] = Installer::DB_VERSION;

		$this->assertTrue( $this->runMigrations( new ControlledMigrationStorage( false ) ) );
	}
}
