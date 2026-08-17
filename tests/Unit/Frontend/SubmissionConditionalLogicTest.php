<?php
/**
 * A required field hidden by its own conditional logic must not block
 * submission.
 *
 * validate_submission() previously enforced `required` unconditionally, with
 * no awareness of conditionalLogic at all - a field the frontend hides
 * (assets/js/frontend.js) could still fail server-side validation as an
 * unfilled required field, and there was no way to submit the form. This
 * suite recomputes each field's visibility directly from the submitted
 * $_POST data - the same source the trigger field's own value comes from -
 * rather than trusting any client-supplied hidden flag, so it can't be
 * bypassed by a client that skips running the frontend JS.
 *
 * fta_get_form() and fta_create_entry() normally hit the database, which is
 * unavailable in this suite. The namespace-scoped overrides below are
 * resolved in preference to the global functions because Submission.php
 * calls them unqualified from inside Formtura\Frontend - the same pattern
 * SubmissionSuccessMessageTest and SubmissionCouponAjaxTest use.
 *
 * @package Formtura
 */

namespace Formtura\Frontend {
	if ( ! function_exists( __NAMESPACE__ . '\\fta_get_form' ) ) {
		/**
		 * @param int $form_id Form ID.
		 * @return array|null
		 */
		function fta_get_form( $form_id ) {
			return isset( $GLOBALS['fta_test_ajax_forms'][ $form_id ] )
				? $GLOBALS['fta_test_ajax_forms'][ $form_id ]
				: null;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\fta_create_entry' ) ) {
		/**
		 * @param array $data Entry data.
		 * @return int|false
		 */
		function fta_create_entry( $data ) {
			return isset( $GLOBALS['fta_test_created_entry_id'] )
				? $GLOBALS['fta_test_created_entry_id']
				: 1;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\do_action' ) ) {
		/**
		 * @param string $hook Hook name.
		 * @param mixed  ...$args Hook arguments.
		 */
		function do_action( $hook, ...$args ) {
			$GLOBALS['fta_test_actions'][] = $hook;
		}
	}
}

namespace Formtura\Tests\Unit\Frontend {

	use Formtura\Frontend\Submission;
	use Formtura\Tests\TestCase;

	class SubmissionConditionalLogicTest extends TestCase {

		/**
		 * @var Submission
		 */
		private $submission;

		protected function setUp(): void {
			parent::setUp();

			$this->submission                       = new Submission();
			$_POST                                   = [];
			$_SERVER['REMOTE_ADDR']                  = '203.0.113.9';
			$GLOBALS['fta_test_ajax_forms']          = [];
			$GLOBALS['fta_test_ajax_referer_valid']  = true;
			$GLOBALS['fta_test_actions']             = [];
			$GLOBALS['fta_test_created_entry_id']    = 42;
		}

		protected function tearDown(): void {
			$_POST = [];
			unset(
				$_SERVER['REMOTE_ADDR'],
				$GLOBALS['fta_test_ajax_forms'],
				$GLOBALS['fta_test_ajax_referer_valid'],
				$GLOBALS['fta_test_actions'],
				$GLOBALS['fta_test_created_entry_id']
			);

			parent::tearDown();
		}

		/**
		 * Submit a two-field form: a trigger field and a required field
		 * carrying the given conditional logic.
		 *
		 * @param array $conditional_logic Conditional logic for the required field.
		 * @param array $posted            $_POST values, beyond form_id.
		 * @return \FTA_Test_Ajax_Response
		 */
		private function submit( array $conditional_logic, array $posted ) {
			$GLOBALS['fta_test_ajax_forms'][7] = [
				'id'     => 7,
				'status' => 'active',
				'fields' => [
					[ 'id' => 'field_trigger', 'type' => 'text', 'label' => 'Trigger' ],
					[
						'id'               => 'field_conditional',
						'type'             => 'text',
						'label'            => 'Conditional',
						'required'         => true,
						'conditionalLogic' => $conditional_logic,
					],
				],
			];

			$_POST = array_merge( [ 'form_id' => 7 ], $posted );

			try {
				$this->submission->ajax_submit_form();
			} catch ( \FTA_Test_Ajax_Response $response ) {
				return $response;
			}

			$this->fail( 'ajax_submit_form() returned without sending a JSON response.' );
		}

		public function test_a_required_field_hidden_because_its_condition_is_unmet_does_not_block_submission() {
			$response = $this->submit(
				[
					'enabled'    => true,
					'action'     => 'show',
					'match'      => 'all',
					'conditions' => [ [ 'field' => 'field_trigger', 'operator' => 'is', 'value' => 'yes' ] ],
				],
				[ 'field_trigger' => 'no', 'field_conditional' => '' ]
			);

			$this->assertTrue( $response->success );
		}

		public function test_a_required_field_shown_because_its_condition_is_met_still_blocks_submission_when_empty() {
			$response = $this->submit(
				[
					'enabled'    => true,
					'action'     => 'show',
					'match'      => 'all',
					'conditions' => [ [ 'field' => 'field_trigger', 'operator' => 'is', 'value' => 'yes' ] ],
				],
				[ 'field_trigger' => 'yes', 'field_conditional' => '' ]
			);

			$this->assertFalse( $response->success );
			$this->assertArrayHasKey( 'field_conditional', $response->data['errors'] );
		}

		public function test_action_hide_with_a_met_condition_hides_the_field_and_skips_the_required_check() {
			$response = $this->submit(
				[
					'enabled'    => true,
					'action'     => 'hide',
					'match'      => 'all',
					'conditions' => [ [ 'field' => 'field_trigger', 'operator' => 'is', 'value' => 'yes' ] ],
				],
				[ 'field_trigger' => 'yes', 'field_conditional' => '' ]
			);

			$this->assertTrue( $response->success );
		}

		public function test_match_any_with_one_of_two_conditions_met_still_requires_the_field() {
			$GLOBALS['fta_test_ajax_forms'][7] = [
				'id'     => 7,
				'status' => 'active',
				'fields' => [
					[ 'id' => 'field_trigger', 'type' => 'text', 'label' => 'Trigger' ],
					[ 'id' => 'field_other', 'type' => 'text', 'label' => 'Other' ],
					[
						'id'               => 'field_conditional',
						'type'             => 'text',
						'label'            => 'Conditional',
						'required'         => true,
						'conditionalLogic' => [
							'enabled'    => true,
							'action'     => 'show',
							'match'      => 'any',
							'conditions' => [
								[ 'field' => 'field_trigger', 'operator' => 'is', 'value' => 'yes' ],
								[ 'field' => 'field_other', 'operator' => 'is', 'value' => 'yes' ],
							],
						],
					],
				],
			];

			$_POST = [
				'form_id'            => 7,
				'field_trigger'      => 'yes',
				'field_other'        => 'no',
				'field_conditional'  => '',
			];

			try {
				$this->submission->ajax_submit_form();
				$this->fail( 'ajax_submit_form() returned without sending a JSON response.' );
			} catch ( \FTA_Test_Ajax_Response $response ) {
				$this->assertFalse( $response->success );
				$this->assertArrayHasKey( 'field_conditional', $response->data['errors'] );
			}
		}

		public function test_a_checkbox_trigger_is_evaluated_against_its_submitted_array_of_checked_values() {
			$GLOBALS['fta_test_ajax_forms'][7] = [
				'id'     => 7,
				'status' => 'active',
				'fields' => [
					[ 'id' => 'field_colors', 'type' => 'checkboxes', 'label' => 'Colors' ],
					[
						'id'               => 'field_conditional',
						'type'             => 'text',
						'label'            => 'Conditional',
						'required'         => true,
						'conditionalLogic' => [
							'enabled'    => true,
							'action'     => 'show',
							'match'      => 'all',
							'conditions' => [ [ 'field' => 'field_colors', 'operator' => 'contains', 'value' => 'blue' ] ],
						],
					],
				],
			];

			// "red" is checked, but not "blue" - the condition is unmet, so
			// the field is hidden and the empty required value is allowed.
			$_POST = [
				'form_id'           => 7,
				'field_colors'      => [ 'red' ],
				'field_conditional' => '',
			];

			try {
				$this->submission->ajax_submit_form();
			} catch ( \FTA_Test_Ajax_Response $response ) {
				$this->assertTrue( $response->success );
				return;
			}

			$this->fail( 'ajax_submit_form() returned without sending a JSON response.' );
		}

		/**
		 * A field with no conditional logic at all behaves exactly as
		 * before: required means required.
		 */
		public function test_a_field_without_conditional_logic_is_unaffected() {
			$GLOBALS['fta_test_ajax_forms'][7] = [
				'id'     => 7,
				'status' => 'active',
				'fields' => [
					[ 'id' => 'field_conditional', 'type' => 'text', 'label' => 'Conditional', 'required' => true ],
				],
			];

			$_POST = [ 'form_id' => 7, 'field_conditional' => '' ];

			try {
				$this->submission->ajax_submit_form();
			} catch ( \FTA_Test_Ajax_Response $response ) {
				$this->assertFalse( $response->success );
				$this->assertArrayHasKey( 'field_conditional', $response->data['errors'] );
				return;
			}

			$this->fail( 'ajax_submit_form() returned without sending a JSON response.' );
		}
	}
}
