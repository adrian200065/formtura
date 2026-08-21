<?php
/**
 * Settings::ajax_save_settings() - merging and the update_option() false/failure conflation.
 *
 * Two defects met here: sanitize_settings() only returns the keys present in
 * the current submission, but the handler used to hand that partial array
 * straight to update_option(), replacing the whole `fta_settings` option and
 * silently dropping every key the settings screen has no control for
 * (currency, load_css, load_js, debug_mode). And update_option()
 * returns false both on failure and when the new value is identical to the
 * old one, which the handler used to treat as an error.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Settings;
use Formtura\Tests\TestCase;

class SettingsSaveTest extends TestCase {

	/**
	 * @var Settings
	 */
	private $settings;

	protected function setUp(): void {
		parent::setUp();

		$this->settings                         = new Settings();
		$_POST                                  = [];
		$GLOBALS['fta_test_options']            = [];
		$GLOBALS['fta_test_current_user_can']   = true;
		$GLOBALS['fta_test_ajax_referer_valid'] = true;
	}

	protected function tearDown(): void {
		$_POST = [];
		unset(
			$GLOBALS['fta_test_options'],
			$GLOBALS['fta_test_current_user_can'],
			$GLOBALS['fta_test_ajax_referer_valid']
		);

		parent::tearDown();
	}

	/**
	 * Call ajax_save_settings() and capture the response the wp_send_json_*
	 * stubs throw instead of exiting the process with.
	 *
	 * @return \FTA_Test_Ajax_Response
	 */
	private function callSave() {
		try {
			$this->settings->ajax_save_settings();
		} catch ( \FTA_Test_Ajax_Response $response ) {
			return $response;
		}

		$this->fail( 'ajax_save_settings() returned without calling wp_send_json_success() or wp_send_json_error().' );
	}

	private function storedSettings() {
		return $GLOBALS['fta_test_options']['fta_settings'];
	}

	public function test_saving_from_the_settings_screen_preserves_keys_it_has_no_control_for() {
		$GLOBALS['fta_test_options']['fta_settings'] = [
			'currency'    => 'EUR',
			'load_css'    => false,
			'load_js'     => false,
			'debug_mode'  => true,
		];

		$_POST['settings'] = [ 'from_name' => 'New Sender' ];

		$this->callSave();

		$stored = $this->storedSettings();

		$this->assertSame( 'EUR', $stored['currency'] );
		$this->assertFalse( $stored['load_css'] );
		$this->assertFalse( $stored['load_js'] );
		$this->assertTrue( $stored['debug_mode'] );
		$this->assertSame( 'New Sender', $stored['from_name'] );
	}

	public function test_saving_unchanged_settings_reports_success() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'from_name' => 'Same Name' ];

		$_POST['settings'] = [ 'from_name' => 'Same Name' ];

		$response = $this->callSave();

		$this->assertTrue( $response->success );
	}

	public function test_saving_a_changed_setting_reports_success() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'from_name' => 'Old Name' ];

		$_POST['settings'] = [ 'from_name' => 'New Name' ];

		$response = $this->callSave();

		$this->assertTrue( $response->success );
		$this->assertSame( 'New Name', $this->storedSettings()['from_name'] );
	}
}
