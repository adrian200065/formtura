<?php
/**
 * Tests for the canonical uninstall preference in Settings::sanitize_settings().
 *
 * `delete_data_on_uninstall` is the only key the uninstall routine consults.
 * Two failure modes are covered here because both are silent:
 *
 * 1. The sanitizer writing a different key than the settings view posts, which
 *    is how the checkbox came to save nothing at all.
 * 2. An unchecked checkbox leaving a previously saved `true` in place. Browsers
 *    omit unchecked checkboxes from the request, so a sanitizer that only acts
 *    when the key `isset()` can never turn destruction back off.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Settings;
use Formtura\Tests\TestCase;

class UninstallSettingsTest extends TestCase {

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

	public function test_checked_delete_setting_is_saved_as_true() {
		$this->assertTrue( $this->sanitize( [ 'delete_data_on_uninstall' => '1' ] )['delete_data_on_uninstall'] );
	}

	public function test_missing_delete_setting_is_saved_as_false() {
		$this->assertFalse( $this->sanitize( [] )['delete_data_on_uninstall'] );
	}

	/**
	 * An unchecked box must overwrite a stored `true`, not inherit it.
	 */
	public function test_unchecked_delete_setting_overrides_stored_true() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'delete_data_on_uninstall' => true ];

		$this->assertFalse( $this->sanitize( [] )['delete_data_on_uninstall'] );
	}

	/**
	 * The obsolete standalone key must not reappear in saved settings.
	 */
	public function test_legacy_keep_data_key_is_not_written() {
		$this->assertArrayNotHasKey(
			'keep_data_on_uninstall',
			$this->sanitize( [ 'keep_data_on_uninstall' => '1' ] )
		);
	}

	public function test_defaults_retain_data() {
		$this->assertFalse( $this->settings->get_defaults()['delete_data_on_uninstall'] );
	}
}
