<?php
/**
 * File cleanup when a whole form is deleted.
 *
 * Deleting a form deletes all of its entries, so every file those entries
 * reference must go too. The form row must not be deleted when entry deletion
 * failed: doing so would strand entries whose parent form no longer exists,
 * with their files unreachable by any cleanup path that starts from a form.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Database\Forms_DB;
use Formtura\Frontend\File_Storage;
use Formtura\Tests\TestCase;

class FormFileCleanupTest extends TestCase {

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
	private $wpdbDouble;

	protected function setUp(): void {
		parent::setUp();

		$this->vaultRoot = sys_get_temp_dir() . '/formtura-vault-' . uniqid( '', true );
		$this->storage   = new File_Storage( $this->vaultRoot );

		global $wpdb;
		$wpdb             = $this->makeWpdb();
		$this->wpdbDouble = $wpdb;
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
	 * A $wpdb serving two entries for one form, each with its own file.
	 */
	private function makeWpdb() {
		return new class {
			public $prefix            = 'wp_';
			public $entryIds          = [];
			public $rows              = [];
			public $meta              = [];
			public $failEntriesDelete = false;
			public $deletedTables     = [];

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
				return $this->entryIds;
			}

			public function get_row( $query, $output = ARRAY_A, $y = 0 ) {
				// The entry id is the last integer in the prepared query.
				if ( preg_match_all( '/\d+/', $query, $m ) ) {
					$id = (int) end( $m[0] );

					return isset( $this->rows[ $id ] ) ? $this->rows[ $id ] : null;
				}

				return null;
			}

			public function get_results( $query, $output = ARRAY_A ) {
				if ( preg_match_all( '/\d+/', $query, $m ) ) {
					$id = (int) end( $m[0] );

					return isset( $this->meta[ $id ] ) ? $this->meta[ $id ] : [];
				}

				return [];
			}

			public function get_var( $query = null, $x = 0, $y = 0 ) {
				return null;
			}

			public function query( $query ) {
				return true;
			}

			public function delete( $table, $where, $where_format = null ) {
				$this->deletedTables[] = $table;

				if ( $this->failEntriesDelete && false !== strpos( $table, 'entries' ) ) {
					return false;
				}

				return 1;
			}
		};
	}

	/**
	 * Seed one entry owning one real vault file.
	 *
	 * @return string Absolute path of the stored file.
	 */
	private function seedEntry( $entry_id ) {
		$dir = $this->storage->prepare_directory();
		$abs = $dir . '/' . uniqid( 'f', true ) . '.pdf';
		file_put_contents( $abs, 'contents' );

		$record = $this->storage->create_record( 'doc.pdf', $abs, 'application/pdf', 8 );

		$this->wpdbDouble->entryIds[]       = $entry_id;
		$this->wpdbDouble->rows[ $entry_id ] = [
			'id'         => $entry_id,
			'form_id'    => 3,
			'created_at' => '2026-08-15 00:00:00',
		];
		$this->wpdbDouble->meta[ $entry_id ] = [
			[ 'meta_key' => 'doc', 'meta_value' => maybe_serialize( [ $record ] ) ],
		];

		return $abs;
	}

	public function test_form_deletion_removes_files_for_every_entry() {
		$first  = $this->seedEntry( 11 );
		$second = $this->seedEntry( 12 );

		$forms = new Forms_DB( $this->storage );

		$this->assertTrue( $forms->delete( 3 ) );
		$this->assertFileDoesNotExist( $first );
		$this->assertFileDoesNotExist( $second );
	}

	/**
	 * The form row must survive a failed entry deletion, and so must the files.
	 */
	public function test_failed_entry_deletion_aborts_form_deletion() {
		$file = $this->seedEntry( 11 );

		$this->wpdbDouble->failEntriesDelete = true;

		$forms = new Forms_DB( $this->storage );

		$this->assertFalse( $forms->delete( 3 ) );
		$this->assertFileExists( $file );
		$this->assertNotContains( 'wp_fta_forms', $this->wpdbDouble->deletedTables );
	}
}
