<?php
/**
 * Submission handling for fields that render no answer.
 *
 * Some field types are on the form for display only, or post a marker rather
 * than a visitor's answer. Two things must hold for all of them: a required
 * flag on one can never block a submission (the visitor has nothing to fill
 * in, so the error would be unclearable), and their posted value must not be
 * stored as that field's entry answer.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Submission;
use Formtura\Tests\TestCase;

class PresentationalFieldSubmissionTest extends TestCase {

	/**
	 * @var Submission
	 */
	private $submission;

	protected function setUp(): void {
		parent::setUp();

		$this->submission = new Submission();
	}

	/**
	 * Call the private validate_submission( $form, $submission ).
	 *
	 * @param array $form       Form data.
	 * @param array $submission Posted data.
	 * @return true|\WP_Error
	 */
	private function validate( array $form, array $submission ) {
		$reflection = new \ReflectionMethod( Submission::class, 'validate_submission' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->submission, $form, $submission );
	}

	/**
	 * Call the private sanitize_submission( $form, $submission ).
	 *
	 * @param array $form       Form data.
	 * @param array $submission Posted data.
	 * @return array
	 */
	private function sanitizeSubmission( array $form, array $submission ) {
		$reflection = new \ReflectionMethod( Submission::class, 'sanitize_submission' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->submission, $form, $submission );
	}

	/**
	 * The defect this covers: the builder offered a Required toggle on the
	 * total field, which renders no input at all. A form saved that way
	 * rejected every submission with "Total is required.", attached to a field
	 * the visitor cannot fill in and cannot see an input for - permanently,
	 * with no way to clear it from the front end.
	 */
	public function test_a_required_total_field_does_not_block_submission() {
		$form = [
			'fields' => [
				[ 'id' => 'field_name', 'type' => 'text', 'label' => 'Name' ],
				[ 'id' => 'field_total', 'type' => 'total', 'label' => 'Total', 'required' => true ],
			],
		];

		$result = $this->validate( $form, [ 'field_name' => 'Ada' ] );

		$this->assertTrue( $result, 'A required total field must never block a submission.' );
	}

	/**
	 * Every display-only type must behave the same way, so the next one added
	 * to is_presentational_field() is covered here by construction.
	 *
	 * @dataProvider presentationalTypeProvider
	 * @param string $type Field type.
	 */
	public function test_required_presentational_fields_never_block_submission( $type ) {
		$form = [
			'fields' => [
				[ 'id' => 'field_x', 'type' => $type, 'label' => ucfirst( $type ), 'required' => true ],
			],
		];

		$this->assertTrue( $this->validate( $form, [] ) );
	}

	/**
	 * @dataProvider presentationalTypeProvider
	 * @param string $type Field type.
	 */
	public function test_presentational_fields_are_not_stored_as_entry_answers( $type ) {
		$form = [
			'fields' => [
				[ 'id' => 'field_x', 'type' => $type, 'label' => ucfirst( $type ) ],
			],
		];

		// A hand-written template or stale saved form could post one anyway.
		$entry = $this->sanitizeSubmission( $form, [ 'field_x' => '999.00' ] );

		$this->assertArrayNotHasKey( 'field_x', $entry );
	}

	/**
	 * @return array[]
	 */
	public function presentationalTypeProvider() {
		return [
			'html'            => [ 'html' ],
			'content'         => [ 'content' ],
			'section-divider' => [ 'section-divider' ],
			'page-break'      => [ 'page-break' ],
			'entry-preview'   => [ 'entry-preview' ],
			'layout'          => [ 'layout' ],
			'total'           => [ 'total' ],
		];
	}

	/**
	 * payment-single posts value="1" as an inclusion marker. Stored as that
	 * field's answer, an entry shows "1" where the visitor's response would
	 * be; the real amount is recorded under the entry's _payment key by
	 * PaymentTotals, from the form definition.
	 */
	public function test_payment_single_marker_is_not_stored_as_an_answer() {
		$form = [
			'fields' => [
				[ 'id' => 'field_item', 'type' => 'payment-single', 'label' => 'T-shirt', 'price' => '25.00' ],
				[ 'id' => 'field_name', 'type' => 'text', 'label' => 'Name' ],
			],
		];

		$entry = $this->sanitizeSubmission( $form, [
			'field_item' => '1',
			'field_name' => 'Ada',
		] );

		$this->assertArrayNotHasKey( 'field_item', $entry );
		$this->assertSame( 'Ada', $entry['field_name'] );
	}

	/**
	 * payment-single is still a real field for validation: it is not
	 * presentational, so nothing about the skip above may stop the rest of the
	 * form from being checked.
	 */
	public function test_other_fields_still_validate_alongside_a_payment_single() {
		$form = [
			'fields' => [
				[ 'id' => 'field_item', 'type' => 'payment-single', 'label' => 'T-shirt', 'price' => '25.00' ],
				[ 'id' => 'field_email', 'type' => 'email', 'label' => 'Email', 'required' => true ],
			],
		];

		$result = $this->validate( $form, [ 'field_item' => '1' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'field_email', $result->get_error_data() );
	}
}
