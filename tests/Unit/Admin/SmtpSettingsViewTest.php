<?php
/**
 * SMTP settings screen rendering.
 *
 * Three defects met here: the enable checkbox posted under a different
 * settings key than the one the backend reads, the test-email button posted
 * an AJAX action nothing on the server registers, and the saved password was
 * rendered back into the password field's value attribute even though the
 * page tells the administrator it is stored encrypted.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Tests\TestCase;

class SmtpSettingsViewTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['fta_test_options'] = [
			'admin_email' => 'admin@example.com',
			'blogname'    => 'Example Site',
		];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );

		parent::tearDown();
	}

	/**
	 * @param array $smtp_settings Settings as fta_get_smtp_setting() would
	 *        return them (i.e. already what the render() method passes in).
	 * @return string
	 */
	private function render( array $smtp_settings = [] ) {
		ob_start();
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/smtp-settings.php';

		return ob_get_clean();
	}

	/**
	 * The backend (SMTP::configure_phpmailer(), sanitize_smtp_settings())
	 * reads and writes 'enabled' - see SMTPTest. The view has to post under
	 * the same key or the checkbox does nothing.
	 */
	public function test_the_enable_checkbox_posts_the_key_the_backend_reads() {
		$html = $this->render();

		$this->assertStringContainsString( 'name="smtp_settings[enabled]"', $html );
		$this->assertStringNotContainsString( 'enable_smtp', $html );
	}

	public function test_the_enable_checkbox_reflects_a_saved_enabled_setting() {
		$html = $this->render( [ 'enabled' => true ] );

		$checkbox = substr( $html, strpos( $html, 'id="fta-smtp-enable"' ) );
		$checkbox = substr( $checkbox, 0, strpos( $checkbox, '>' ) );

		$this->assertStringContainsString( 'checked', $checkbox );
	}

	/**
	 * The registered handler is wp_ajax_fta_send_test_email (see SMTP.php);
	 * the button must post that action, not one nothing listens for.
	 */
	public function test_the_test_button_posts_the_registered_ajax_action() {
		$html = $this->render();

		$this->assertStringContainsString( "action: 'fta_send_test_email'", $html );
		$this->assertStringNotContainsString( "'fta_test_smtp'", $html );
	}

	/**
	 * ajax_send_test_email() requires $_POST['email']; the button has to
	 * send one.
	 */
	public function test_the_test_button_sends_an_email_address() {
		$html = $this->render();

		$this->assertStringContainsString( 'email:', $html );
	}

	/**
	 * The saved password must never come back out of the server - not even
	 * to prefill the field the administrator is looking at.
	 */
	public function test_a_saved_password_is_not_rendered_into_the_field() {
		$html = $this->render( [ 'smtp_password' => 'ZW5jcnlwdGVkLWxvb2tpbmctY2lwaGVydGV4dA==' ] );

		$this->assertStringNotContainsString( 'ZW5jcnlwdGVkLWxvb2tpbmctY2lwaGVydGV4dA==', $html );

		$field = substr( $html, strpos( $html, 'id="fta-smtp-password"' ) );
		$field = substr( $field, 0, strpos( $field, '>' ) );

		$this->assertStringContainsString( 'value=""', $field );
	}

	public function test_the_description_tells_the_administrator_a_password_is_already_saved() {
		$html = $this->render( [ 'smtp_password' => 'ZW5jcnlwdGVkLWxvb2tpbmctY2lwaGVydGV4dA==' ] );

		$this->assertStringContainsString( 'already saved', $html );
	}
}
