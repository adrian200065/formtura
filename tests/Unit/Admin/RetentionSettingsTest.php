<?php
/**
 * Tests for the entry_retention_days setting.
 *
 * Modeled on UninstallSettingsTest.php: the two failure modes that matter are
 * the sanitizer writing a different key than the settings view posts, and a
 * negative or non-numeric value producing something other than "disabled".
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Settings;
use Formtura\Tests\TestCase;

class RetentionSettingsTest extends TestCase {

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

	public function test_default_is_zero_meaning_disabled() {
		$this->assertSame( 0, $this->settings->get_defaults()['entry_retention_days'] );
	}

	public function test_a_posted_value_is_saved_as_an_integer() {
		$this->assertSame( 90, $this->sanitize( [ 'entry_retention_days' => '90' ] )['entry_retention_days'] );
	}

	public function test_a_negative_value_is_clamped_to_zero() {
		$this->assertSame( 0, $this->sanitize( [ 'entry_retention_days' => '-5' ] )['entry_retention_days'] );
	}

	public function test_a_missing_value_is_not_written() {
		$this->assertArrayNotHasKey( 'entry_retention_days', $this->sanitize( [] ) );
	}
}
