<?php
/**
 * Price formatting and payment item normalization tests.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Utils;

use Formtura\Tests\TestCase;

class PriceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fta_test_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );
		parent::tearDown();
	}

	public function test_formats_with_dollar_symbol_by_default() {
		$this->assertSame( '$10.00', fta_format_price( 10 ) );
	}

	public function test_formats_two_decimals_and_thousands() {
		$this->assertSame( '$1,234.50', fta_format_price( 1234.5 ) );
	}

	public function test_uses_the_configured_currency() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'currency' => 'EUR' ];

		$this->assertSame( '€25.00', fta_format_price( 25 ) );
	}

	public function test_unknown_currency_falls_back_to_its_code() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'currency' => 'XCD' ];

		$this->assertSame( 'XCD25.00', fta_format_price( 25 ) );
	}

	public function test_items_are_normalized() {
		$items = fta_get_field_items( [
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00', 'isDefault' => false ],
				[ 'label' => 'Large', 'value' => '', 'price' => 25 ],
			],
		] );

		$this->assertSame(
			[
				[ 'label' => 'Small', 'value' => 'small', 'price' => 10.0, 'isDefault' => false ],
				// Empty value falls back to the label, like choices do.
				[ 'label' => 'Large', 'value' => 'Large', 'price' => 25.0, 'isDefault' => false ],
			],
			$items
		);
	}

	public function test_junk_items_are_dropped_and_junk_prices_are_zero() {
		$items = fta_get_field_items( [
			'items' => [
				'not-an-array',
				[ 'label' => '', 'value' => '' ],
				[ 'label' => 'Thing', 'value' => 'thing', 'price' => 'abc' ],
			],
		] );

		$this->assertCount( 1, $items );
		$this->assertSame( 0.0, $items[0]['price'] );
	}

	public function test_missing_items_key_gives_empty_list() {
		$this->assertSame( [], fta_get_field_items( [] ) );
	}
}
