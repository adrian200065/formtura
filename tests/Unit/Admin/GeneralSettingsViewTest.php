<?php
/**
 * Settings screen rendering for the reCAPTCHA secret key field.
 *
 * Modeled on SmtpSettingsViewTest.php: the saved secret must never come back
 * out of the server, not even to prefill the field the administrator is
 * looking at.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Tests\TestCase;

class GeneralSettingsViewTest extends TestCase {

	/**
	 * @param array $settings As Settings::render() would pass them in.
	 * @return string
	 */
	private function render( array $settings = [] ) {
		ob_start();
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/settings.php';

		return ob_get_clean();
	}

	public function test_a_saved_recaptcha_secret_is_not_rendered_into_the_field() {
		$html = $this->render( [ 'recaptcha_secret_key' => 'ZW5jcnlwdGVkLWxvb2tpbmctY2lwaGVydGV4dA==' ] );

		$this->assertStringNotContainsString( 'ZW5jcnlwdGVkLWxvb2tpbmctY2lwaGVydGV4dA==', $html );

		$field = substr( $html, strpos( $html, 'id="fta-recaptcha-secret-key"' ) );
		$field = substr( $field, 0, strpos( $field, '>' ) );

		$this->assertStringContainsString( 'value=""', $field );
	}
}
