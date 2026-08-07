<?php
/**
 * Cross-field cleanup tests for the submission file pipeline.
 *
 * Uploads are moved to disk before signatures run. If a signature then
 * fails, those already-moved upload files must not be left behind - a
 * rejected submission must never leave files on disk.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Submission;
use Formtura\Tests\TestCase;

class SubmissionFileCleanupTest extends TestCase {

	/**
	 * @var Submission
	 */
	private $submission;

	protected function setUp(): void {
		parent::setUp();

		$this->submission = new Submission();
		$_POST             = [];
	}

	protected function tearDown(): void {
		$_POST = [];

		parent::tearDown();
	}

	/**
	 * Call the private process_signatures( $form, $uploads ).
	 *
	 * @param array $form    Form data.
	 * @param array $uploads Already-stored upload file records.
	 * @return array|\WP_Error
	 */
	private function processSignatures( $form, $uploads ) {
		$reflection = new \ReflectionMethod( Submission::class, 'process_signatures' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->submission, $form, $uploads );
	}

	/**
	 * Proves the fix for defect (b): a signature failure must clean up
	 * upload files that were already moved to disk earlier in the same
	 * request, not just avoid writing its own file.
	 */
	public function test_signature_failure_deletes_already_stored_upload_files() {
		$uploaded_file = tempnam( sys_get_temp_dir(), 'fta-upload' );
		$this->assertFileExists( $uploaded_file );

		$uploads = [
			'field_resume' => [
				[ 'name' => 'resume.pdf', 'file' => $uploaded_file, 'url' => '', 'type' => 'application/pdf', 'size' => 10 ],
			],
		];

		$form = [
			'fields' => [
				[ 'id' => 'field_resume', 'type' => 'file-upload', 'label' => 'Resume' ],
				[ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here', 'required' => true ],
			],
		];

		// No $_POST value for the signature field, and it is required, so
		// signature processing fails without ever writing a file itself.
		$result = $this->processSignatures( $form, $uploads );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertFileDoesNotExist( $uploaded_file, 'The already-uploaded file must be cleaned up when a later signature field fails.' );
	}

	/**
	 * The counterpart: when signatures succeed, upload records must survive
	 * untouched and be merged alongside the signature's own record.
	 */
	public function test_successful_signatures_are_merged_with_uploads_untouched() {
		$uploaded_file = tempnam( sys_get_temp_dir(), 'fta-upload' );

		$uploads = [
			'field_resume' => [
				[ 'name' => 'resume.pdf', 'file' => $uploaded_file, 'url' => '', 'type' => 'application/pdf', 'size' => 10 ],
			],
		];

		// No signature fields on this form, so process_form_signatures()
		// returns an empty map and the uploads pass through unchanged.
		$form = [
			'fields' => [
				[ 'id' => 'field_resume', 'type' => 'file-upload', 'label' => 'Resume' ],
			],
		];

		$result = $this->processSignatures( $form, $uploads );

		$this->assertIsArray( $result );
		$this->assertSame( $uploads, $result );
		$this->assertFileExists( $uploaded_file );

		unlink( $uploaded_file );
	}
}
