<?php
/**
 * The success message returned after a submission.
 *
 * The builder saves this setting as `successMessage` (see
 * Form_Builder::sanitize_settings_data(), which normalises both the
 * camelCase key React posts and a legacy snake_case one to camelCase before
 * storage). ajax_submit_form() read `success_message` - a key that
 * normalisation never produces - so a custom success message set in the
 * builder never reached a visitor; every form silently fell back to the
 * built-in default text.
 *
 * fta_get_form() and fta_create_entry() normally hit the database, which is
 * unavailable in this suite. The namespace-scoped overrides below are
 * resolved in preference to the global functions because Submission.php
 * calls them unqualified from inside Formtura\Frontend - the same pattern
 * SubmissionEntryFailureCleanupTest and SubmissionCouponAjaxTest use.
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

	class SubmissionSuccessMessageTest extends TestCase {

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
		 * Submit a minimal one-field form and capture the AJAX response the
		 * wp_send_json_* stubs throw instead of exiting the process with.
		 *
		 * @param array $settings Form settings, in the shape the builder saves
		 *        them.
		 * @return \FTA_Test_Ajax_Response
		 */
		private function submit( array $settings ) {
			$GLOBALS['fta_test_ajax_forms'][7] = [
				'id'       => 7,
				'status'   => 'active',
				'fields'   => [
					[ 'id' => 'field_name', 'type' => 'text', 'label' => 'Name' ],
				],
				'settings' => $settings,
			];

			$_POST = [
				'form_id'    => 7,
				'field_name' => 'Ada',
			];

			try {
				$this->submission->ajax_submit_form();
			} catch ( \FTA_Test_Ajax_Response $response ) {
				return $response;
			}

			$this->fail( 'ajax_submit_form() returned without sending a JSON response.' );
		}

		public function test_a_custom_success_message_saved_by_the_builder_is_returned() {
			$response = $this->submit( [ 'successMessage' => 'Thanks, Ada - we will be in touch!' ] );

			$this->assertTrue( $response->success );
			$this->assertSame( 'Thanks, Ada - we will be in touch!', $response->data['message'] );
		}

		public function test_the_default_message_is_used_when_no_setting_is_saved() {
			$response = $this->submit( [] );

			$this->assertTrue( $response->success );
			$this->assertStringContainsString( 'submitted successfully', $response->data['message'] );
		}
	}
}
