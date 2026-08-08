<?php
/**
 * Server-side payment recomputation tests.
 *
 * The core invariant: the amount stored with an entry derives only from
 * the form definition and the visitor's selections - never from any
 * price or total the browser posted.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\PaymentTotals;
use Formtura\Tests\TestCase;

class PaymentTotalsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fta_test_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );
		parent::tearDown();
	}

	private function form( array $fields ) {
		return [ 'id' => 7, 'fields' => $fields ];
	}

	private function itemsField( $type, $id = 'field_items' ) {
		return [
			'id'    => $id,
			'type'  => $type,
			'label' => 'Items',
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00' ],
				[ 'label' => 'Large', 'value' => 'large', 'price' => '25.00' ],
			],
		];
	}

	public function test_detects_payment_forms() {
		$totals = new PaymentTotals();

		$this->assertTrue( $totals->form_has_payment_fields( $this->form( [ [ 'id' => 'a', 'type' => 'payment-single' ] ] ) ) );
		$this->assertFalse( $totals->form_has_payment_fields( $this->form( [ [ 'id' => 'a', 'type' => 'text' ] ] ) ) );
	}

	public function test_single_items_always_count() {
		$form = $this->form( [ [ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ] ] );

		$result = ( new PaymentTotals() )->compute( $form, [] );

		$this->assertSame( 5.0, $result['amount'] );
		$this->assertSame( [ [ 'label' => 'Fee', 'price' => 5.0 ] ], $result['items'] );
	}

	/**
	 * The core invariant, made falsifiable: the form definition includes a
	 * real `total`-typed field (so posting to its own submission key is not
	 * a no-op just because the key doesn't exist), and a payment-single
	 * field is attacked by posting a bogus value under its own key even
	 * though nothing is ever read from the submission for that type. An
	 * implementation that trusted either posted value instead of the
	 * definition would compute something other than 30.0 here.
	 */
	public function test_selected_items_price_from_the_definition_not_the_request() {
		$form = $this->form( [
			[ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ],
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_total', 'type' => 'total', 'label' => 'Total' ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [
			// Bogus price posted directly under the fixed-price field's own
			// key - payment-single never reads $submission at all.
			'field_fee'   => '0.01',
			// A real selection; its price must come from the definition,
			// not anything the browser sent alongside it.
			'field_items' => 'large',
			// A real `total`-typed field's own submission key, carrying a
			// decoy amount - `total` fields contribute nothing to the sum.
			'field_total' => '0.01',
		] );

		$this->assertSame( 30.0, $result['amount'] );
	}

	public function test_checkbox_selections_sum() {
		$form = $this->form( [ $this->itemsField( 'payment-checkbox' ) ] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => [ 'small', 'large' ] ] );

		$this->assertSame( 35.0, $result['amount'] );
	}

	public function test_unknown_item_value_is_a_field_error() {
		$form = $this->form( [ $this->itemsField( 'payment-multiple' ) ] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'forged-item' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'field_items', $result->get_error_data() );
	}

	public function test_fixed_coupon_is_applied() {
		$form = $this->form( [
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5.00' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'large', 'field_coupon' => 'save5' ] );

		// Case-insensitive match, fixed discount.
		$this->assertSame( 20.0, $result['amount'] );
		$this->assertSame( 'SAVE5', $result['coupon'] );
	}

	public function test_percent_coupon_is_applied() {
		$form = $this->form( [
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'HALF', 'type' => 'percent', 'value' => '50' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'small', 'field_coupon' => 'HALF' ] );

		$this->assertSame( 5.0, $result['amount'] );
	}

	public function test_unknown_coupon_is_a_field_error() {
		$form = $this->form( [
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'small', 'field_coupon' => 'NOPE' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'field_coupon', $result->get_error_data() );
	}

	public function test_empty_coupon_field_is_fine() {
		$form = $this->form( [
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'small', 'field_coupon' => '' ] );

		$this->assertSame( 10.0, $result['amount'] );
		$this->assertNull( $result['coupon'] );
	}

	public function test_amount_never_goes_negative() {
		$form = $this->form( [
			[ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ],
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'BIG', 'type' => 'fixed', 'value' => '100' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_coupon' => 'BIG' ] );

		$this->assertSame( 0.0, $result['amount'] );
	}

	public function test_currency_comes_from_settings() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'currency' => 'EUR' ];
		$form = $this->form( [ [ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ] ] );

		$result = ( new PaymentTotals() )->compute( $form, [] );

		$this->assertSame( 'EUR', $result['currency'] );
	}

	public function test_find_coupon_matches_case_insensitively() {
		$field = [ 'coupons' => [ [ 'code' => 'Save5', 'type' => 'fixed', 'value' => '5' ] ] ];

		$this->assertSame(
			[ 'code' => 'Save5', 'type' => 'fixed', 'value' => 5.0 ],
			PaymentTotals::find_coupon( $field, 'sAvE5' )
		);
		$this->assertNull( PaymentTotals::find_coupon( $field, 'other' ) );
		$this->assertNull( PaymentTotals::find_coupon( [], 'Save5' ) );
	}

	/**
	 * Adversarial: a percent coupon above 100 must not push the amount
	 * negative. This is implied by the "never goes negative" floor, but
	 * that existing test uses a fixed coupon - this exercises the percent
	 * branch's own arithmetic (amount * value / 100) directly.
	 */
	public function test_percent_coupon_above_100_floors_at_zero() {
		$form = $this->form( [
			[ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ],
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'TOOMUCH', 'type' => 'percent', 'value' => '150' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_coupon' => 'TOOMUCH' ] );

		$this->assertSame( 0.0, $result['amount'] );
	}

	/**
	 * Adversarial: a saved form definition (not user input) with a
	 * negative price must not let the negative value pull the total down
	 * or below zero via cancellation with a legitimate item.
	 */
	public function test_negative_price_in_definition_does_not_produce_negative_amount() {
		$form = $this->form( [
			[ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '-50.00' ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [] );

		$this->assertSame( 0.0, $result['amount'] );
	}

	/**
	 * Adversarial: a non-numeric price stored in the definition (e.g. a
	 * hand-edited template or corrupted data) must be treated as 0, not
	 * cause a fatal error or a NaN amount.
	 */
	public function test_non_numeric_price_in_definition_is_treated_as_zero() {
		$form = $this->form( [
			[ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => 'not-a-number' ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [] );

		$this->assertSame( 0.0, $result['amount'] );
	}

	/**
	 * Adversarial: a request that simply omits a payment-multiple field
	 * entirely (as opposed to sending an empty string) must compute to
	 * zero rather than erroring - omission is not a forged value.
	 */
	public function test_omitted_items_field_computes_to_zero() {
		$form = $this->form( [ $this->itemsField( 'payment-multiple' ) ] );

		$result = ( new PaymentTotals() )->compute( $form, [] );

		$this->assertSame( 0.0, $result['amount'] );
		$this->assertSame( [], $result['items'] );
	}

	/**
	 * Round 1 fix - Minor 3: a repeated value in a checkbox selection must
	 * only be counted once. Posting the same value twice is not two items.
	 */
	public function test_duplicate_checkbox_selections_are_not_double_counted() {
		$form = $this->form( [ $this->itemsField( 'payment-checkbox' ) ] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => [ 'small', 'small' ] ] );

		$this->assertSame( 10.0, $result['amount'] );
	}

	/**
	 * Round 1 fix - Minor 3: a single-select type (payment-multiple,
	 * payment-dropdown) only ever has one real selection. An array with
	 * more than one element can only be a crafted request; only the first
	 * element counts, the rest must not be summed in.
	 */
	public function test_array_posted_to_single_select_field_takes_only_the_first_value() {
		$form = $this->form( [ $this->itemsField( 'payment-multiple' ) ] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => [ 'large', 'small' ] ] );

		$this->assertSame( 25.0, $result['amount'] );
	}

	/**
	 * Round 1 fix - Minor 4: a coupon definition with a negative value must
	 * not act as a surcharge (amount -= -50 would increase the total).
	 */
	public function test_negative_coupon_value_is_clamped_to_zero() {
		$field = [ 'coupons' => [ [ 'code' => 'NEG', 'type' => 'fixed', 'value' => '-50' ] ] ];

		$found = PaymentTotals::find_coupon( $field, 'NEG' );

		$this->assertSame( 0.0, $found['value'] );
	}

	/**
	 * Round 1 fix - Minor 4: a percent coupon above 100 must be clamped,
	 * not merely rely on the amount floor downstream to hide it.
	 */
	public function test_percent_coupon_value_is_clamped_to_100() {
		$field = [ 'coupons' => [ [ 'code' => 'HUGE', 'type' => 'percent', 'value' => '250' ] ] ];

		$found = PaymentTotals::find_coupon( $field, 'HUGE' );

		$this->assertSame( 100.0, $found['value'] );
	}

	/**
	 * Round 1 fix - Minor 4, end to end: a negative fixed coupon on an
	 * actual order must leave the fee untouched, not inflate it.
	 */
	public function test_negative_coupon_does_not_inflate_the_order_amount() {
		$form = $this->form( [
			[ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ],
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'NEG', 'type' => 'fixed', 'value' => '-50' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_coupon' => 'NEG' ] );

		$this->assertSame( 5.0, $result['amount'] );
	}

	/**
	 * Round 1 fix - Minor 5: compute() is a published method and must not
	 * fatal on a form with no usable `fields` array, the same shape
	 * form_has_payment_fields() already guards against.
	 */
	public function test_compute_guards_against_a_missing_fields_array() {
		$result = ( new PaymentTotals() )->compute( [ 'id' => 7 ], [] );

		$this->assertSame( 0.0, $result['amount'] );
		$this->assertSame( [], $result['items'] );
	}

	/**
	 * Round 1 fix - Minor 5: a nested array posted as an item value (e.g. a
	 * crafted field_items[][]=x request) must produce the same "Invalid
	 * selection" field error as any other unrecognised value, without an
	 * "Array to string conversion" warning along the way.
	 */
	public function test_nested_array_item_value_is_a_field_error() {
		$form = $this->form( [ $this->itemsField( 'payment-checkbox' ) ] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => [ 'small', [ 'nested' ] ] ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'field_items', $result->get_error_data() );
	}

	/**
	 * Round 1 fix - Minor 5: a nested array posted as a coupon code must
	 * produce a field error rather than a warning/fatal from trim().
	 */
	public function test_nested_array_coupon_code_is_a_field_error() {
		$form = $this->form( [
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_coupon' => [ 'nested' ] ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'field_coupon', $result->get_error_data() );
	}

	/**
	 * Round 1 fix - Minor 5: find_coupon() is called directly by Task 11's
	 * AJAX endpoint with a raw $_POST value, which could be an array. It
	 * must return null, not fatal inside trim().
	 */
	public function test_find_coupon_with_array_code_returns_null() {
		$field = [ 'coupons' => [ [ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ] ] ];

		$this->assertNull( PaymentTotals::find_coupon( $field, [ 'nested' ] ) );
	}
}
