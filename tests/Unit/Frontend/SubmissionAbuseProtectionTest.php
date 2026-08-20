<?php
/**
 * Baseline abuse protection for anonymous form submissions: a honeypot
 * field, a per-IP rate limit, and trusted-proxy-aware IP resolution.
 *
 * Before this, ajax_submit_form() had no honeypot and no throttle of its
 * own - reCAPTCHA was the only thing standing between an anonymous visitor
 * and a database entry, a stored file, and an outgoing email, and sites that
 * leave reCAPTCHA unconfigured had nothing at all. get_user_ip() also
 * trusted HTTP_CLIENT_IP/HTTP_X_FORWARDED_FOR unconditionally, both of which
 * are attacker-controlled, so the per-IP throttle (and anything else keyed
 * on it) could be defeated just by rotating the header on every request.
 *
 * fta_get_form() and fta_create_entry() normally hit the database, which is
 * unavailable in this suite. The namespace-scoped overrides below follow the
 * same pattern as SubmissionSuccessMessageTest.
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
			return isset( $GLOBALS['fta_test_created_entry_id'] ) ? $GLOBALS['fta_test_created_entry_id'] : 1;
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

	class SubmissionAbuseProtectionTest extends TestCase {

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
			$GLOBALS['fta_test_transients']          = [];
			$GLOBALS['fta_test_options']             = [];
		}

		protected function tearDown(): void {
			$_POST = [];
			unset(
				$_SERVER['REMOTE_ADDR'],
				$_SERVER['HTTP_X_FORWARDED_FOR'],
				$_SERVER['HTTP_CLIENT_IP'],
				$GLOBALS['fta_test_ajax_forms'],
				$GLOBALS['fta_test_ajax_referer_valid'],
				$GLOBALS['fta_test_actions'],
				$GLOBALS['fta_test_created_entry_id'],
				$GLOBALS['fta_test_transients'],
				$GLOBALS['fta_test_options']
			);

			parent::tearDown();
		}

		/**
		 * Number of real entries created so far, inferred from how many times
		 * fta_after_form_submission fired - it is only ever called after
		 * fta_create_entry() succeeds (see ajax_submit_form()), so it stands
		 * in for a call count on fta_create_entry() itself without needing a
		 * second stateful override of that function.
		 *
		 * @return int
		 */
		private function entriesCreated() {
			return count( array_filter(
				$GLOBALS['fta_test_actions'],
				function ( $hook ) {
					return 'fta_after_form_submission' === $hook;
				}
			) );
		}

		private function form( array $settings = [] ) {
			$GLOBALS['fta_test_ajax_forms'][7] = [
				'id'       => 7,
				'status'   => 'active',
				'fields'   => [
					[ 'id' => 'field_name', 'type' => 'text', 'label' => 'Name' ],
				],
				'settings' => $settings,
			];
		}

		/**
		 * Submit and capture the AJAX response the wp_send_json_* stubs throw
		 * instead of exiting the process with.
		 *
		 * @param array $post Extra $_POST fields beyond form_id/field_name.
		 * @return \FTA_Test_Ajax_Response
		 */
		private function submit( array $post = [] ) {
			$_POST = array_merge(
				[
					'form_id'    => 7,
					'field_name' => 'Ada',
				],
				$post
			);

			try {
				$this->submission->ajax_submit_form();
			} catch ( \FTA_Test_Ajax_Response $response ) {
				return $response;
			}

			$this->fail( 'ajax_submit_form() returned without sending a JSON response.' );
		}

		private function callGetUserIp() {
			$reflection = new \ReflectionMethod( Submission::class, 'get_user_ip' );
			$reflection->setAccessible( true );

			return $reflection->invoke( $this->submission );
		}

		// --- Honeypot -------------------------------------------------

		public function test_a_filled_honeypot_field_is_reported_as_success_with_no_entry_created() {
			$this->form();

			$response = $this->submit( [ 'fta_hp' => 'I am a bot' ] );

			$this->assertTrue( $response->success );
			$this->assertSame( 0, $this->entriesCreated(), 'A honeypot trip must not create an entry.' );
		}

		public function test_a_filled_honeypot_field_does_not_send_notifications() {
			$this->form();

			$this->submit( [ 'fta_hp' => 'I am a bot' ] );

			$this->assertSame( [], $GLOBALS['fta_test_actions'], 'A honeypot trip must not fire fta_after_form_submission.' );
		}

		public function test_a_filled_honeypot_field_returns_the_real_success_message() {
			$this->form( [ 'successMessage' => 'Thanks, Ada!' ] );

			$response = $this->submit( [ 'fta_hp' => 'spam' ] );

			$this->assertSame( 'Thanks, Ada!', $response->data['message'] );
		}

		public function test_an_empty_honeypot_field_submits_normally() {
			$this->form();

			$response = $this->submit( [ 'fta_hp' => '' ] );

			$this->assertTrue( $response->success );
			$this->assertSame( 1, $this->entriesCreated() );
		}

		public function test_a_missing_honeypot_field_submits_normally() {
			$this->form();

			$response = $this->submit();

			$this->assertTrue( $response->success );
			$this->assertSame( 1, $this->entriesCreated() );
		}

		// --- Rate limiting ----------------------------------------------

		public function test_submissions_within_the_limit_all_succeed() {
			$this->form();
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'submission_rate_limit' => 3 ];

			for ( $i = 0; $i < 3; $i++ ) {
				$response = $this->submit();
				$this->assertTrue( $response->success, "Submission {$i} should not be throttled." );
			}
		}

		public function test_a_submission_beyond_the_limit_is_rejected() {
			$this->form();
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'submission_rate_limit' => 3 ];

			for ( $i = 0; $i < 3; $i++ ) {
				$this->submit();
			}

			$response = $this->submit();

			$this->assertFalse( $response->success );
			$this->assertSame( 3, $this->entriesCreated(), 'The throttled attempt must not have created a 4th entry.' );
		}

		public function test_the_limit_is_scoped_per_ip() {
			$this->form();
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'submission_rate_limit' => 1 ];

			$this->submit();

			$_SERVER['REMOTE_ADDR'] = '198.51.100.7';
			$response               = $this->submit();

			$this->assertTrue( $response->success, 'A different IP must not inherit another visitor\'s exhausted budget.' );
		}

		public function test_zero_disables_the_rate_limit() {
			$this->form();
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'submission_rate_limit' => 0 ];

			for ( $i = 0; $i < 15; $i++ ) {
				$response = $this->submit();
				$this->assertTrue( $response->success, "Submission {$i} should not be throttled when the limit is 0." );
			}
		}

		public function test_a_throttled_submission_still_reports_a_message() {
			$this->form();
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'submission_rate_limit' => 1 ];

			$this->submit();
			$response = $this->submit();

			$this->assertFalse( $response->success );
			$this->assertNotEmpty( $response->data['message'] );
		}

		// --- Trusted-proxy-aware IP resolution ---------------------------

		public function test_forwarded_header_is_ignored_by_default() {
			$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.50';

			$this->assertSame( '203.0.113.9', $this->callGetUserIp() );
		}

		public function test_forwarded_header_is_honored_from_a_trusted_proxy() {
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'trusted_proxies' => '203.0.113.9' ];
			$_SERVER['HTTP_X_FORWARDED_FOR']              = '198.51.100.50';

			$this->assertSame( '198.51.100.50', $this->callGetUserIp() );
		}

		public function test_forwarded_header_is_honored_from_a_trusted_cidr_range() {
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'trusted_proxies' => '203.0.113.0/24' ];
			$_SERVER['HTTP_X_FORWARDED_FOR']              = '198.51.100.50';

			$this->assertSame( '198.51.100.50', $this->callGetUserIp() );
		}

		public function test_forwarded_header_from_an_untrusted_proxy_is_ignored() {
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'trusted_proxies' => '192.0.2.1' ];
			$_SERVER['HTTP_X_FORWARDED_FOR']              = '198.51.100.50';

			$this->assertSame( '203.0.113.9', $this->callGetUserIp() );
		}

		public function test_the_leftmost_forwarded_address_is_used_as_the_client_ip() {
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'trusted_proxies' => '203.0.113.9' ];
			$_SERVER['HTTP_X_FORWARDED_FOR']              = '198.51.100.50, 203.0.113.9';

			$this->assertSame( '198.51.100.50', $this->callGetUserIp() );
		}

		public function test_a_malformed_forwarded_address_falls_back_to_remote_addr() {
			$GLOBALS['fta_test_options']['fta_settings'] = [ 'trusted_proxies' => '203.0.113.9' ];
			$_SERVER['HTTP_X_FORWARDED_FOR']              = 'not-an-ip';

			$this->assertSame( '203.0.113.9', $this->callGetUserIp() );
		}

		public function test_client_ip_header_is_no_longer_trusted() {
			$_SERVER['HTTP_CLIENT_IP'] = '198.51.100.50';

			$this->assertSame( '203.0.113.9', $this->callGetUserIp() );
		}
	}
}
