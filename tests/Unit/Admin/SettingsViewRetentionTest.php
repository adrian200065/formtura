<?php
/**
 * Settings screen rendering for the entry-retention field.
 *
 * Modeled on SmtpSettingsViewTest.php: the failure mode that matters is the
 * input posting under a different key than Settings::sanitize_settings()
 * reads (see RetentionSettingsTest), which would make the field silently do
 * nothing.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Tests\TestCase;

class SettingsViewRetentionTest extends TestCase {

	/**
	 * @param array $settings As Settings::render() would pass them in.
	 * @return string
	 */
	private function render( array $settings = [] ) {
		ob_start();
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/settings.php';

		return ob_get_clean();
	}

	public function test_the_field_posts_the_key_the_sanitizer_reads() {
		$html = $this->render();

		$this->assertStringContainsString( 'name="settings[entry_retention_days]"', $html );
	}

	public function test_a_saved_value_is_reflected_in_the_field() {
		$html = $this->render( [ 'entry_retention_days' => 90 ] );

		$field = substr( $html, strpos( $html, 'id="fta-entry-retention-days"' ) );
		$field = substr( $field, 0, strpos( $field, '>' ) );

		$this->assertStringContainsString( 'value="90"', $field );
	}

	public function test_a_missing_value_defaults_the_field_to_zero() {
		$html  = $this->render();
		$field = substr( $html, strpos( $html, 'id="fta-entry-retention-days"' ) );
		$field = substr( $field, 0, strpos( $field, '>' ) );

		$this->assertStringContainsString( 'value="0"', $field );
	}

	public function test_the_field_explains_that_zero_disables_automatic_deletion() {
		$this->assertStringContainsString( '0', $this->render() );
		$this->assertStringContainsString( 'disable', strtolower( $this->render() ) );
	}
}
