<?php
/**
 * Private storage administrator notice tests.
 *
 * Both failure modes below are silent from the administrator's side. An
 * unwritable vault rejects every file submission, and an incomplete migration
 * leaves old uploads publicly readable - in neither case does anything appear
 * in the admin unless these notices render.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Admin;
use Formtura\Frontend\File_Storage;
use Formtura\Tests\TestCase;

class PrivateStorageNoticeTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['fta_test_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );

		parent::tearDown();
	}

	/**
	 * Render admin_notices() and return its markup.
	 *
	 * @return string
	 */
	private function render() {
		ob_start();

		try {
			( new Admin() )->admin_notices();
		} finally {
			$output = ob_get_clean();
		}

		return $output;
	}

	public function test_unwritable_private_storage_is_visible_to_administrators() {
		$GLOBALS['fta_test_options'][ File_Storage::STORAGE_ERROR_OPTION ] = '/var/private/formtura/site-1';

		$output = $this->render();

		$this->assertStringContainsString( 'private storage is not writable', $output );
		$this->assertStringContainsString( 'FORMTURA_PRIVATE_UPLOAD_DIR', $output );
		$this->assertStringContainsString( 'notice-error', $output );
	}

	public function test_failed_private_file_migration_is_visible_to_administrators() {
		$GLOBALS['fta_test_options']['fta_private_migration_failed'] = true;

		$output = $this->render();

		$this->assertStringContainsString( 'remain publicly readable', $output );
		$this->assertStringContainsString( 'FORMTURA_PRIVATE_UPLOAD_DIR', $output );
		$this->assertStringContainsString( 'notice-error', $output );
	}

	/**
	 * A healthy install must not nag.
	 */
	public function test_no_storage_notice_when_everything_is_healthy() {
		$output = $this->render();

		$this->assertStringNotContainsString( 'private storage is not writable', $output );
		$this->assertStringNotContainsString( 'remain publicly readable', $output );
	}
}
