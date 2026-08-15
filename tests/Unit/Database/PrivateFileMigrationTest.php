<?php
/**
 * Migration of pre-1.0.5 public files into the private vault.
 *
 * Files already sitting in wp-content/uploads/formtura are exactly the ones
 * this release exists to protect, so an upgrade has to move them. Two
 * properties matter: a source file is removed only once the destination is
 * verified, and a failed migration leaves everything intact so it can be
 * retried rather than losing data halfway.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Frontend\File_Storage;
use Formtura\Tests\TestCase;

class PrivateFileMigrationTest extends TestCase {

	/**
	 * @var File_Storage
	 */
	private $storage;

	/**
	 * @var string
	 */
	private $vaultRoot;

	/**
	 * @var string
	 */
	private $uploadBase;

	protected function setUp(): void {
		parent::setUp();

		$this->vaultRoot  = sys_get_temp_dir() . '/formtura-vault-' . uniqid( '', true );
		$this->uploadBase = sys_get_temp_dir() . '/formtura-uploads-' . uniqid( '', true );

		$GLOBALS['fta_test_upload_basedir'] = $this->uploadBase;

		$this->storage = new File_Storage( $this->vaultRoot );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_upload_basedir'] );

		$this->removeTree( $this->vaultRoot );
		$this->removeTree( $this->uploadBase );

		parent::tearDown();
	}

	private function removeTree( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);

		foreach ( $items as $item ) {
			$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
		}

		rmdir( $dir );
	}

	/**
	 * Write a file into the legacy public tree.
	 *
	 * @return string Absolute legacy path.
	 */
	private function seedLegacyFile( $relative = '2026/08/file.png', $contents = 'png-bytes' ) {
		$path = $this->storage->get_legacy_root() . '/' . $relative;

		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0777, true );
		}

		file_put_contents( $path, $contents );

		return $path;
	}

	public function test_legacy_file_is_moved_into_the_vault() {
		$legacy = $this->seedLegacyFile();

		$this->assertTrue( $this->storage->migrate_legacy_files() );

		$this->assertFileDoesNotExist( $legacy );
		$this->assertFileExists( $this->storage->get_site_root() . '/2026/08/file.png' );
		$this->assertSame( 'png-bytes', file_get_contents( $this->storage->get_site_root() . '/2026/08/file.png' ) );
	}

	/**
	 * Legacy metadata is not rewritten, so an old record must keep resolving
	 * after its file moves - and must resolve to the new location.
	 */
	public function test_legacy_record_resolves_after_migration() {
		$legacy = $this->seedLegacyFile();

		$this->storage->migrate_legacy_files();

		$resolved = $this->storage->resolve( [
			'file' => $legacy,
			'url'  => 'https://example.test/wp-content/uploads/formtura/2026/08/file.png',
		] );

		$this->assertSame( $this->storage->get_site_root() . '/2026/08/file.png', $resolved );
	}

	/**
	 * Before migration runs, an old record must still resolve to its original
	 * location rather than failing outright.
	 */
	public function test_legacy_record_resolves_before_migration() {
		$legacy = $this->seedLegacyFile();

		$this->assertSame( $legacy, $this->storage->resolve( [ 'file' => $legacy ] ) );
	}

	public function test_year_month_structure_is_preserved() {
		$this->seedLegacyFile( '2024/01/old.png' );
		$this->seedLegacyFile( '2026/12/new.png' );

		$this->assertTrue( $this->storage->migrate_legacy_files() );

		$this->assertFileExists( $this->storage->get_site_root() . '/2024/01/old.png' );
		$this->assertFileExists( $this->storage->get_site_root() . '/2026/12/new.png' );
	}

	/**
	 * An existing destination must not be overwritten by a legacy file of the
	 * same name.
	 */
	public function test_existing_destination_is_not_overwritten() {
		$this->seedLegacyFile( '2026/08/file.png', 'legacy' );

		$dest = $this->storage->get_site_root() . '/2026/08/file.png';
		mkdir( dirname( $dest ), 0700, true );
		file_put_contents( $dest, 'already-here' );

		$this->storage->migrate_legacy_files();

		$this->assertSame( 'already-here', file_get_contents( $dest ) );
	}

	/**
	 * The guard files the old directory carried are plugin-written protection,
	 * not user data, and must not be migrated as if they were uploads.
	 */
	public function test_guard_files_are_not_migrated() {
		$root = $this->storage->get_legacy_root();
		mkdir( $root, 0777, true );
		file_put_contents( $root . '/.htaccess', 'deny' );
		file_put_contents( $root . '/index.php', '<?php' );

		$this->storage->migrate_legacy_files();

		$this->assertFileDoesNotExist( $this->storage->get_site_root() . '/.htaccess' );
		$this->assertFileDoesNotExist( $this->storage->get_site_root() . '/index.php' );
	}

	/**
	 * Nothing to migrate is a success, not a failure - otherwise a fresh
	 * install could never advance its database version.
	 */
	public function test_missing_legacy_directory_is_a_success() {
		$this->assertTrue( $this->storage->migrate_legacy_files() );
	}

	public function test_empty_legacy_directories_are_removed() {
		$this->seedLegacyFile();

		$this->storage->migrate_legacy_files();

		$this->assertDirectoryDoesNotExist( $this->storage->get_legacy_root() . '/2026/08' );
	}

	/**
	 * A file that cannot be moved must be left where it is and the migration
	 * reported as failed, so the upgrade retries rather than silently losing
	 * it or advancing the database version past unfinished work.
	 *
	 * Failure is injected by making the vault unwritable, which is the
	 * realistic cause: a host where the vault's parent cannot be written.
	 */
	public function test_unwritable_vault_is_reported_and_retains_the_source() {
		$legacy = $this->seedLegacyFile();

		mkdir( $this->vaultRoot, 0500, true );

		// Running as root ignores directory permissions entirely, so this
		// property cannot be exercised there.
		if ( is_writable( $this->vaultRoot ) ) {
			chmod( $this->vaultRoot, 0700 );
			$this->markTestSkipped( 'Cannot simulate an unwritable directory as this user.' );
		}

		$result = $this->storage->migrate_legacy_files();

		chmod( $this->vaultRoot, 0700 );

		$this->assertFalse( $result, 'A migration that could not move a file must report failure.' );
		$this->assertFileExists( $legacy, 'The legacy file must survive a failed migration.' );
	}
}
