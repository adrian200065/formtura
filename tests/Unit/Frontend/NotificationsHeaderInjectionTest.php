<?php
/**
 * Email header injection via notification smart tags.
 *
 * Notification recipients/reply-to/cc/bcc are built by substituting smart
 * tags into admin-configured templates (see parse_smart_tags()) and handing
 * the result straight to wp_mail() as raw header strings. A smart tag can
 * resolve to a visitor-submitted field value, so a submission containing a
 * carriage return/line feed in a field referenced by {field_x} in a
 * notification's To/Reply-To/Cc/Bcc could inject an arbitrary extra header
 * (a second Bcc:, a Subject: override, etc.) into the outgoing email. This
 * covers the fix: header-bound values are stripped of CR/LF after smart
 * tags are resolved, before they ever reach wp_mail().
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Notifications;
use Formtura\Tests\TestCase;

class NotificationsHeaderInjectionTest extends TestCase {

	/**
	 * @var Notifications
	 */
	private $notifications;

	protected function setUp(): void {
		parent::setUp();

		$this->notifications = new Notifications();
		unset( $GLOBALS['fta_test_last_mail'] );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_last_mail'] );

		parent::tearDown();
	}

	/**
	 * Call the private send_notification( $notification, $form, $entry_data, $entry_id ).
	 *
	 * @param array $notification Notification settings.
	 * @param array $entry_data   Entry data.
	 * @return array The wp_mail() call the stub recorded.
	 */
	private function send( array $notification, array $entry_data ) {
		$reflection = new \ReflectionMethod( Notifications::class, 'send_notification' );
		$reflection->setAccessible( true );

		$reflection->invoke( $this->notifications, $notification, array( 'fields' => array() ), $entry_data, 1 );

		$this->assertNotNull( $GLOBALS['fta_test_last_mail'], 'wp_mail() was never called.' );

		return $GLOBALS['fta_test_last_mail'];
	}

	public function test_a_crlf_in_a_smart_tagged_recipient_cannot_add_a_second_address() {
		$mail = $this->send(
			array( 'to' => '{email}', 'subject' => 'S', 'message' => 'M' ),
			array( 'email' => "victim@example.test\r\nBcc: attacker@evil.test" )
		);

		foreach ( $mail['to'] as $recipient ) {
			$this->assertStringNotContainsString( "\r", $recipient );
			$this->assertStringNotContainsString( "\n", $recipient );
		}
	}

	public function test_a_crlf_in_a_smart_tagged_reply_to_cannot_inject_a_header() {
		$mail = $this->send(
			array(
				'to'       => 'owner@example.test',
				'subject'  => 'S',
				'message'  => 'M',
				'reply_to' => "{email}",
			),
			array( 'email' => "victim@example.test\r\nBcc: attacker@evil.test" )
		);

		// The attacker's text survives as inert garbage tacked onto the one
		// legitimate Reply-To value - the property that matters is that it
		// never becomes a header line of its own.
		$this->assertNotContains( 'Bcc: attacker@evil.test', (array) $mail['headers'] );
	}

	public function test_a_crlf_in_a_smart_tagged_cc_cannot_inject_a_header() {
		$mail = $this->send(
			array(
				'to'      => 'owner@example.test',
				'subject' => 'S',
				'message' => 'M',
				'cc'      => '{email}',
			),
			array( 'email' => "victim@example.test\r\nBcc: attacker@evil.test" )
		);

		$this->assertNotContains( 'Bcc: attacker@evil.test', (array) $mail['headers'] );
	}

	public function test_a_crlf_in_a_smart_tagged_bcc_cannot_inject_a_header() {
		$mail = $this->send(
			array(
				'to'      => 'owner@example.test',
				'subject' => 'S',
				'message' => 'M',
				'bcc'     => '{email}',
			),
			array( 'email' => "victim@example.test\r\nX-Injected: yes" )
		);

		$this->assertNotContains( 'X-Injected: yes', (array) $mail['headers'] );
	}

	public function test_a_crlf_in_a_smart_tagged_subject_is_stripped() {
		$mail = $this->send(
			array(
				'to'      => 'owner@example.test',
				'subject' => 'Re: {email}',
				'message' => 'M',
			),
			array( 'email' => "test\r\nBcc: attacker@evil.test" )
		);

		$this->assertStringNotContainsString( "\r", $mail['subject'] );
		$this->assertStringNotContainsString( "\n", $mail['subject'] );
	}

	/**
	 * The fix must not disturb ordinary, well-formed values.
	 */
	public function test_ordinary_values_are_unaffected() {
		$mail = $this->send(
			array(
				'to'       => 'owner@example.test, other@example.test',
				'subject'  => 'New submission',
				'message'  => 'M',
				'reply_to' => 'reply@example.test',
				'cc'       => 'cc@example.test',
				'bcc'      => 'bcc@example.test',
			),
			array()
		);

		$this->assertSame( array( 'owner@example.test', 'other@example.test' ), $mail['to'] );
		$this->assertSame( 'New submission', $mail['subject'] );

		$headers = implode( "\n", (array) $mail['headers'] );
		$this->assertStringContainsString( 'Reply-To: reply@example.test', $headers );
		$this->assertStringContainsString( 'Cc: cc@example.test', $headers );
		$this->assertStringContainsString( 'Bcc: bcc@example.test', $headers );
	}
}
