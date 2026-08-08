<?php
/**
 * reCAPTCHA token flow tests.
 *
 * Covers the two halves of the flow that can be checked without a browser:
 * the shared configuration helper that decides whether reCAPTCHA is active,
 * and the server-side verification of the token the browser sends back.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Submission;
use Formtura\Tests\TestCase;

class RecaptchaTest extends TestCase {

	/**
	 * @var Submission
	 */
	private $submission;

	protected function setUp(): void {
		parent::setUp();

		$this->submission = new Submission();

		$GLOBALS['fta_test_options'] = [];
		unset( $GLOBALS['fta_test_http_handler'] );
		$_POST = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'], $GLOBALS['fta_test_http_handler'] );
		$_POST = [];

		parent::tearDown();
	}

	/**
	 * Seed plugin settings.
	 *
	 * @param array $settings Settings to store.
	 */
	private function settings( array $settings ) {
		$GLOBALS['fta_test_options']['fta_settings'] = $settings;
	}

	/**
	 * Register the siteverify response tests should see.
	 *
	 * @param array $body Decoded response body.
	 */
	private function siteverifyReturns( array $body ) {
		$GLOBALS['fta_test_http_handler'] = function ( $url, $args ) use ( $body ) {
			$this->assertSame( 'https://www.google.com/recaptcha/api/siteverify', $url );

			return [ 'body' => json_encode( $body ) ];
		};
	}

	/**
	 * Call the private validator.
	 *
	 * @return true|\WP_Error
	 */
	private function validate() {
		$reflection = new \ReflectionMethod( Submission::class, 'validate_recaptcha' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->submission );
	}

	public function test_config_is_disabled_when_no_keys_are_set() {
		$config = fta_get_recaptcha_config();

		$this->assertFalse( $config['enabled'] );
	}

	public function test_config_is_disabled_when_only_the_secret_key_is_set() {
		$this->settings( [ 'recaptcha_secret_key' => 'secret' ] );

		$this->assertFalse( fta_get_recaptcha_config()['enabled'] );
	}

	public function test_config_is_disabled_when_only_the_site_key_is_set() {
		$this->settings( [ 'recaptcha_site_key' => 'site' ] );

		$this->assertFalse( fta_get_recaptcha_config()['enabled'] );
	}

	public function test_config_is_enabled_when_both_keys_are_set() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
		] );

		$config = fta_get_recaptcha_config();

		$this->assertTrue( $config['enabled'] );
		$this->assertSame( 'site', $config['site_key'] );
		$this->assertSame( 'secret', $config['secret_key'] );
		$this->assertSame( 'v2', $config['version'] );
	}

	public function test_config_falls_back_to_v2_for_an_unknown_version() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
			'recaptcha_version'    => 'v9',
		] );

		$this->assertSame( 'v2', fta_get_recaptcha_config()['version'] );
	}

	public function test_config_clamps_the_score_threshold_to_the_valid_range() {
		$this->settings( [
			'recaptcha_site_key'        => 'site',
			'recaptcha_secret_key'      => 'secret',
			'recaptcha_score_threshold' => '5',
		] );

		$this->assertSame( 1.0, fta_get_recaptcha_config()['score_threshold'] );
	}

	public function test_submission_passes_when_recaptcha_is_not_configured() {
		$this->assertTrue( $this->validate() );
	}

	/**
	 * The bug this suite exists for: a secret key on its own used to reject
	 * every submission, because nothing ever produced a token.
	 */
	public function test_submission_passes_when_only_the_secret_key_is_set() {
		$this->settings( [ 'recaptcha_secret_key' => 'secret' ] );

		$this->assertTrue( $this->validate() );
	}

	public function test_missing_token_is_rejected_with_its_own_message() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
		] );

		$result = $this->validate();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'recaptcha_missing', $result->get_error_code() );
	}

	public function test_valid_v2_token_passes() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
		] );
		$_POST['g-recaptcha-response'] = 'token';
		$this->siteverifyReturns( [ 'success' => true ] );

		$this->assertTrue( $this->validate() );
	}

	public function test_token_rejected_by_google_fails() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
		] );
		$_POST['g-recaptcha-response'] = 'token';
		$this->siteverifyReturns( [
			'success'     => false,
			'error-codes' => [ 'timeout-or-duplicate' ],
		] );

		$result = $this->validate();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'recaptcha_failed', $result->get_error_code() );
	}

	public function test_transport_failure_fails_closed() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
		] );
		$_POST['g-recaptcha-response'] = 'token';
		// No handler registered, so wp_remote_post() returns a WP_Error.

		$this->assertInstanceOf( \WP_Error::class, $this->validate() );
	}

	public function test_v3_token_below_the_score_threshold_fails() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
			'recaptcha_version'    => 'v3',
		] );
		$_POST['g-recaptcha-response'] = 'token';
		$this->siteverifyReturns( [
			'success' => true,
			'score'   => 0.1,
			'action'  => 'formtura_submit',
		] );

		$result = $this->validate();

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'recaptcha_failed', $result->get_error_code() );
	}

	public function test_v3_token_at_or_above_the_score_threshold_passes() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
			'recaptcha_version'    => 'v3',
		] );
		$_POST['g-recaptcha-response'] = 'token';
		$this->siteverifyReturns( [
			'success' => true,
			'score'   => 0.5,
			'action'  => 'formtura_submit',
		] );

		$this->assertTrue( $this->validate() );
	}

	/**
	 * A token minted for another action on the same site must not be replayable
	 * against the form endpoint.
	 */
	public function test_v3_token_for_another_action_fails() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
			'recaptcha_version'    => 'v3',
		] );
		$_POST['g-recaptcha-response'] = 'token';
		$this->siteverifyReturns( [
			'success' => true,
			'score'   => 0.9,
			'action'  => 'login',
		] );

		$this->assertInstanceOf( \WP_Error::class, $this->validate() );
	}

	/**
	 * v2 responses carry no score, so the v3 threshold must not be applied.
	 */
	public function test_v2_response_without_a_score_passes() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
			'recaptcha_version'    => 'v2',
		] );
		$_POST['g-recaptcha-response'] = 'token';
		$this->siteverifyReturns( [ 'success' => true ] );

		$this->assertTrue( $this->validate() );
	}

	public function test_token_is_sent_to_google_unslashed() {
		$this->settings( [
			'recaptcha_site_key'   => 'site',
			'recaptcha_secret_key' => 'secret',
		] );
		// WordPress slashes $_POST; the token must be unslashed before use.
		$_POST['g-recaptcha-response'] = 'abc\\/def';

		$GLOBALS['fta_test_http_handler'] = function ( $url, $args ) {
			$this->assertSame( 'abc/def', $args['body']['response'] );
			$this->assertSame( 'secret', $args['body']['secret'] );

			return [ 'body' => json_encode( [ 'success' => true ] ) ];
		};

		$this->assertTrue( $this->validate() );
	}
}
