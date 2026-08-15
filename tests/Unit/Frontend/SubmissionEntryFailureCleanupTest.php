<?php
/**
 * Cleanup when the entry write itself fails.
 *
 * Files are stored before fta_create_entry() runs, so if that insert fails the
 * files on disk are orphaned: no entry references them, so nothing will ever
 * display or delete them. This is the last of the three places in the
 * submission path where that could happen - the other two (a signature failing
 * after uploads, and a rejected payment recompute) are covered by
 * SubmissionFileCleanupTest and the payment tests.
 *
 * fta_get_form() and fta_create_entry() normally hit the database, which is
 * unavailable in this suite. The namespace-scoped overrides below are resolved
 * in preference to the global functions because Submission.php calls them
 * unqualified from inside Formtura\Frontend.
 *
 * @package Formtura
 */

namespace Formtura\Frontend {
	if ( ! function_exists( __NAMESPACE__ . '\\fta_get_form' ) ) {
		/**
		 * Test double for fta_get_form(), scoped to this namespace only.
		 * Tests seed $GLOBALS['fta_test_ajax_forms'][ $form_id ].
		 *
		 * @param int $form_id Form ID.
		 * @return array|null
		 */
		function fta_get_form( $form_id ) {
			return isset( $GLOBALS['fta_test_ajax_forms'][ $form_id ] )
				? $GLOBALS['fta_test_ajax_forms'][ $form_id ]
				: null;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\fta_create_entry' ) ) {
		/**
		 * Test double for fta_create_entry(). Returns whatever a test seeded in
		 * $GLOBALS['fta_test_created_entry_id'] - 0 or false standing in for a
		 * failed insert, as the real function returns on error.
		 *
		 * @param array $data Entry data.
		 * @return int|false
		 */
		function fta_create_entry( $data ) {
			$GLOBALS['fta_test_created_entry_data'] = $data;

			return isset( $GLOBALS['fta_test_created_entry_id'] )
				? $GLOBALS['fta_test_created_entry_id']
				: 1;
		}
	}

	if ( ! function_exists( __NAMESPACE__ . '\\do_action' ) ) {
		/**
		 * Test double: the real submission path fires
		 * fta_after_form_submission here, which would run notification
		 * handlers. Nothing in this test cares what they do.
		 *
		 * @param string $hook Hook name.
		 * @param mixed  ...$args Hook arguments.
		 */
		function do_action( $hook, ...$args ) {
			$GLOBALS['fta_test_actions'][] = $hook;
		}
	}
}

namespace Formtura\Tests\Unit\Frontend {

	use Formtura\Frontend\File_Storage;
	use Formtura\Frontend\Submission;
	use Formtura\Tests\TestCase;

	class SubmissionEntryFailureCleanupTest extends TestCase {

		const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

		/**
		 * @var Submission
		 */
		private $submission;

		/**
		 * @var File_Storage
		 */
		private $storage;

		/**
		 * @var string
		 */
		private $vaultRoot;

		protected function setUp(): void {
			parent::setUp();

			$this->vaultRoot                         = sys_get_temp_dir() . '/formtura-vault-' . uniqid( '', true );
			$this->storage                           = new File_Storage( $this->vaultRoot );
			$this->submission                        = new Submission( $this->storage );
			$_POST                                   = [];
			$_SERVER['REMOTE_ADDR']                  = '203.0.113.9';
			$GLOBALS['fta_test_ajax_forms']          = [];
			$GLOBALS['fta_test_ajax_referer_valid']  = true;
			$GLOBALS['fta_test_actions']             = [];
			unset( $GLOBALS['fta_test_created_entry_id'], $GLOBALS['fta_test_created_entry_data'] );
		}

		protected function tearDown(): void {
			$_POST = [];
			unset(
				$_SERVER['REMOTE_ADDR'],
				$GLOBALS['fta_test_ajax_forms'],
				$GLOBALS['fta_test_ajax_referer_valid'],
				$GLOBALS['fta_test_actions'],
				$GLOBALS['fta_test_created_entry_id'],
				$GLOBALS['fta_test_created_entry_data']
			);

			$this->removeUploadTree();
			$this->removeTree( $this->vaultRoot );

			parent::tearDown();
		}

		/**
		 * Directory the stubbed wp_upload_dir() puts stored files in.
		 *
		 * @return string
		 */
		private function uploadDir() {
			return sys_get_temp_dir() . '/formtura-tests-uploads/formtura';
		}

		/**
		 * Every stored file currently on disk.
		 *
		 * @return string[]
		 */
		private function storedFiles() {
			if ( ! is_dir( $this->vaultRoot ) ) {
				return [];
			}

			$found = [];
			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $this->vaultRoot, \FilesystemIterator::SKIP_DOTS )
			);

			foreach ( $items as $item ) {
				if ( $item->isFile() && 'png' === $item->getExtension() ) {
					$found[] = $item->getPathname();
				}
			}

			return $found;
		}

		/**
		 * Recursively delete a directory tree.
		 */
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
		 * Delete everything the test wrote under the stubbed upload base.
		 */
		private function removeUploadTree() {
			$base = sys_get_temp_dir() . '/formtura-tests-uploads';

			if ( ! is_dir( $base ) ) {
				return;
			}

			$items = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator( $base, \RecursiveDirectoryIterator::SKIP_DOTS ),
				\RecursiveIteratorIterator::CHILD_FIRST
			);

			foreach ( $items as $item ) {
				$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
			}

			rmdir( $base );
		}

		/**
		 * A form with one signature field, which stores a real PNG on disk
		 * during the submission - the cheapest way to get a stored file into
		 * the path without faking a PHP file upload.
		 *
		 * @return array
		 */
		private function signatureForm() {
			return [
				'id'     => 7,
				'status' => 'active',
				'fields' => [
					[ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here' ],
					[ 'id' => 'field_name', 'type' => 'text', 'label' => 'Name' ],
				],
			];
		}

		/**
		 * Submit the signature form and capture the AJAX response the
		 * wp_send_json_* stubs throw instead of exiting the process with.
		 *
		 * @return \FTA_Test_Ajax_Response
		 */
		private function submit() {
			$GLOBALS['fta_test_ajax_forms'][7] = $this->signatureForm();

			$_POST = [
				'form_id'    => 7,
				'field_sig'  => 'data:image/png;base64,' . self::PNG_BASE64,
				'field_name' => 'Ada',
			];

			try {
				$this->submission->ajax_submit_form();
			} catch ( \FTA_Test_Ajax_Response $response ) {
				return $response;
			}

			$this->fail( 'ajax_submit_form() returned without sending a JSON response.' );
		}

		/**
		 * The counterpart, asserted first so the failure case below cannot pass
		 * by simply never storing a file: a successful submission does leave
		 * the stored signature on disk for the entry to reference.
		 */
		public function test_a_successful_submission_keeps_the_stored_file() {
			$GLOBALS['fta_test_created_entry_id'] = 42;

			$response = $this->submit();

			$this->assertTrue( $response->success );
			$this->assertCount( 1, $this->storedFiles() );
		}

		/**
		 * The defect: files are moved to disk before fta_create_entry() runs,
		 * and a failed insert used to return an error without removing them,
		 * orphaning every stored file for that submission permanently.
		 */
		public function test_a_failed_entry_write_deletes_the_stored_files() {
			$GLOBALS['fta_test_created_entry_id'] = 0;

			$response = $this->submit();

			$this->assertFalse( $response->success );
			$this->assertSame(
				[],
				$this->storedFiles(),
				'A failed entry write must not leave stored files behind - nothing references them, so nothing will ever clean them up.'
			);
		}

		/**
		 * A failed entry write must not fire the post-submission hook either,
		 * so notifications cannot go out for an entry that does not exist.
		 */
		public function test_a_failed_entry_write_does_not_fire_the_submission_hook() {
			$GLOBALS['fta_test_created_entry_id'] = 0;

			$this->submit();

			$this->assertSame( [], $GLOBALS['fta_test_actions'] );
		}
	}
}
