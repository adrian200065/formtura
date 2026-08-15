<?php
/**
 * Tests for the private file vault.
 *
 * Two properties matter more than anything else here and are pinned first:
 *
 * 1. A stored record must not carry a public URL or an absolute filesystem
 *    path. Entry metadata is rendered in the admin and passed to notification
 *    formatting; a URL in the record is a public download link by another name.
 * 2. resolve() is the only thing standing between a stored string and a file
 *    read. Anything that escapes the vault root must come back false.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\File_Storage;
use Formtura\Tests\TestCase;

class FileStorageTest extends TestCase {

	/**
	 * @var File_Storage
	 */
	private $storage;

	/**
	 * @var string
	 */
	private $root;

	protected function setUp(): void {
		parent::setUp();

		$this->root    = sys_get_temp_dir() . '/formtura-vault-' . uniqid( '', true );
		$this->storage = new File_Storage( $this->root );
	}

	protected function tearDown(): void {
		$this->removeTree( $this->root );

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

	private function writeFile( $path, $contents ) {
		if ( ! is_dir( dirname( $path ) ) ) {
			mkdir( dirname( $path ), 0700, true );
		}

		file_put_contents( $path, $contents );

		return $path;
	}

	public function test_site_root_is_below_the_configured_root() {
		$this->assertStringStartsWith( $this->root, $this->storage->get_site_root() );
	}

	public function test_record_contains_relative_path_and_no_public_location() {
		$path = $this->storage->get_site_root() . '/2026/08/random.png';
		$this->writeFile( $path, 'png' );

		$record = $this->storage->create_record( 'signature.png', $path, 'image/png', 3 );

		$this->assertSame( '2026/08/random.png', $record['path'] );
		$this->assertSame( 'signature.png', $record['name'] );
		$this->assertSame( 'image/png', $record['type'] );
		$this->assertSame( 3, $record['size'] );
		$this->assertArrayNotHasKey( 'url', $record );
		$this->assertArrayNotHasKey( 'file', $record );
	}

	public function test_resolver_returns_absolute_path_for_stored_record() {
		$path = $this->storage->get_site_root() . '/2026/08/random.png';
		$this->writeFile( $path, 'png' );

		$this->assertSame( $path, $this->storage->resolve( [ 'path' => '2026/08/random.png' ] ) );
	}

	public function test_resolver_rejects_path_traversal() {
		$this->assertFalse( $this->storage->resolve( [ 'path' => '../outside.txt' ] ) );
	}

	public function test_resolver_rejects_nested_path_traversal() {
		$this->writeFile( dirname( $this->storage->get_site_root() ) . '/secret.txt', 'secret' );

		$this->assertFalse( $this->storage->resolve( [ 'path' => '2026/../../secret.txt' ] ) );
	}

	public function test_resolver_rejects_absolute_path() {
		$this->assertFalse( $this->storage->resolve( [ 'path' => '/etc/passwd' ] ) );
	}

	public function test_resolver_rejects_null_byte() {
		$this->assertFalse( $this->storage->resolve( [ 'path' => "2026/08/a.png\0.txt" ] ) );
	}

	public function test_resolver_rejects_missing_file() {
		$this->assertFalse( $this->storage->resolve( [ 'path' => '2026/08/absent.png' ] ) );
	}

	public function test_resolver_ignores_unrecognised_records() {
		$this->assertFalse( $this->storage->resolve( [] ) );
		$this->assertFalse( $this->storage->resolve( [ 'name' => 'Bob' ] ) );
	}

	public function test_relative_path_rejects_a_path_outside_the_vault() {
		$this->assertFalse( $this->storage->relative_path( '/tmp/elsewhere/file.png' ) );
	}

	public function test_delete_records_removes_only_vault_files() {
		$inside = $this->storage->get_site_root() . '/2026/08/inside.png';
		$this->writeFile( $inside, 'x' );

		$outside = sys_get_temp_dir() . '/formtura-outside-' . uniqid( '', true ) . '.txt';
		$this->writeFile( $outside, 'keep me' );

		$this->storage->delete_records( [
			'resume'    => [ [ 'name' => 'r.png', 'path' => '2026/08/inside.png' ] ],
			'escape'    => [ [ 'name' => 'x', 'path' => '../../../../../../..' . $outside ] ],
			'plaintext' => 'not a file record',
		] );

		$this->assertFileDoesNotExist( $inside );
		$this->assertFileExists( $outside );

		unlink( $outside );
	}

	public function test_remove_site_files_clears_the_vault() {
		$this->writeFile( $this->storage->get_site_root() . '/2026/08/a.png', 'a' );

		$this->assertTrue( $this->storage->remove_site_files() );
		$this->assertDirectoryDoesNotExist( $this->storage->get_site_root() );
	}
}
