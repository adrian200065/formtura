<?php
/**
 * Notification file-link tests.
 *
 * Notification emails used to render a file record as a link to its public
 * upload URL. That address needed no authentication, so anyone the mail was
 * forwarded to - or anyone reading it in transit - could fetch the file.
 * Links must instead point at the authenticated download controller.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Notifications;
use Formtura\Tests\TestCase;

class NotificationsFileLinkTest extends TestCase {

	/**
	 * @var Notifications
	 */
	private $notifications;

	protected function setUp(): void {
		parent::setUp();

		$this->notifications = new Notifications();
	}

	/**
	 * Call the private parse_smart_tags( $text, $entry_data, $entry_id ).
	 *
	 * @param string $text       Text containing smart tags.
	 * @param array  $entry_data Entry data.
	 * @param int    $entry_id   Entry ID.
	 * @return string
	 */
	private function parse( $text, array $entry_data, $entry_id ) {
		$reflection = new \ReflectionMethod( Notifications::class, 'parse_smart_tags' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->notifications, $text, $entry_data, $entry_id );
	}

	/**
	 * A record as stored by File_Storage: relative path, no URL.
	 */
	private function record( $name = 'resume.pdf' ) {
		return [
			'name' => $name,
			'path' => '2026/08/abc123.pdf',
			'type' => 'application/pdf',
			'size' => 1024,
		];
	}

	public function test_file_record_renders_a_link_to_the_authenticated_controller() {
		$output = $this->parse(
			'{resume}',
			[ 'resume' => [ $this->record() ] ],
			8
		);

		$this->assertStringContainsString( 'admin-post.php', $output );
		$this->assertStringContainsString( 'action=fta_download_file', $output );
		$this->assertStringContainsString( 'entry_id=8', $output );
		$this->assertStringContainsString( 'field=resume', $output );
		$this->assertStringContainsString( 'file=0', $output );
		$this->assertStringContainsString( 'resume.pdf', $output );
	}

	/**
	 * The regression itself: no public uploads URL may appear, even when a
	 * legacy record still carries one.
	 */
	public function test_legacy_public_url_is_never_rendered() {
		$legacy = [
			'name' => 'resume.pdf',
			'file' => '/var/www/wp-content/uploads/formtura/2026/08/abc123.pdf',
			'url'  => 'https://example.com/wp-content/uploads/formtura/2026/08/abc123.pdf',
		];

		$output = $this->parse( '{resume}', [ 'resume' => [ $legacy ] ], 8 );

		$this->assertStringNotContainsString( 'wp-content/uploads', $output );
		$this->assertStringContainsString( 'action=fta_download_file', $output );
	}

	/**
	 * Each file in a multi-file field needs its own index, or every link
	 * would fetch the first file.
	 */
	public function test_each_file_in_a_field_gets_its_own_index() {
		$output = $this->parse(
			'{resume}',
			[ 'resume' => [ $this->record( 'first.pdf' ), $this->record( 'second.pdf' ) ] ],
			8
		);

		$this->assertStringContainsString( 'file=0', $output );
		$this->assertStringContainsString( 'file=1', $output );
	}

	/**
	 * An attacker-controlled filename must not become markup in the email.
	 */
	public function test_file_name_is_escaped() {
		$output = $this->parse(
			'{resume}',
			[ 'resume' => [ $this->record( '<script>alert(1)</script>.pdf' ) ] ],
			8
		);

		$this->assertStringNotContainsString( '<script>', $output );
	}

	/**
	 * Ordinary values must keep rendering exactly as before.
	 */
	public function test_non_file_values_are_unaffected() {
		$this->assertSame( 'Ada', $this->parse( '{name}', [ 'name' => 'Ada' ], 8 ) );
		$this->assertSame( 'a, b', $this->parse( '{opts}', [ 'opts' => [ 'a', 'b' ] ], 8 ) );
	}
}
