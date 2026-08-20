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

	public function test_the_rate_limit_field_posts_the_key_the_sanitizer_reads() {
		$html = $this->render();

		$this->assertStringContainsString( 'name="settings[submission_rate_limit]"', $html );
	}

	public function test_a_saved_rate_limit_is_reflected_in_the_field() {
		$html  = $this->render( [ 'submission_rate_limit' => 25 ] );
		$field = substr( $html, strpos( $html, 'id="fta-submission-rate-limit"' ) );
		$field = substr( $field, 0, strpos( $field, '>' ) );

		$this->assertStringContainsString( 'value="25"', $field );
	}

	public function test_the_trusted_proxies_field_posts_the_key_the_sanitizer_reads() {
		$html = $this->render();

		$this->assertStringContainsString( 'name="settings[trusted_proxies]"', $html );
	}

	public function test_a_saved_trusted_proxies_list_is_reflected_in_the_field() {
		$html = $this->render( [ 'trusted_proxies' => "203.0.113.7\n198.51.100.0/24" ] );

		$this->assertStringContainsString( "203.0.113.7\n198.51.100.0/24", $html );
	}
}
