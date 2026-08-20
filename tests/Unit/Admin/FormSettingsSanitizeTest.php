<?php
/**
 * Round-trip tests for Form_Builder::sanitize_settings_data()'s handling of
 * notification settings.
 *
 * Before this, sanitize_settings_data() passed $settings['notifications']
 * straight through unsanitized - every notification field (to, subject,
 * message, reply_to, cc, bcc) was stored exactly as posted, with no type
 * coercion and no stripping of markup from fields that end up as raw text.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Form_Builder;
use Formtura\Tests\TestCase;

class FormSettingsSanitizeTest extends TestCase {

	/**
	 * @var Form_Builder
	 */
	private $builder;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = new Form_Builder();
	}

	/**
	 * Call the private sanitize_settings_data( $settings ).
	 *
	 * @param array $settings Raw settings data, as posted by the builder.
	 * @return array Sanitized settings data.
	 */
	private function sanitize( array $settings ) {
		$reflection = new \ReflectionMethod( Form_Builder::class, 'sanitize_settings_data' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->builder, $settings );
	}

	public function test_a_notification_round_trips_with_correct_types() {
		$result = $this->sanitize(
			[
				'notifications' => [
					[
						'enabled'  => true,
						'to'       => '{admin_email}, extra@example.test',
						'subject'  => 'New submission from {site_name}',
						'message'  => 'You got mail: {field_1}',
						'reply_to' => '{field_email}',
						'cc'       => 'cc@example.test',
						'bcc'      => 'bcc@example.test',
					],
				],
			]
		);

		$this->assertSame(
			[
				'enabled'  => true,
				'to'       => '{admin_email}, extra@example.test',
				'subject'  => 'New submission from {site_name}',
				'message'  => 'You got mail: {field_1}',
				'reply_to' => '{field_email}',
				'cc'       => 'cc@example.test',
				'bcc'      => 'bcc@example.test',
			],
			$result['notifications'][0]
		);
	}

	public function test_enabled_is_coerced_to_a_strict_bool() {
		$result = $this->sanitize( [ 'notifications' => [ [ 'enabled' => '1' ] ] ] );
		$this->assertTrue( $result['notifications'][0]['enabled'] );

		$result = $this->sanitize( [ 'notifications' => [ [ 'enabled' => '' ] ] ] );
		$this->assertFalse( $result['notifications'][0]['enabled'] );

		$result = $this->sanitize( [ 'notifications' => [ [] ] ] );
		$this->assertFalse( $result['notifications'][0]['enabled'] );
	}

	public function test_missing_string_fields_default_to_empty_strings() {
		$result = $this->sanitize( [ 'notifications' => [ [ 'enabled' => true ] ] ] );

		foreach ( [ 'to', 'subject', 'message', 'reply_to', 'cc', 'bcc' ] as $key ) {
			$this->assertSame( '', $result['notifications'][0][ $key ], "Missing '{$key}' should sanitize to ''." );
		}
	}

	public function test_markup_is_stripped_from_the_header_bound_fields() {
		$result = $this->sanitize(
			[
				'notifications' => [
					[
						'to'       => '<script>alert(1)</script>owner@example.test',
						'subject'  => '<b>Subject</b>',
						'reply_to' => '<i>reply@example.test</i>',
						'cc'       => '<script>x</script>cc@example.test',
						'bcc'      => '<script>x</script>bcc@example.test',
					],
				],
			]
		);

		$notification = $result['notifications'][0];

		foreach ( [ 'to', 'subject', 'reply_to', 'cc', 'bcc' ] as $key ) {
			$this->assertStringNotContainsString( '<', $notification[ $key ], "'{$key}' should have tags stripped." );
		}
	}

	/**
	 * The message is re-escaped through wp_kses_post() at render time (see
	 * Notifications::send_notification()), so it is stored as posted rather
	 * than double-sanitized here.
	 */
	public function test_message_is_not_double_escaped_on_save() {
		$result = $this->sanitize( [ 'notifications' => [ [ 'message' => "Line one\nLine two" ] ] ] );

		$this->assertSame( "Line one\nLine two", $result['notifications'][0]['message'] );
	}

	public function test_a_non_array_notification_entry_is_dropped_without_a_key_gap() {
		$result = $this->sanitize(
			[
				'notifications' => [
					'not-an-array',
					[ 'enabled' => true, 'to' => 'owner@example.test' ],
				],
			]
		);

		$this->assertCount( 1, $result['notifications'] );
		$this->assertSame( 'owner@example.test', $result['notifications'][0]['to'] );
	}

	public function test_multiple_notifications_all_survive() {
		$result = $this->sanitize(
			[
				'notifications' => [
					[ 'enabled' => true, 'to' => 'first@example.test' ],
					[ 'enabled' => false, 'to' => 'second@example.test' ],
				],
			]
		);

		$this->assertCount( 2, $result['notifications'] );
		$this->assertSame( 'first@example.test', $result['notifications'][0]['to'] );
		$this->assertSame( 'second@example.test', $result['notifications'][1]['to'] );
	}

	public function test_notifications_key_is_absent_when_not_posted() {
		$result = $this->sanitize( [ 'title' => 'Contact' ] );

		$this->assertArrayNotHasKey( 'notifications', $result );
	}
}
