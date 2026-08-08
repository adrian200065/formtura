<?php
/**
 * Address field validation tests.
 *
 * Required means line1, city, state and zip are all present. line2 and
 * country are always optional.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Submission;
use Formtura\Tests\TestCase;

class AddressValidationTest extends TestCase {

	/**
	 * Call the private validate_field_type.
	 *
	 * @param mixed $value Submitted value.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error
	 */
	private function validate( $value, array $field ) {
		$reflection = new \ReflectionMethod( Submission::class, 'validate_field_type' );
		$reflection->setAccessible( true );

		return $reflection->invoke( new Submission(), $value, $field );
	}

	private function field( array $extra = [] ) {
		return array_merge( [ 'id' => 'field_1', 'type' => 'address', 'label' => 'Address' ], $extra );
	}

	public function test_complete_required_address_passes() {
		$result = $this->validate(
			[ 'line1' => '1 Main St', 'city' => 'Springfield', 'state' => 'IL', 'zip' => '62701' ],
			$this->field( [ 'required' => true ] )
		);

		$this->assertTrue( $result );
	}

	public function test_required_address_missing_a_core_part_fails() {
		$result = $this->validate(
			[ 'line1' => '1 Main St', 'city' => 'Springfield', 'state' => '', 'zip' => '62701' ],
			$this->field( [ 'required' => true ] )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_optional_partial_address_passes() {
		$result = $this->validate(
			[ 'city' => 'Springfield' ],
			$this->field( [ 'required' => false ] )
		);

		$this->assertTrue( $result );
	}

	public function test_non_array_value_for_address_fails() {
		$result = $this->validate( 'just a string', $this->field( [ 'required' => true ] ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
