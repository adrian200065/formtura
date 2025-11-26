<?php
/**
 * Tests for Sanitize utility class
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Utils;

use Formtura\Tests\TestCase;
use Formtura\Utils\Sanitize;

/**
 * Test Sanitize class
 */
class SanitizeTest extends TestCase {

	/**
	 * Test boolean method
	 */
	public function test_boolean() {
		$this->assertTrue( Sanitize::boolean( true ) );
		$this->assertTrue( Sanitize::boolean( 'true' ) );
		$this->assertTrue( Sanitize::boolean( '1' ) );
		$this->assertTrue( Sanitize::boolean( 1 ) );

		$this->assertFalse( Sanitize::boolean( false ) );
		$this->assertFalse( Sanitize::boolean( 'false' ) );
		$this->assertFalse( Sanitize::boolean( '0' ) );
		$this->assertFalse( Sanitize::boolean( 0 ) );
	}

	/**
	 * Test integer method
	 */
	public function test_integer() {
		$this->assertEquals( 42, Sanitize::integer( 42 ) );
		$this->assertEquals( 42, Sanitize::integer( '42' ) );
		$this->assertEquals( 0, Sanitize::integer( -5 ) );
		$this->assertEquals( 0, Sanitize::integer( 'abc' ) );
	}

	/**
	 * Test float method
	 */
	public function test_float() {
		$this->assertEquals( 3.14, Sanitize::float( '3.14' ) );
		$this->assertEquals( 42.0, Sanitize::float( 42 ) );
		$this->assertEquals( 0.0, Sanitize::float( 'abc' ) );
	}

	/**
	 * Test hex_color method
	 */
	public function test_hex_color() {
		$this->assertEquals( '#ff0000', Sanitize::hex_color( 'ff0000' ) );
		$this->assertEquals( '#ff0000', Sanitize::hex_color( '#ff0000' ) );
		$this->assertEquals( '#fff', Sanitize::hex_color( 'fff' ) );
		$this->assertEquals( '', Sanitize::hex_color( 'invalid' ) );
		$this->assertEquals( '', Sanitize::hex_color( '' ) );
	}

	/**
	 * Test array method
	 */
	public function test_array() {
		$input = [
			'key1' => 'value1',
			'key2' => '<script>xss</script>',
			'nested' => [
				'inner' => 'data',
			],
		];

		$result = Sanitize::array( $input );

		$this->assertIsArray( $result );
		$this->assertEquals( 'value1', $result['key1'] );
		$this->assertArrayHasKey( 'nested', $result );
	}

	/**
	 * Test array with non-array input
	 */
	public function test_array_with_non_array() {
		$result = Sanitize::array( 'not an array' );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );
	}

	/**
	 * Test field method for different types
	 */
	public function test_field_text() {
		$result = Sanitize::field( 'Hello World', 'text' );
		$this->assertIsString( $result );
	}

	/**
	 * Test field method for number type
	 */
	public function test_field_number() {
		$this->assertEquals( 42.0, Sanitize::field( '42', 'number' ) );
		$this->assertEquals( 0.0, Sanitize::field( 'abc', 'number' ) );
	}

	/**
	 * Test field method for array checkbox values
	 */
	public function test_field_checkbox_array() {
		$input = [ 'option1', 'option2', 'option3' ];
		$result = Sanitize::field( $input, 'checkbox' );

		$this->assertIsArray( $result );
		$this->assertCount( 3, $result );
	}
}
