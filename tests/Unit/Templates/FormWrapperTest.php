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
	 * @return string
	 */
	private function render() {
		$form = [
			'id'     => 7,
			'title'  => 'Contact',
			'fields' => [
				[
					'id'    => 'field_1',
					'type'  => 'text',
					'label' => 'Your name',
				],
			],
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
}
