<?php
/**
 * Tests for the fields Settings::sanitize_settings() ignored entirely:
 * `disable_default_css`, `from_email`, `from_name`, and the plaintext
 * storage of `recaptcha_secret_key`.
 *
 * Modeled on UninstallSettingsTest.php / SMTPTest.php.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Secret_Crypto;
use Formtura\Admin\Settings;
use Formtura\Tests\TestCase;

class GeneralSettingsSanitizeTest extends TestCase {

	/**
	 * @var Settings
	 */
	private $settings;

	protected function setUp(): void {
		parent::setUp();

		$this->settings               = new Settings();
		$GLOBALS['fta_test_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );

		parent::tearDown();
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

	public function test_checked_disable_css_is_saved_as_true() {
		$this->assertTrue( $this->sanitize( [ 'disable_default_css' => '1' ] )['disable_default_css'] );
	}

	public function test_missing_disable_css_is_saved_as_false() {
		$this->assertFalse( $this->sanitize( [] )['disable_default_css'] );
	}

	/**
	 * An unchecked box must overwrite a stored `true`, not inherit it -
	 * sanitize_settings() itself has no memory of the old value, so this
	 * only proves the key is written unconditionally either way.
	 */
	public function test_unchecked_disable_css_is_written_as_false_even_when_absent() {
		$result = $this->sanitize( [] );

		$this->assertArrayHasKey( 'disable_default_css', $result );
		$this->assertFalse( $result['disable_default_css'] );
	}

	public function test_from_email_is_sanitized_as_an_email() {
		$this->assertSame(
			'owner@example.test',
			$this->sanitize( [ 'from_email' => ' owner@example.test ' ] )['from_email']
		);
	}

	public function test_from_name_is_sanitized_as_text() {
		$this->assertSame(
			'My Site',
			$this->sanitize( [ 'from_name' => '<b>My Site</b>' ] )['from_name']
		);
	}

	public function test_missing_from_email_and_from_name_are_not_written() {
		$result = $this->sanitize( [] );

		$this->assertArrayNotHasKey( 'from_email', $result );
		$this->assertArrayNotHasKey( 'from_name', $result );
	}

	public function test_a_submitted_recaptcha_secret_is_not_stored_as_plaintext() {
		$result = $this->sanitize( [ 'recaptcha_secret_key' => 'super-secret' ] );

		$this->assertNotSame( 'super-secret', $result['recaptcha_secret_key'] );
		$this->assertSame( 'super-secret', Secret_Crypto::decrypt( $result['recaptcha_secret_key'] ) );
	}

	/**
	 * The settings screen never re-renders a saved secret's real value (see
	 * GeneralSettingsViewTest), so the form always resubmits it blank unless
	 * the administrator is actively changing it.
	 */
	public function test_saving_with_a_blank_recaptcha_secret_keeps_the_existing_one() {
		$existing = Secret_Crypto::encrypt( 'super-secret' );
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'recaptcha_secret_key' => $existing ];

		$result = $this->sanitize( [ 'recaptcha_secret_key' => '' ] );

		$this->assertSame( $existing, $result['recaptcha_secret_key'] );
	}
}
