<?php
/**
 * Tests for the anti-abuse settings Settings::sanitize_settings() feeds to
 * Submission's rate limiting and trusted-proxy-aware IP resolution:
 * `submission_rate_limit` and `trusted_proxies`.
 *
 * Modeled on RetentionSettingsTest.php.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Settings;
use Formtura\Tests\TestCase;

class AntiAbuseSettingsSanitizeTest extends TestCase {

	/**
	 * @var Settings
	 */
	private $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new Settings();
	}

	/**
	 * Call the private sanitize_settings( $settings ).
	 *
	 * @param array $settings Raw settings, as posted by the settings form.
	 * @return array Sanitized settings.
	 */
	private function sanitize( array $settings ) {
		$reflection = new \ReflectionMethod( Settings::class, 'sanitize_settings' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->settings, $settings );
	}

	public function test_default_rate_limit_is_ten() {
		$this->assertSame( 10, $this->settings->get_defaults()['submission_rate_limit'] );
	}

	public function test_a_posted_rate_limit_is_saved_as_an_integer() {
		$this->assertSame( 25, $this->sanitize( [ 'submission_rate_limit' => '25' ] )['submission_rate_limit'] );
	}

	public function test_a_negative_rate_limit_is_clamped_to_zero() {
		$this->assertSame( 0, $this->sanitize( [ 'submission_rate_limit' => '-5' ] )['submission_rate_limit'] );
	}

	public function test_a_missing_rate_limit_is_not_written() {
		$this->assertArrayNotHasKey( 'submission_rate_limit', $this->sanitize( [] ) );
	}

	public function test_trusted_proxies_default_to_empty() {
		$this->assertSame( '', $this->settings->get_defaults()['trusted_proxies'] );
	}

	public function test_valid_ip_and_cidr_entries_are_kept() {
		$result = $this->sanitize( [
			'trusted_proxies' => "203.0.113.7\n198.51.100.0/24\n2001:db8::1",
		] );

		$this->assertSame( "203.0.113.7\n198.51.100.0/24\n2001:db8::1", $result['trusted_proxies'] );
	}

	public function test_invalid_entries_are_dropped() {
		$result = $this->sanitize( [
			'trusted_proxies' => "203.0.113.7\nnot-an-ip\n<script>alert(1)</script>",
		] );

		$this->assertSame( '203.0.113.7', $result['trusted_proxies'] );
	}

	public function test_blank_lines_are_ignored() {
		$result = $this->sanitize( [
			'trusted_proxies' => "203.0.113.7\n\n\n198.51.100.7",
		] );

		$this->assertSame( "203.0.113.7\n198.51.100.7", $result['trusted_proxies'] );
	}

	public function test_a_missing_trusted_proxies_value_is_not_written() {
		$this->assertArrayNotHasKey( 'trusted_proxies', $this->sanitize( [] ) );
	}
}
