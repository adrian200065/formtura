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

	public function test_selected_items_price_from_the_definition_not_the_request() {
		$form = $this->form( [ $this->itemsField( 'payment-multiple' ) ] );

		// The browser can claim any total it likes; only the selection matters.
		$result = ( new PaymentTotals() )->compute( $form, [
			'field_items' => 'large',
			'field_total' => '0.01',
		] );

		$this->assertSame( 25.0, $result['amount'] );
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
}
