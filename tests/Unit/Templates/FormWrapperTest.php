<?php
/**
 * Form wrapper rendering tests.
 *
 * The v2 reCAPTCHA widget has to have a container inside the form: that is
 * where Google renders the checkbox, and where the token textarea ends up so
 * the submission carries it.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Templates;

use Formtura\Tests\TestCase;

class FormWrapperTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['fta_test_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );

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
	 * Render the form wrapper and return its markup.
	 *
	 * @param array $form_settings Form-level settings, in the shape the
	 *        builder saves them (see FormWrapperSettingsTest).
	 * @return string
	 */
	private function render( array $form_settings = [] ) {
		$form = [
			'id'       => 7,
			'title'    => 'Contact',
			'fields'   => [
				[
					'id'    => 'field_1',
					'type'  => 'text',
					'label' => 'Your name',
				],
			],
			'settings' => $form_settings,
		];
		$args = [];

		ob_start();
		include FORMTURA_PLUGIN_DIR . 'templates/form-wrapper.php';

		return (string) ob_get_clean();
	}

	public function test_no_widget_container_when_recaptcha_is_not_configured() {
		$this->assertStringNotContainsString( 'data-fta-recaptcha', $this->render() );
	}

	public function test_no_widget_container_when_only_one_key_is_set() {
		$this->settings( [ 'recaptcha_site_key' => 'site' ] );

		$this->assertStringNotContainsString( 'data-fta-recaptcha', $this->render() );
	}

	public function test_widget_container_is_rendered_inside_the_form_for_v2() {
		$this->settings( [
			'recaptcha_site_key'   => 'site-key-123',
			'recaptcha_secret_key' => 'secret',
			'recaptcha_version'    => 'v2',
		] );

		$html = $this->render();

		$this->assertStringContainsString( 'data-fta-recaptcha', $html );
		$this->assertStringContainsString( 'data-sitekey="site-key-123"', $html );

		// Inside the form, or the token would not be submitted with it.
		$this->assertMatchesRegularExpression( '/<form[^>]*>.*data-fta-recaptcha.*<\/form>/s', $html );
	}

	/**
	 * v3 needs no visible widget - the token is minted on submit.
	 */
	public function test_no_widget_container_for_v3() {
		$this->settings( [
			'recaptcha_site_key'   => 'site-key-123',
			'recaptcha_secret_key' => 'secret',
			'recaptcha_version'    => 'v3',
		] );

		$this->assertStringNotContainsString( 'data-fta-recaptcha', $this->render() );
	}

	/**
	 * The secret key must never reach the page.
	 */
	public function test_secret_key_is_not_printed() {
		$this->settings( [
			'recaptcha_site_key'   => 'site-key-123',
			'recaptcha_secret_key' => 'super-secret-value',
		] );

		$this->assertStringNotContainsString( 'super-secret-value', $this->render() );
	}

	/**
	 * The builder saves this setting as `submitButtonText` (camelCase) - see
	 * Form_Builder::sanitize_settings_data(). Reading `submit_button_text`
	 * here meant a custom label set in the builder never reached the page;
	 * the button silently fell back to "Submit" every time.
	 */
	public function test_submit_button_uses_the_builder_saved_setting() {
		$html = $this->render( [ 'submitButtonText' => 'Send Message' ] );

		$this->assertStringContainsString( 'Send Message', $html );
	}

	public function test_submit_button_falls_back_to_submit_when_unset() {
		$html = $this->render();

		$button = substr( $html, strpos( $html, 'fta-submit-button' ) );
		$button = substr( $button, 0, strpos( $button, '</button>' ) );

		$this->assertStringContainsString( 'Submit', $button );
	}

	/**
	 * The honeypot field (see Submission::honeypot_tripped()) has to post
	 * under the exact key the server checks, or the whole mechanism does
	 * nothing.
	 */
	public function test_the_honeypot_field_posts_the_key_the_server_reads() {
		$html = $this->render();

		$this->assertStringContainsString(
			'name="' . \Formtura\Frontend\Submission::HONEYPOT_FIELD . '"',
			$html
		);
	}

	/**
	 * Inside the form, or it would never be submitted alongside real fields.
	 */
	public function test_the_honeypot_field_is_inside_the_form() {
		$html = $this->render();

		$this->assertMatchesRegularExpression(
			'/<form[^>]*>.*name="' . preg_quote( \Formtura\Frontend\Submission::HONEYPOT_FIELD, '/' ) . '".*<\/form>/s',
			$html
		);
	}

	/**
	 * Hidden with an inline style rather than the plugin's own CSS class, so
	 * it stays hidden even when "Disable Default CSS" turns that stylesheet
	 * off - a visible honeypot would trap real visitors, not bots.
	 */
	public function test_the_honeypot_field_is_hidden_inline_not_via_a_stylesheet_class() {
		$html   = $this->render();
		$offset = strpos( $html, '<div style=' );
		$field  = substr( $html, $offset, strpos( $html, '</div>', $offset ) - $offset );

		$this->assertStringContainsString( 'position:absolute', $field );
		$this->assertStringContainsString( \Formtura\Frontend\Submission::HONEYPOT_FIELD, $field );
	}
}
