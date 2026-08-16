<?php
/**
 * Entries list screen rendering.
 *
 * The preview column read $entry['entry_data'], a key Entries_DB has never
 * produced - it returns the unserialized answers under 'data'. Every row
 * therefore rendered an empty preview cell, and the "View all fields" link
 * that depends on the same value never appeared.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Tests\TestCase;

class EntriesListViewTest extends TestCase {

	/**
	 * @var object
	 */
	private $recordingWpdb;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb                = $this->makeWpdb();
		$this->recordingWpdb = $wpdb;

		$GLOBALS['fta_test_options'] = [
			'date_format' => 'Y-m-d',
			'time_format' => 'H:i',
		];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );

		parent::tearDown();
	}

	private function makeWpdb() {
		return new class {
			public $prefix    = 'wp_';
			public $entryRows = [];
			public $entryMeta = [];
			public $formRow   = null;

			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					$query = preg_replace( '/%d|%s/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			public function get_row( $query, $output = ARRAY_A, $y = 0 ) {
				return $this->formRow;
			}

			public function get_results( $query, $output = ARRAY_A ) {
				return false !== strpos( $query, 'fta_entry_meta' ) ? $this->entryMeta : $this->entryRows;
			}

			public $entryCount = 1;

			public function get_var( $query = null, $x = 0, $y = 0 ) {
				return $this->entryCount;
			}

			public function query( $query ) {
				return true;
			}
		};
	}

	/**
	 * Render the screen for one seeded entry and return its markup.
	 *
	 * @param array $data Stored field data for the entry.
	 * @return string
	 */
	private function render( array $data ) {
		$this->recordingWpdb->entryRows = [
			[
				'id'         => 4,
				'form_id'    => 7,
				'user_id'    => 0,
				'ip_address' => '203.0.113.9',
				'user_agent' => 'Mozilla/5.0',
				'is_read'    => 0,
				'created_at' => '2026-08-15 09:30:00',
			],
		];

		$this->recordingWpdb->entryMeta = [];

		foreach ( $data as $key => $value ) {
			$this->recordingWpdb->entryMeta[] = [
				'meta_key'   => $key,
				'meta_value' => maybe_serialize( $value ),
			];
		}

		$this->recordingWpdb->formRow = [
			'id'         => 7,
			'title'      => 'Contact',
			'fields'     => wp_json_encode(
				[
					[ 'id' => 'field_1', 'type' => 'text', 'label' => 'Your name' ],
					[ 'id' => 'field_2', 'type' => 'checkbox', 'label' => 'Sizes' ],
					[ 'id' => 'field_3', 'type' => 'address', 'label' => 'Address' ],
					[ 'id' => 'field_4', 'type' => 'file', 'label' => 'Resume' ],
					[ 'id' => 'field_5', 'type' => 'text', 'label' => 'Notes' ],
				]
			),
			'settings'   => wp_json_encode( [] ),
			'status'     => 'active',
			'created_at' => '2026-08-01 00:00:00',
		];

		$forms            = [ [ 'id' => 7, 'title' => 'Contact' ] ];
		$selected_form_id = 7;

		ob_start();
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/entries-list.php';

		return ob_get_clean();
	}

	/**
	 * The defect: values live under 'data', so nothing was ever shown.
	 */
	public function test_stored_answers_appear_in_the_preview() {
		$html = $this->render( [ 'field_1' => 'Ada Lovelace' ] );

		$this->assertStringContainsString( 'Ada Lovelace', $html );
	}

	public function test_the_preview_uses_field_labels() {
		$html = $this->render( [ 'field_1' => 'Ada Lovelace' ] );

		$this->assertStringContainsString( 'Your name', $html );
	}

	public function test_nested_answers_are_flattened_not_rendered_as_array() {
		$html = $this->render(
			[
				'field_2' => [ 'Small', 'Large' ],
				'field_3' => [ 'line1' => '1 Elm Street', 'city' => 'Springfield' ],
			]
		);

		$this->assertStringNotContainsString( 'Array', $html );
		$this->assertStringContainsString( 'Small, Large', $html );
		$this->assertStringContainsString( '1 Elm Street, Springfield', $html );
	}

	/**
	 * The preview shows the first few answers; the rest have to be reachable,
	 * which is what the detail panel the View control opens is for.
	 */
	public function test_every_answer_is_present_in_the_detail_panel() {
		$html = $this->render(
			[
				'field_1' => 'Ada',
				'field_2' => [ 'Small' ],
				'field_3' => [ 'line1' => '1 Elm Street' ],
				'field_5' => 'A late answer',
			]
		);

		$this->assertStringContainsString( 'fta-entry-details', $html );
		$this->assertStringContainsString( 'A late answer', $html );
	}

	/**
	 * A record's vault path must never reach the page; the only route to the
	 * bytes is the capability-checked download controller.
	 */
	public function test_file_answers_link_to_the_authorized_download_route() {
		$html = $this->render(
			[
				'field_4' => [
					[
						'name' => 'resume.pdf',
						'path' => 'wp-content/uploads/formtura-private/2026/08/abc.pdf',
						'type' => 'application/pdf',
						'size' => 128,
					],
				],
			]
		);

		$this->assertStringContainsString( 'resume.pdf', $html );
		$this->assertStringContainsString( 'fta_download_file', $html );
		$this->assertStringNotContainsString( 'formtura-private', $html );
	}

	public function test_row_controls_carry_the_entry_id_for_the_client_handlers() {
		$html = $this->render( [ 'field_1' => 'Ada' ] );

		$this->assertStringContainsString( 'fta-view-entry', $html );
		$this->assertStringContainsString( 'fta-delete-entry', $html );
		$this->assertStringContainsString( 'fta-mark-read', $html );
		$this->assertStringContainsString( 'data-entry-id="4"', $html );
	}

	/**
	 * Answers are visitor-supplied, and the detail panel renders them inside
	 * the page rather than through a JSON round trip - so the escaping has to
	 * happen here.
	 */
	public function test_answers_are_escaped() {
		$html = $this->render( [ 'field_1' => '<script>alert(1)</script>' ] );

		$this->assertStringNotContainsString( '<script>alert(1)</script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	/**
	 * The screen shows one page at a time. Without a pager the entries past
	 * the first page are unreachable, and the export - which does cover them
	 * all - disagrees with what the screen appears to hold.
	 */
	public function test_a_form_past_one_page_offers_navigation() {
		$this->recordingWpdb->entryCount = 45;

		$html = $this->render( [ 'field_1' => 'Ada' ] );

		$this->assertStringContainsString( 'fta-entries-pagination', $html );
		$this->assertStringContainsString( 'Page 1 of 3 (45 entries)', $html );
		$this->assertStringContainsString( 'paged=2', $html );
	}

	public function test_a_single_page_of_entries_shows_no_pager() {
		$html = $this->render( [ 'field_1' => 'Ada' ] );

		$this->assertStringNotContainsString( 'fta-entries-pagination', $html );
	}
}
