<?php
/**
 * Round-trip tests for Form_Builder::sanitize_field_data().
 *
 * This is a strict key allowlist and the only save path
 * (ajax_save_form -> sanitize_form_data -> Forms_DB::update). A setting
 * missing its own branch here is silently discarded on every save,
 * regardless of how correct the rest of that feature is - which is exactly
 * how five tasks' worth of field settings (coupons, payment items/price,
 * the total field's summary toggle, the address scheme, and the entire
 * file-upload options panel) went missing without any test catching it.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Form_Builder;
use Formtura\Tests\TestCase;

class FormBuilderSanitizeTest extends TestCase {

	/**
	 * @var Form_Builder
	 */
	private $builder;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = new Form_Builder();
	}

	/**
	 * Call the private sanitize_field_data( $field ).
	 *
	 * @param array $field Raw field data, as posted by the builder.
	 * @return array Sanitized field data.
	 */
	private function sanitize( array $field ) {
		$reflection = new \ReflectionMethod( Form_Builder::class, 'sanitize_field_data' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->builder, $field );
	}

	/**
	 * Coupon field: the codes that make this task's whole feature work.
	 */
	public function test_coupons_round_trip_with_correct_types() {
		$result = $this->sanitize( [
			'id'      => 'field_coupon',
			'type'    => 'coupon',
			'coupons' => [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5.5' ],
				[ 'code' => 'HALF', 'type' => 'percent', 'value' => '50' ],
			],
		] );

		$this->assertSame(
			[
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => 5.5 ],
				[ 'code' => 'HALF', 'type' => 'percent', 'value' => 50.0 ],
			],
			$result['coupons']
		);
	}

	/**
	 * An unexpected coupon `type` must fall back to 'fixed', not pass
	 * through verbatim - PaymentTotals::find_coupon() only recognises
	 * 'fixed' and 'percent', and the sanitizer must not invent a third state.
	 */
	public function test_coupon_with_unexpected_type_falls_back_to_fixed() {
		$result = $this->sanitize( [
			'coupons' => [
				[ 'code' => 'X', 'type' => 'surprise', 'value' => '1' ],
			],
		] );

		$this->assertSame( 'fixed', $result['coupons'][0]['type'] );
	}

	/**
	 * A non-array coupon entry (a crafted or corrupted POST) must be dropped,
	 * not coerced into a coupon with empty fields, and must not leave a gap
	 * in the array's keys - wp_json_encode() would turn a gap into a JSON
	 * object instead of an array.
	 */
	public function test_non_array_coupon_entry_is_dropped_without_a_key_gap() {
		$result = $this->sanitize( [
			'coupons' => [
				[ 'code' => 'FIRST', 'type' => 'fixed', 'value' => '1' ],
				'not-an-array',
				[ 'code' => 'THIRD', 'type' => 'fixed', 'value' => '3' ],
			],
		] );

		$this->assertSame( [ 0, 1 ], array_keys( $result['coupons'] ) );
		$this->assertSame( 'FIRST', $result['coupons'][0]['code'] );
		$this->assertSame( 'THIRD', $result['coupons'][1]['code'] );
	}

	/**
	 * The coupon value must not be clamped here - PaymentTotals::find_coupon()
	 * already clamps it, and duplicating that would create two places to
	 * keep in step. A negative value must survive the sanitizer as-is.
	 */
	public function test_coupon_value_is_not_clamped_by_the_sanitizer() {
		$result = $this->sanitize( [
			'coupons' => [ [ 'code' => 'NEG', 'type' => 'fixed', 'value' => '-50' ] ],
		] );

		$this->assertSame( -50.0, $result['coupons'][0]['value'] );
	}

	/**
	 * Payment items (payment-checkbox, payment-multiple, payment-dropdown):
	 * every price an author sets must survive.
	 */
	public function test_items_round_trip_with_correct_types() {
		$result = $this->sanitize( [
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00', 'isDefault' => false ],
				[ 'label' => 'Large', 'value' => 'large', 'price' => '25.50', 'isDefault' => true ],
			],
		] );

		$this->assertSame(
			[
				[ 'label' => 'Small', 'value' => 'small', 'price' => 10.0, 'isDefault' => false ],
				[ 'label' => 'Large', 'value' => 'large', 'price' => 25.5, 'isDefault' => true ],
			],
			$result['items']
		);
	}

	/**
	 * A non-array item entry must be dropped without leaving a key gap, the
	 * same guarantee as coupons above.
	 */
	public function test_non_array_item_entry_is_dropped_without_a_key_gap() {
		$result = $this->sanitize( [
			'items' => [
				[ 'label' => 'A', 'value' => 'a', 'price' => '1' ],
				123,
				[ 'label' => 'B', 'value' => 'b', 'price' => '2' ],
			],
		] );

		$this->assertSame( [ 0, 1 ], array_keys( $result['items'] ) );
		$this->assertSame( 'a', $result['items'][0]['value'] );
		$this->assertSame( 'b', $result['items'][1]['value'] );
	}

	/**
	 * Single item price (payment-single).
	 */
	public function test_single_item_price_round_trips_as_a_float() {
		$result = $this->sanitize( [ 'price' => '19.99' ] );

		$this->assertSame( 19.99, $result['price'] );
	}

	/**
	 * showPriceAfterLabels (payment-checkbox/multiple/dropdown display toggle).
	 */
	public function test_show_price_after_labels_round_trips_as_a_bool() {
		$this->assertTrue( $this->sanitize( [ 'showPriceAfterLabels' => true ] )['showPriceAfterLabels'] );
		$this->assertFalse( $this->sanitize( [ 'showPriceAfterLabels' => false ] )['showPriceAfterLabels'] );
	}

	/**
	 * enableSummary (total field's order summary toggle).
	 */
	public function test_enable_summary_round_trips_as_a_bool() {
		$this->assertTrue( $this->sanitize( [ 'enableSummary' => true ] )['enableSummary'] );
		$this->assertFalse( $this->sanitize( [ 'enableSummary' => false ] )['enableSummary'] );
	}

	/**
	 * Address scheme: only 'us' or 'international' are valid; anything else
	 * (a crafted request) falls back to 'us' rather than passing through.
	 */
	public function test_scheme_round_trips_and_rejects_unexpected_values() {
		$this->assertSame( 'international', $this->sanitize( [ 'scheme' => 'international' ] )['scheme'] );
		$this->assertSame( 'us', $this->sanitize( [ 'scheme' => 'us' ] )['scheme'] );
		$this->assertSame( 'us', $this->sanitize( [ 'scheme' => '<script>' ] )['scheme'] );
	}

	/**
	 * File upload options - pre-existing since 1.0.3, not introduced by the
	 * coupon task, but silently discarded by the same missing-branch defect.
	 */
	public function test_file_upload_options_round_trip_with_correct_types() {
		$result = $this->sanitize( [
			'allowMultiple'     => true,
			'attachToEmail'     => true,
			'allowedFileTypes'  => 'all',
			'specifiedTypes'    => 'pdf, doc, docx',
			'minFileSize'       => '0.5',
			'maxFileSize'       => '10',
			'uploadText'        => 'Drop a file here',
			'compactUploadText' => 'Choose File',
		] );

		$this->assertTrue( $result['allowMultiple'] );
		$this->assertTrue( $result['attachToEmail'] );
		$this->assertSame( 'all', $result['allowedFileTypes'] );
		$this->assertSame( 'pdf, doc, docx', $result['specifiedTypes'] );
		$this->assertSame( 0.5, $result['minFileSize'] );
		$this->assertSame( 10.0, $result['maxFileSize'] );
		$this->assertSame( 'Drop a file here', $result['uploadText'] );
		$this->assertSame( 'Choose File', $result['compactUploadText'] );
	}

	/**
	 * allowedFileTypes must be restricted to the values the builder itself
	 * emits (FieldLibrary.jsx: 'all' or 'specify') - an unexpected value
	 * falls back to 'specify', the builder's own default.
	 */
	public function test_allowed_file_types_rejects_unexpected_values() {
		$result = $this->sanitize( [ 'allowedFileTypes' => 'anything-goes' ] );

		$this->assertSame( 'specify', $result['allowedFileTypes'] );
	}

	/**
	 * A non-numeric file size (a nested array where a scalar belongs, or a
	 * hand-crafted string) must not survive as a bogus numeric-looking
	 * string - it is dropped to an empty string rather than cast, since
	 * (float) on an array trips a warning rather than a usable value.
	 */
	public function test_non_numeric_file_size_does_not_survive_as_a_number() {
		$result = $this->sanitize( [
			'minFileSize' => [ 'nested' => 'array' ],
			'maxFileSize' => 'not-a-number',
		] );

		$this->assertSame( '', $result['minFileSize'] );
		$this->assertSame( '', $result['maxFileSize'] );
	}
}
