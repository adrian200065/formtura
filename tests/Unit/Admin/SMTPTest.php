<?php
/**
 * SMTP settings: saving, and applying saved settings to PHPMailer.
 *
 * Three defects met here: the settings screen posted the enable checkbox
 * under a different key than the code that reads it back, saving a new
 * password overwrote it in plaintext, and rendering the settings screen put
 * that plaintext back into the password field's value attribute.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Secret_Crypto;
use Formtura\Admin\SMTP;
use Formtura\Tests\TestCase;

class SMTPTest extends TestCase {

	/**
	 * @var SMTP
	 */
	private $smtp;

	protected function setUp(): void {
		parent::setUp();

		$this->smtp                             = new SMTP();
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
	 * Call ajax_save_smtp_settings() and capture the response the
	 * wp_send_json_* stubs throw instead of exiting the process with.
	 *
	 * @return \FTA_Test_Ajax_Response
	 */
	private function callSave() {
		try {
			$this->smtp->ajax_save_smtp_settings();
		} catch ( \FTA_Test_Ajax_Response $response ) {
			return $response;
		}

		$this->fail( 'ajax_save_smtp_settings() returned without calling wp_send_json_success() or wp_send_json_error().' );
	}

	private function storedSettings() {
		return $GLOBALS['fta_test_options']['fta_smtp_settings'];
	}

	/**
	 * A minimal PHPMailer double: only the properties/methods
	 * configure_phpmailer() touches.
	 */
	private function phpmailer() {
		return new class {
			public $Host;
			public $Port;
			public $SMTPAuth;
			public $Username;
			public $Password;
			public $SMTPSecure;
			public $fromEmail;
			public $fromName;

			public function isSMTP() {}

			public function setFrom( $email, $name ) {
				$this->fromEmail = $email;
				$this->fromName  = $name;
			}
		};
	}

	/**
	 * The view posts `smtp_settings[enabled]` (see SmtpSettingsViewTest) and
	 * configure_phpmailer() reads the same 'enabled' key - this pins that
	 * contract from the saving side so the two cannot drift apart again.
	 */
	public function test_the_enabled_flag_saves_and_is_read_back_under_the_same_key() {
		$_POST['smtp_settings'] = [ 'enabled' => '1' ];

		$this->callSave();

		$this->assertTrue( $this->storedSettings()['enabled'] );

		$mailer = $this->phpmailer();
		$this->smtp->configure_phpmailer( $mailer );

		// Reaching isSMTP()/setFrom() at all means configure_phpmailer() saw
		// the setting as enabled; assert on a value only set on that path.
		$this->assertNotNull( $mailer->fromEmail );
	}

	public function test_a_submitted_password_is_not_stored_as_plaintext() {
		$_POST['smtp_settings'] = [ 'smtp_password' => 'hunter2' ];

		$this->callSave();

		$this->assertNotSame( 'hunter2', $this->storedSettings()['smtp_password'] );
	}

	public function test_configure_smtp_decrypts_the_stored_password() {
		$GLOBALS['fta_test_options']['fta_smtp_settings'] = [
			'enabled'       => true,
			'smtp_password' => Secret_Crypto::encrypt( 'hunter2' ),
		];

		$mailer = $this->phpmailer();
		$this->smtp->configure_phpmailer( $mailer );

		$this->assertSame( 'hunter2', $mailer->Password );
	}

	/**
	 * The password field is never re-rendered with its real value (see
	 * SmtpSettingsViewTest), so the form always resubmits it blank unless the
	 * administrator is actively changing it. A blank submission must leave
	 * the stored password alone rather than clearing it.
	 */
	public function test_saving_with_a_blank_password_field_keeps_the_existing_password() {
		$existing = Secret_Crypto::encrypt( 'hunter2' );
		$GLOBALS['fta_test_options']['fta_smtp_settings'] = [ 'smtp_password' => $existing ];

		$_POST['smtp_settings'] = [ 'smtp_password' => '' ];

		$this->callSave();

		$this->assertSame( $existing, $this->storedSettings()['smtp_password'] );
	}

	public function test_submitting_a_new_password_replaces_the_stored_one() {
		$GLOBALS['fta_test_options']['fta_smtp_settings'] = [ 'smtp_password' => Secret_Crypto::encrypt( 'old-pass' ) ];

		$_POST['smtp_settings'] = [ 'smtp_password' => 'new-pass' ];

		$this->callSave();

		$this->assertSame( 'new-pass', Secret_Crypto::decrypt( $this->storedSettings()['smtp_password'] ) );
	}

	/**
	 * Browsers omit an unchecked checkbox from the request entirely, so a
	 * sanitizer that only writes 'smtp_auth' when the key isset() can never
	 * turn it back off - it just silently keeps whatever was last saved, and
	 * configure_smtp()'s own default is `true`.
	 */
	public function test_unchecking_smtp_auth_overrides_a_stored_true() {
		$GLOBALS['fta_test_options']['fta_smtp_settings'] = [ 'smtp_auth' => true ];

		$_POST['smtp_settings'] = [];

		$this->callSave();

		$this->assertFalse( $this->storedSettings()['smtp_auth'] );
	}

	public function test_checking_smtp_auth_is_saved_as_true() {
		$_POST['smtp_settings'] = [ 'smtp_auth' => '1' ];

		$this->callSave();

		$this->assertTrue( $this->storedSettings()['smtp_auth'] );
	}
}
