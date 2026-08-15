<?php
/**
 * File cleanup across the entry lifecycle.
 *
 * Deleting an entry used to delete only database rows, leaving its uploads
 * and signatures on disk forever with nothing referencing them.
 *
 * Ordering is the subtle part and is pinned here: files must be captured
 * before the rows go, but deleted only after the database delete succeeds. Do
 * it the other way round and a failed delete leaves an entry that still
 * displays files which no longer exist.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Database\Entries_DB;
use Formtura\Frontend\File_Storage;
use Formtura\Tests\TestCase;

class EntryFileCleanupTest extends TestCase {

	/**
	 * @var File_Storage
	 */
	private $storage;

	/**
	 * @var string
	 */
	private $vaultRoot;

	/**
	 * @var object
	 */
	private $recordingWpdb;

	protected function setUp(): void {
		parent::setUp();

		$this->vaultRoot = sys_get_temp_dir() . '/formtura-vault-' . uniqid( '', true );
		$this->storage   = new File_Storage( $this->vaultRoot );

		global $wpdb;
		$wpdb                = $this->makeWpdb();
		$this->recordingWpdb = $wpdb;
	}

	protected function tearDown(): void {
		$this->removeTree( $this->vaultRoot );

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
	 * A $wpdb that serves one seeded entry and can be told to fail its delete.
	 */
	private function makeWpdb() {
		return new class {
			public $prefix          = 'wp_';
			public $failEntryDelete = false;
			public $entryRow        = null;
			public $entryMeta       = [];
			public $entryIds        = [];
			public $deletes         = [];

			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					$query = preg_replace( '/%d|%s/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			public function get_row( $query, $output = ARRAY_A, $y = 0 ) {
				return $this->entryRow;
			}

			public function get_results( $query, $output = ARRAY_A ) {
				return $this->entryMeta;
			}

			public function get_col( $query, $x = 0 ) {
				return $this->entryIds;
			}

			public function get_var( $query = null, $x = 0, $y = 0 ) {
				return null;
			}

			public function query( $query ) {
				return true;
			}

			public function delete( $table, $where, $where_format = null ) {
				$this->deletes[] = [ $table, $where ];

				// Only the entries table itself is made to fail; meta deletion
				// succeeding first mirrors the real ordering.
				if ( $this->failEntryDelete && false === strpos( $table, 'meta' ) ) {
					return false;
				}

				return 1;
			}
		};
	}

	/**
	 * Seed an entry whose data references one real file in the vault.
	 *
	 * @return string Absolute path of the stored file.
	 */
	private function seedEntryWithPrivateFile( $entry_id ) {
		$dir = $this->storage->prepare_directory();
		$abs = $dir . '/' . uniqid( 'e', true ) . '.pdf';
		file_put_contents( $abs, 'contents' );

		$record = $this->storage->create_record( 'resume.pdf', $abs, 'application/pdf', 8 );

		$this->recordingWpdb->entryRow = [
			'id'         => $entry_id,
			'form_id'    => 3,
			'created_at' => '2026-08-15 00:00:00',
		];

		$this->recordingWpdb->entryMeta = [
			[ 'meta_key' => 'resume', 'meta_value' => maybe_serialize( [ $record ] ) ],
		];

		return $abs;
	}

	private function entries() {
		return new Entries_DB( $this->storage );
	}

	public function test_successful_entry_delete_removes_captured_files() {
		$file = $this->seedEntryWithPrivateFile( 4 );

		$this->assertFileExists( $file );
		$this->assertTrue( $this->entries()->delete( 4 ) );
		$this->assertFileDoesNotExist( $file );
	}

	/**
	 * The ordering guarantee: nothing is deleted from disk unless the database
	 * delete actually succeeded.
	 */
	public function test_failed_entry_delete_retains_files() {
		$file = $this->seedEntryWithPrivateFile( 4 );

		$this->recordingWpdb->failEntryDelete = true;

		$this->assertFalse( $this->entries()->delete( 4 ) );
		$this->assertFileExists( $file );
	}

	/**
	 * An entry with no file records must delete cleanly rather than erroring
	 * on values that are ordinary text.
	 */
	public function test_entry_without_files_deletes_cleanly() {
		$this->recordingWpdb->entryRow  = [ 'id' => 5, 'form_id' => 3, 'created_at' => '2026-08-15 00:00:00' ];
		$this->recordingWpdb->entryMeta = [
			[ 'meta_key' => 'name', 'meta_value' => maybe_serialize( 'Ada' ) ],
		];

		$this->assertTrue( $this->entries()->delete( 5 ) );
	}

	/**
	 * A missing entry must not blow up the delete path.
	 */
	public function test_deleting_a_missing_entry_is_safe() {
		$this->recordingWpdb->entryRow = null;

		$this->assertTrue( $this->entries()->delete( 999 ) );
	}
}
