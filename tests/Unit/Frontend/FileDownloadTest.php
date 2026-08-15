<?php
/**
 * Authorization and ownership tests for the file download controller.
 *
 * This controller is the only browser-reachable route to a stored file, so it
 * carries the whole access-control boundary that the webserver used to carry
 * (badly). The tests below pin the three ways that boundary could be lost:
 *
 * 1. Registering an anonymous handler, or failing to require manage_options.
 * 2. Trusting a path supplied by the request instead of looking one up.
 * 3. Serving a file that belongs to some entry, but not the requested one.
 *
 * fta_get_entry() normally hits the database. The namespace-scoped override
 * below is resolved in preference to the global function because
 * File_Download.php calls it unqualified from inside Formtura\Frontend.
 *
 * @package Formtura
 */

namespace Formtura\Frontend {
	if ( ! function_exists( __NAMESPACE__ . '\\fta_get_entry' ) ) {
		/**
		 * Test double for fta_get_entry(). Tests seed
		 * $GLOBALS['fta_test_entries'][ $entry_id ].
		 *
		 * @param int $entry_id Entry ID.
		 * @return array|null
		 */
		function fta_get_entry( $entry_id ) {
			return isset( $GLOBALS['fta_test_entries'][ $entry_id ] )
				? $GLOBALS['fta_test_entries'][ $entry_id ]
				: null;
		}
	}
}

namespace Formtura\Tests\Unit\Frontend {

	use Formtura\Frontend\File_Download;
	use Formtura\Frontend\File_Storage;
	use Formtura\Tests\TestCase;

	class FileDownloadTest extends TestCase {

		/**
		 * @var File_Storage
		 */
		private $storage;

		/**
		 * @var File_Download
		 */
		private $download;

		/**
		 * @var string
		 */
		private $vaultRoot;

		/**
		 * @var array
		 */
		private $record;

		protected function setUp(): void {
			parent::setUp();

			$this->vaultRoot = sys_get_temp_dir() . '/formtura-vault-' . uniqid( '', true );
			$this->storage   = new File_Storage( $this->vaultRoot );
			$this->download  = new File_Download( $this->storage );

			$dir = $this->storage->prepare_directory();
			$abs = $dir . '/stored.txt';
			file_put_contents( $abs, 'payload' );

			$this->record = $this->storage->create_record( 'resume.txt', $abs, 'text/plain', 7 );

			$GLOBALS['fta_test_entries']     = [];
			$GLOBALS['fta_test_current_user_can'] = true;
			$_GET                            = [];
			$_REQUEST                        = [];
		}

		protected function tearDown(): void {
			unset( $GLOBALS['fta_test_entries'], $GLOBALS['fta_test_current_user_can'] );
			$_GET     = [];
			$_REQUEST = [];

			$this->removeTree( $this->vaultRoot );

			parent::tearDown();
		}

		private function removeTree( $dir ) {
			if ( ! is_dir( $dir ) ) {
				return;
			}

			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $items as $item ) {
				$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
			}

			rmdir( $dir );
		}

		/**
		 * Seed an entry owning this test's stored file.
		 */
		private function seedEntry( $entry_id = 8, $field = 'resume' ) {
			$GLOBALS['fta_test_entries'][ $entry_id ] = [
				'id'   => $entry_id,
				'data' => [ $field => [ $this->record ] ],
			];
		}

		/**
		 * Run the controller and capture what it wrote to the output buffer.
		 *
		 * @return string
		 */
		private function captureDownload() {
			ob_start();

			try {
				$this->download->handle();
			} finally {
				$body = ob_get_clean();
			}

			return $body;
		}

		public function test_anonymous_user_cannot_download() {
			$GLOBALS['fta_test_current_user_can'] = false;
			$this->seedEntry();
			$_GET = [ 'entry_id' => 8, 'field' => 'resume', 'file' => 0 ];

			$this->expectException( \FTA_Test_Wp_Die::class );

			$this->download->handle();
		}

		public function test_user_without_manage_options_cannot_download() {
			$GLOBALS['fta_test_current_user_can'] = false;
			$this->seedEntry();
			$_GET = [ 'entry_id' => 8, 'field' => 'resume', 'file' => 0 ];

			try {
				$this->download->handle();
				$this->fail( 'The controller must refuse a user without manage_options.' );
			} catch ( \FTA_Test_Wp_Die $died ) {
				$this->assertSame( 403, $died->response_code );
			}
		}

		public function test_admin_can_download_record_owned_by_requested_entry() {
			$this->seedEntry();
			$_GET = [ 'entry_id' => 8, 'field' => 'resume', 'file' => 0 ];

			$this->assertSame( 'payload', $this->captureDownload() );
		}

		/**
		 * The ownership check: entry 9 does not reference this file, so naming
		 * entry 9 must not serve entry 8's file.
		 */
		public function test_cannot_download_a_different_entrys_file() {
			$this->seedEntry( 8 );
			$GLOBALS['fta_test_entries'][9] = [ 'id' => 9, 'data' => [ 'resume' => [] ] ];

			$_GET = [ 'entry_id' => 9, 'field' => 'resume', 'file' => 0 ];

			$this->expectException( \FTA_Test_Wp_Die::class );

			$this->download->handle();
		}

		public function test_unknown_entry_is_refused() {
			$_GET = [ 'entry_id' => 404, 'field' => 'resume', 'file' => 0 ];

			$this->expectException( \FTA_Test_Wp_Die::class );

			$this->download->handle();
		}

		public function test_unknown_field_is_refused() {
			$this->seedEntry();
			$_GET = [ 'entry_id' => 8, 'field' => 'nope', 'file' => 0 ];

			$this->expectException( \FTA_Test_Wp_Die::class );

			$this->download->handle();
		}

		public function test_out_of_range_index_is_refused() {
			$this->seedEntry();
			$_GET = [ 'entry_id' => 8, 'field' => 'resume', 'file' => 7 ];

			$this->expectException( \FTA_Test_Wp_Die::class );

			$this->download->handle();
		}

		/**
		 * A supplied path must be inert: the controller looks the file up from
		 * the entry and never reads a filesystem location from the request.
		 */
		public function test_supplied_path_parameter_is_ignored() {
			$this->seedEntry();
			$_GET = [
				'entry_id' => 8,
				'field'    => 'resume',
				'file'     => 0,
				'path'     => '/etc/passwd',
			];

			$this->assertSame( 'payload', $this->captureDownload() );
		}

		/**
		 * A record whose stored path escapes the vault must not be served, even
		 * when a real entry references it.
		 */
		public function test_record_escaping_the_vault_is_refused() {
			$outside = sys_get_temp_dir() . '/formtura-outside-' . uniqid( '', true ) . '.txt';
			file_put_contents( $outside, 'secret' );

			$GLOBALS['fta_test_entries'][8] = [
				'id'   => 8,
				'data' => [ 'resume' => [ [ 'name' => 'x.txt', 'path' => '../../../../../..' . $outside ] ] ],
			];

			$_GET = [ 'entry_id' => 8, 'field' => 'resume', 'file' => 0 ];

			try {
				$this->download->handle();
				$this->fail( 'A record resolving outside the vault must not be served.' );
			} catch ( \FTA_Test_Wp_Die $died ) {
				$this->assertSame( 404, $died->response_code );
			} finally {
				unlink( $outside );
			}
		}

		/**
		 * A stored file served inline would be stored XSS for any type a
		 * browser renders, so the response must force a download and forbid
		 * content sniffing.
		 */
		public function test_download_headers_force_an_attachment_and_forbid_sniffing() {
			$GLOBALS['fta_test_sent_headers'] = [];

			$this->seedEntry();
			$_GET = [ 'entry_id' => 8, 'field' => 'resume', 'file' => 0 ];

			$this->captureDownload();

			$headers = $GLOBALS['fta_test_sent_headers'];

			$this->assertContains( 'X-Content-Type-Options: nosniff', $headers );
			$this->assertContains( 'Content-Disposition: attachment; filename="resume.txt"', $headers );
			$this->assertContains( 'Content-Type: text/plain', $headers );
			$this->assertContains( 'Content-Length: 7', $headers );
			$this->assertTrue( $GLOBALS['fta_test_nocache_headers'] );
		}

		public function test_url_targets_the_authenticated_controller() {
			$url = File_Download::url( 8, 'resume', 0 );

			$this->assertStringContainsString( 'admin-post.php', $url );
			$this->assertStringContainsString( 'action=fta_download_file', $url );
			$this->assertStringContainsString( 'entry_id=8', $url );
			$this->assertStringContainsString( 'field=resume', $url );
			$this->assertStringContainsString( 'file=0', $url );
		}

		/**
		 * No anonymous route may exist: admin_post_nopriv_* fires for logged-out
		 * visitors, which would hand every stored file to the public.
		 */
		public function test_no_anonymous_action_is_registered() {
			$GLOBALS['fta_test_actions'] = [];

			( new File_Download( $this->storage ) )->register();

			$hooks = array_column( $GLOBALS['fta_test_actions'], 0 );

			$this->assertContains( 'admin_post_fta_download_file', $hooks );
			$this->assertNotContains( 'admin_post_nopriv_fta_download_file', $hooks );
		}
	}
}
