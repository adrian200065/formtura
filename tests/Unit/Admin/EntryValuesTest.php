<?php
/**
 * Rendering stored entry values as text.
 *
 * Entry data is not flat: checkboxes hold a list, address and name fields hold
 * parts, file fields hold records, and payment forms hold a computed order.
 * Every surface that shows an entry - the list preview, the detail view, the
 * CSV export - needs the same answer for "what does this value say", so the
 * conversion lives here instead of being re-invented per surface with
 * implode() and (string) casts that turn a nested value into "Array".
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Entry_Values;
use Formtura\Tests\TestCase;

class EntryValuesTest extends TestCase {

	public function test_scalar_values_pass_through() {
		$this->assertSame( 'Ada', Entry_Values::to_text( 'Ada' ) );
		$this->assertSame( '0', Entry_Values::to_text( 0 ) );
		$this->assertSame( '', Entry_Values::to_text( null ) );
	}

	public function test_multi_value_fields_are_joined() {
		$this->assertSame( 'Small, Large', Entry_Values::to_text( [ 'Small', 'Large' ] ) );
	}

	/**
	 * The defect this class exists for: a nested value used to reach a string
	 * cast and land in the export as the literal word "Array".
	 */
	public function test_nested_values_are_flattened_rather_than_cast() {
		$address = [
			'line1'   => '1 Elm Street',
			'line2'   => '',
			'city'    => 'Springfield',
			'state'   => 'IL',
			'zip'     => '62701',
			'country' => 'US',
		];

		$this->assertSame(
			'1 Elm Street, Springfield, IL, 62701, US',
			Entry_Values::to_text( $address )
		);
	}

	public function test_file_records_render_as_their_original_filename() {
		$record = [
			'name' => 'resume.pdf',
			'path' => 'wp-content/uploads/formtura-private/2026/08/abc.pdf',
			'type' => 'application/pdf',
			'size' => 128,
		];

		$this->assertSame( 'resume.pdf', Entry_Values::to_text( [ $record ] ) );
	}

	/**
	 * A record's stored path is a private vault location. It must never be the
	 * thing a value renders as, in any surface.
	 */
	public function test_file_records_never_expose_their_stored_path() {
		$record = [
			'name' => 'resume.pdf',
			'path' => 'wp-content/uploads/formtura-private/2026/08/abc.pdf',
		];

		$this->assertStringNotContainsString( 'formtura-private', Entry_Values::to_text( [ $record ] ) );
	}

	/**
	 * Serialized data can nest arbitrarily deep. Recursion is bounded so a
	 * pathological value cannot exhaust the stack while rendering an entry.
	 */
	public function test_deep_nesting_terminates() {
		$value = 'leaf';

		for ( $i = 0; $i < 50; $i++ ) {
			$value = [ $value ];
		}

		$this->assertSame( '', Entry_Values::to_text( $value ) );
	}

	public function test_payment_orders_render_as_an_amount_with_line_items() {
		$payment = [
			'amount'   => 42.5,
			'currency' => 'USD',
			'items'    => [
				[ 'label' => 'Ticket', 'price' => 40.0 ],
				[ 'label' => 'Badge', 'price' => 2.5 ],
			],
			'coupon'   => null,
		];

		$this->assertSame(
			'USD 42.50 - Ticket (40.00), Badge (2.50)',
			Entry_Values::text_for( '_payment', $payment )
		);
	}

	public function test_payment_orders_record_the_applied_coupon() {
		$payment = [
			'amount'   => 9.0,
			'currency' => 'USD',
			'items'    => [ [ 'label' => 'Ticket', 'price' => 10.0 ] ],
			'coupon'   => 'SAVE10',
		];

		$this->assertStringContainsString( 'SAVE10', Entry_Values::text_for( '_payment', $payment ) );
	}

	public function test_labels_come_from_the_form_definition() {
		$labels = Entry_Values::labels( $this->form() );

		$this->assertSame( 'Your name', Entry_Values::label( 'field_1', $labels ) );
		$this->assertSame( 'Email', Entry_Values::label( 'field_2', $labels ) );
	}

	/**
	 * A field deleted from the form after entries were collected has no label
	 * left to look up, and its raw name is not readable as a column heading.
	 */
	public function test_unknown_keys_fall_back_to_a_readable_heading() {
		$this->assertSame( 'Old field', Entry_Values::label( 'old_field', [] ) );
	}

	public function test_the_reserved_payment_key_is_labelled() {
		$this->assertSame( 'Payment', Entry_Values::label( '_payment', [] ) );
	}

	public function test_file_records_are_recognised_for_download_links() {
		$record = [ 'name' => 'resume.pdf', 'path' => 'wp-content/uploads/x/abc.pdf' ];

		$this->assertSame( [ $record ], Entry_Values::file_records( [ $record ] ) );
		$this->assertSame( [ $record ], Entry_Values::file_records( $record ) );
		$this->assertSame( [], Entry_Values::file_records( 'Ada' ) );
		$this->assertSame( [], Entry_Values::file_records( [ 'Small', 'Large' ] ) );
	}

	private function form() {
		return [
			'id'     => 7,
			'fields' => [
				[ 'id' => 'field_1', 'type' => 'text', 'label' => 'Your name' ],
				[ 'id' => 'field_2', 'type' => 'email', 'label' => 'Email' ],
				[ 'id' => 'field_3', 'type' => 'section-divider' ],
			],
		];
	}
}
