<?php
/**
 * Adversarial tests for the fta_validate_coupon AJAX endpoint.
 *
 * The endpoint is display convenience only - PaymentTotals re-validates the
 * code independently on submission - but it still accepts a form id, a
 * field id and a code from anyone, unauthenticated. These tests exercise
 * exactly that boundary: a missing/wrong nonce, a field id that belongs to
 * a different form, a field id that resolves but is not a coupon field, and
 * whether any of those failure shapes can be told apart from a simple wrong
 * code by the message alone.
 *
 * fta_get_form() normally hits the database (Forms_DB -> $wpdb), which is
 * unavailable in this suite. A namespace-scoped override below - resolved
 * in preference to the global function because Submission.php calls
 * fta_get_form() unqualified from inside Formtura\Frontend - lets these
 * tests hand it fixed form data without touching the database.
 *
 * @package Formtura
 */

namespace Formtura\Frontend {
	if ( ! function_exists( __NAMESPACE__ . '\\fta_get_form' ) ) {
		/**
		 * Test double for fta_get_form(), scoped to this namespace only.
		 * Tests seed $GLOBALS['fta_test_ajax_forms'][ $form_id ].
		 *
		 * @param int $form_id Form ID.
		 * @return array|null
		 */
		function fta_get_form( $form_id ) {
			return isset( $GLOBALS['fta_test_ajax_forms'][ $form_id ] )
				? $GLOBALS['fta_test_ajax_forms'][ $form_id ]
				: null;
		}
	}
}

namespace Formtura\Tests\Unit\Frontend {

	use Formtura\Frontend\Submission;
	use Formtura\Tests\TestCase;

	class SubmissionCouponAjaxTest extends TestCase {

		/**
		 * @var Submission
		 */
		private $submission;

		protected function setUp(): void {
			parent::setUp();

			$this->submission = new Submission();
			$_POST            = [];
			$_SERVER['REMOTE_ADDR']                  = '203.0.113.1';
			$GLOBALS['fta_test_ajax_forms']          = [];
			$GLOBALS['fta_test_ajax_referer_valid']  = true;
			$GLOBALS['fta_test_transients']          = [];
		}

		protected function tearDown(): void {
			$_POST = [];
			unset( $_SERVER['REMOTE_ADDR'] );
			unset( $GLOBALS['fta_test_ajax_forms'], $GLOBALS['fta_test_ajax_referer_valid'], $GLOBALS['fta_test_transients'] );

			parent::tearDown();
		}

		/**
		 * Call ajax_validate_coupon() and capture the response the
		 * wp_send_json_* stubs throw instead of exiting the process with.
		 *
		 * @return \FTA_Test_Ajax_Response
		 */
		private function callAjax() {
			try {
				$this->submission->ajax_validate_coupon();
			} catch ( \FTA_Test_Ajax_Response $response ) {
				return $response;
			}

			$this->fail( 'ajax_validate_coupon() returned without calling wp_send_json_success() or wp_send_json_error().' );
		}

		private function couponForm( $id, array $coupons ) {
			return [
				'id'     => $id,
				'fields' => [
					[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => $coupons ],
				],
			];
		}

		/**
		 * A missing or incorrect nonce must halt the request before the form
		 * is even looked up - check_ajax_referer's default $stop = true dies
		 * in real WordPress. Nothing about the coupon or the form should be
		 * reachable past this point.
		 */
		public function test_invalid_nonce_halts_before_touching_the_form() {
			$GLOBALS['fta_test_ajax_referer_valid'] = false;
			$GLOBALS['fta_test_ajax_forms'][7]       = $this->couponForm( 7, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] );

			$_POST = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];

			$response = $this->callAjax();

			$this->assertFalse( $response->success );
		}

		/**
		 * A field id that is real, but belongs to a different form than the
		 * one named in form_id, must not validate - the lookup is scoped to
		 * the requested form's own field list, not a global field id index.
		 */
		public function test_field_id_from_a_different_form_is_rejected() {
			$GLOBALS['fta_test_ajax_forms'][1] = $this->couponForm( 1, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] );
			// Form 2 has no coupon field at all, let alone one named
			// field_coupon - but the id string is identical to form 1's.
			$GLOBALS['fta_test_ajax_forms'][2] = [
				'id'     => 2,
				'fields' => [
					[ 'id' => 'field_name', 'type' => 'text', 'label' => 'Name' ],
				],
			];

			$_POST = [ 'form_id' => 2, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];

			$response = $this->callAjax();

			$this->assertFalse( $response->success, 'A field id must not validate against a different form\'s coupon list.' );
		}

		/**
		 * A field id that resolves within the correct form, but names a
		 * field that is not of type "coupon", must not be treated as one -
		 * a text field sharing an id with a coupon field must not leak
		 * whatever a crafted request tries to read through it.
		 */
		public function test_non_coupon_field_id_is_rejected() {
			$GLOBALS['fta_test_ajax_forms'][7] = [
				'id'     => 7,
				'fields' => [
					[ 'id' => 'field_coupon', 'type' => 'text', 'label' => 'Not actually a coupon' ],
				],
			];

			$_POST = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'ANY' ];

			$response = $this->callAjax();

			$this->assertFalse( $response->success );
		}

		/**
		 * A code that matches must return exactly the shape find_coupon()
		 * produces - code, type, value - and nothing else. In particular,
		 * the response must not carry the field's full coupon list; only
		 * the single matched entry the visitor already typed.
		 */
		public function test_valid_code_response_carries_only_the_matched_coupon() {
			$GLOBALS['fta_test_ajax_forms'][7] = $this->couponForm( 7, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
				[ 'code' => 'SECRET50', 'type' => 'percent', 'value' => '50' ],
			] );

			$_POST = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'save5' ];

			$response = $this->callAjax();

			$this->assertTrue( $response->success );
			$this->assertSame( [ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => 5.0 ], $response->data );
		}

		/**
		 * The core anti-enumeration property: a wrong code, a form that
		 * doesn't exist, a field id from another form, and a field id that
		 * isn't a coupon field must all be indistinguishable from the
		 * response alone. If any of them produced a different message, a
		 * visitor probing the endpoint could learn something about the form
		 * definition beyond "that code didn't work."
		 */
		public function test_every_failure_shape_returns_the_identical_message() {
			$GLOBALS['fta_test_ajax_forms'][7] = $this->couponForm( 7, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] );
			$GLOBALS['fta_test_ajax_forms'][8] = [
				'id'     => 8,
				'fields' => [ [ 'id' => 'field_coupon', 'type' => 'text' ] ],
			];
			$GLOBALS['fta_test_ajax_forms'][9] = array_merge(
				$this->couponForm( 9, [ [ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ] ] ),
				[ 'status' => 'inactive' ]
			);

			$cases = [
				'wrong code'               => [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'NOPE' ],
				'form does not exist'      => [ 'form_id' => 999, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ],
				'field id from other form' => [ 'form_id' => 8, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ],
				'non-coupon field id'      => [ 'form_id' => 8, 'field_id' => 'field_coupon', 'code' => 'ANY' ],
				'inactive form'            => [ 'form_id' => 9, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ],
			];

			$messages = [];

			foreach ( $cases as $label => $post ) {
				$_POST    = $post;
				$response = $this->callAjax();

				$this->assertFalse( $response->success, "Case '{$label}' unexpectedly succeeded." );
				$messages[ $label ] = $response->data['message'];
			}

			$this->assertSame( 1, count( array_unique( $messages ) ), 'Every failure shape must produce the same message: ' . wp_json_encode( $messages ) );
		}

		/**
		 * An empty code, field id, or missing form id must fail the same
		 * generic way rather than a different error path (e.g. a PHP notice
		 * from indexing into a null form).
		 */
		public function test_missing_form_id_is_rejected_without_touching_field_lookup() {
			$_POST = [ 'form_id' => 0, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];

			$response = $this->callAjax();

			$this->assertFalse( $response->success );
		}

		/**
		 * An inactive form's coupon codes must not be probeable through this
		 * endpoint - unlike ajax_submit_form(), which tells a visitor a form
		 * is inactive (they already reached the page), this endpoint would
		 * otherwise let a request confirm a code is genuinely valid on a form
		 * that isn't accepting submissions at all.
		 */
		public function test_inactive_form_rejects_an_otherwise_valid_code() {
			$GLOBALS['fta_test_ajax_forms'][7] = array_merge(
				$this->couponForm( 7, [ [ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ] ] ),
				[ 'status' => 'inactive' ]
			);

			$_POST = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];

			$response = $this->callAjax();

			$this->assertFalse( $response->success );
		}

		/**
		 * A form with no status key at all (the common case - status
		 * defaults to active) must still validate normally. The inactive
		 * check above must not have turned into "no status set => rejected."
		 */
		public function test_form_with_no_status_key_still_validates() {
			$GLOBALS['fta_test_ajax_forms'][7] = $this->couponForm( 7, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] );

			$_POST = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];

			$response = $this->callAjax();

			$this->assertTrue( $response->success );
		}

		/**
		 * The per-IP throttle: a real visitor mistyping a code a handful of
		 * times must never be blocked, and a subsequent correct attempt must
		 * still succeed - a few failures do not exhaust the budget.
		 */
		public function test_throttle_does_not_block_a_handful_of_failed_attempts() {
			$GLOBALS['fta_test_ajax_forms'][7] = $this->couponForm( 7, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] );

			for ( $i = 0; $i < 5; $i++ ) {
				$_POST    = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'NOPE' ];
				$response = $this->callAjax();

				$this->assertFalse( $response->success );
				$this->assertSame(
					'This coupon code is not valid.',
					$response->data['message'],
					"Attempt {$i} returned a different message than a normal wrong-code failure - it should not have been throttled yet."
				);
			}

			$_POST    = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];
			$response = $this->callAjax();

			$this->assertTrue( $response->success, 'A correct code after only 5 prior failures must still validate.' );
		}

		/**
		 * A successful validation must never consume throttle budget - a
		 * visitor applying a genuinely valid code repeatedly (e.g. re-reading
		 * the total, or a NAT/CGNAT egress shared with other visitors doing
		 * the same) must not eventually be told their working code "is not
		 * valid" just because a counter filled up on successes.
		 */
		public function test_successful_validation_does_not_consume_throttle_budget() {
			$GLOBALS['fta_test_ajax_forms'][7] = $this->couponForm( 7, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] );

			// Comfortably more than the 20-attempt failure budget - if
			// successes counted against it, this would start failing well
			// before the 25th attempt.
			for ( $i = 0; $i < 25; $i++ ) {
				$_POST    = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];
				$response = $this->callAjax();

				$this->assertTrue( $response->success, "Successful attempt {$i} was unexpectedly throttled." );
			}
		}

		/**
		 * Repeated failures - and only failures - eventually throttle, with
		 * the identical generic message rather than a distinguishable
		 * "too many attempts" response that would itself leak information
		 * about how the limit works.
		 */
		public function test_repeated_failures_eventually_throttle_with_the_identical_message() {
			$GLOBALS['fta_test_ajax_forms'][7] = $this->couponForm( 7, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] );

			$last = null;

			for ( $i = 0; $i < 21; $i++ ) {
				$_POST = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'NOPE' ];
				$last  = $this->callAjax();
			}

			$this->assertFalse( $last->success, 'The 21st failed attempt from the same IP must be throttled.' );
			$this->assertSame( 'This coupon code is not valid.', $last->data['message'] );

			// Throttled means throttled: even the genuinely correct code is
			// refused once the budget is spent, since granting it here would
			// just be a slower way to confirm the same code the sweep is
			// trying to find.
			$_POST    = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];
			$response = $this->callAjax();

			$this->assertFalse( $response->success, 'A correct code must also be refused once the IP is throttled.' );
		}

		/**
		 * The throttle key is per-IP: a different visitor (different
		 * REMOTE_ADDR) must not inherit another visitor's exhausted budget.
		 */
		public function test_throttle_is_scoped_per_ip() {
			$GLOBALS['fta_test_ajax_forms'][7] = $this->couponForm( 7, [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5' ],
			] );

			for ( $i = 0; $i < 21; $i++ ) {
				$_POST = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'NOPE' ];
				$this->callAjax();
			}

			// A different visitor arrives on the same form.
			$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
			$_POST                  = [ 'form_id' => 7, 'field_id' => 'field_coupon', 'code' => 'SAVE5' ];

			$response = $this->callAjax();

			$this->assertTrue( $response->success, 'A different IP must not be throttled by another visitor\'s attempts.' );
		}
	}
}
