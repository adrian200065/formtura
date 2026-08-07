<?php
/**
 * File upload validation tests.
 *
 * Covers the security-critical decisions: which extensions a form may accept,
 * how $_FILES is normalized, and the size limits. The move-to-disk step is
 * WordPress's own wp_handle_upload() and is not exercised here.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Uploads;
use Formtura\Tests\TestCase;

class UploadsTest extends TestCase {

	/**
	 * @var Uploads
	 */
	private $uploads;

	protected function setUp(): void {
		parent::setUp();

		$this->uploads = new Uploads();
		$_FILES        = [];
	}

	protected function tearDown(): void {
		$_FILES = [];

		parent::tearDown();
	}

	/**
	 * Call a private method.
	 *
	 * @param string $method Method name.
	 * @param array  $args   Arguments.
	 * @return mixed
	 */
	private function invoke( $method, array $args = [] ) {
		$reflection = new \ReflectionMethod( Uploads::class, $method );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( $this->uploads, $args );
	}

	/**
	 * Build a $_FILES-shaped entry.
	 *
	 * @param string $name  Filename.
	 * @param int    $size  Size in bytes.
	 * @param int    $error Upload error code.
	 * @return array
	 */
	private function file( $name = 'photo.jpg', $size = 1024, $error = UPLOAD_ERR_OK ) {
		return [
			'name'     => $name,
			'type'     => 'image/jpeg',
			'tmp_name' => '/tmp/phpXXXX',
			'error'    => $error,
			'size'     => $size,
		];
	}

	public function test_allowed_extensions_are_parsed_from_the_field() {
		$allowed = $this->invoke( 'get_allowed_extensions', [
			[ 'allowedFileTypes' => 'specify', 'specifiedTypes' => 'jpg, .PNG , gif' ],
		] );

		$this->assertSame( [ 'jpg', 'png', 'gif' ], $allowed );
	}

	/**
	 * The hard block list must win over anything the form author configures.
	 */
	public function test_a_form_cannot_allow_executable_extensions() {
		$allowed = $this->invoke( 'get_allowed_extensions', [
			[ 'allowedFileTypes' => 'specify', 'specifiedTypes' => 'jpg, php, phtml, svg, html, exe' ],
		] );

		$this->assertSame( [ 'jpg' ], $allowed );
		$this->assertNotContains( 'php', $allowed );
		$this->assertNotContains( 'svg', $allowed );
	}

	public function test_no_whitelist_means_any_recognised_type() {
		$allowed = $this->invoke( 'get_allowed_extensions', [
			[ 'allowedFileTypes' => 'any' ],
		] );

		$this->assertSame( [], $allowed );
	}

	/**
	 * @dataProvider blockedExtensionProvider
	 * @param string $filename Filename to reject.
	 */
	public function test_blocked_extensions_are_always_rejected( $filename ) {
		$result = $this->invoke( 'check_type', [
			$this->file( $filename ),
			[ 'allowedFileTypes' => 'any' ],
		] );

		$this->assertInstanceOf( \WP_Error::class, $result, "{$filename} should be rejected." );
		$this->assertSame( 'upload_blocked_type', $result->get_error_code() );
	}

	/**
	 * @return array[]
	 */
	public function blockedExtensionProvider() {
		return [
			[ 'shell.php' ],
			[ 'shell.PHP' ],
			[ 'shell.phtml' ],
			[ 'archive.phar' ],
			[ 'page.html' ],
			[ 'vector.svg' ],
			[ 'run.exe' ],
			[ 'script.sh' ],
			[ '.htaccess' ],
		];
	}

	public function test_extension_not_on_the_whitelist_is_rejected() {
		$result = $this->invoke( 'check_type', [
			$this->file( 'notes.txt' ),
			[ 'allowedFileTypes' => 'specify', 'specifiedTypes' => 'jpg, png' ],
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'upload_disallowed_type', $result->get_error_code() );
	}

	public function test_allowed_extension_passes() {
		$result = $this->invoke( 'check_type', [
			$this->file( 'photo.jpg' ),
			[ 'allowedFileTypes' => 'specify', 'specifiedTypes' => 'jpg, png' ],
		] );

		$this->assertTrue( $result );
	}

	public function test_file_with_no_extension_is_rejected() {
		$result = $this->invoke( 'check_type', [
			$this->file( 'noextension' ),
			[ 'allowedFileTypes' => 'any' ],
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'upload_no_extension', $result->get_error_code() );
	}

	public function test_unrecognised_type_is_rejected() {
		$result = $this->invoke( 'check_type', [
			$this->file( 'thing.xyz' ),
			[ 'allowedFileTypes' => 'any' ],
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'upload_unrecognised_type', $result->get_error_code() );
	}

	public function test_max_size_is_enforced_in_megabytes() {
		$field  = [ 'maxFileSize' => 1 ];
		$under  = $this->invoke( 'check_size', [ $this->file( 'a.jpg', 900 * 1024 ), $field ] );
		$over   = $this->invoke( 'check_size', [ $this->file( 'a.jpg', 2 * 1024 * 1024 ), $field ] );

		$this->assertTrue( $under );
		$this->assertInstanceOf( \WP_Error::class, $over );
		$this->assertSame( 'upload_too_large', $over->get_error_code() );
	}

	public function test_min_size_is_enforced() {
		$result = $this->invoke( 'check_size', [
			$this->file( 'a.jpg', 100 ),
			[ 'minFileSize' => 1 ],
		] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'upload_too_small', $result->get_error_code() );
	}

	public function test_empty_file_is_rejected() {
		$result = $this->invoke( 'check_size', [ $this->file( 'a.jpg', 0 ), [] ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'upload_empty', $result->get_error_code() );
	}

	public function test_single_file_input_is_normalized() {
		$_FILES = [ 'field_1' => $this->file( 'one.jpg' ) ];

		$files = $this->invoke( 'collect_field_files', [ 'field_1' ] );

		$this->assertCount( 1, $files );
		$this->assertSame( 'one.jpg', $files[0]['name'] );
	}

	/**
	 * PHP nests multi-file inputs by property, not by file.
	 */
	public function test_multi_file_input_is_normalized() {
		$_FILES = [
			'field_1' => [
				'name'     => [ 'one.jpg', 'two.png' ],
				'type'     => [ 'image/jpeg', 'image/png' ],
				'tmp_name' => [ '/tmp/a', '/tmp/b' ],
				'error'    => [ UPLOAD_ERR_OK, UPLOAD_ERR_OK ],
				'size'     => [ 100, 200 ],
			],
		];

		$files = $this->invoke( 'collect_field_files', [ 'field_1' ] );

		$this->assertCount( 2, $files );
		$this->assertSame( 'two.png', $files[1]['name'] );
		$this->assertSame( 200, $files[1]['size'] );
	}

	public function test_empty_file_slots_are_dropped() {
		$_FILES = [
			'field_1' => [
				'name'     => [ 'one.jpg', '' ],
				'type'     => [ 'image/jpeg', '' ],
				'tmp_name' => [ '/tmp/a', '' ],
				'error'    => [ UPLOAD_ERR_OK, UPLOAD_ERR_NO_FILE ],
				'size'     => [ 100, 0 ],
			],
		];

		$files = $this->invoke( 'collect_field_files', [ 'field_1' ] );

		$this->assertCount( 1, $files );
	}

	public function test_missing_field_yields_no_files() {
		$this->assertSame( [], $this->invoke( 'collect_field_files', [ 'nope' ] ) );
	}

	public function test_required_field_with_no_file_reports_an_error() {
		$form = [
			'fields' => [
				[ 'id' => 'f1', 'type' => 'file-upload', 'label' => 'Resume', 'required' => true ],
			],
		];

		$result = $this->uploads->process_form_uploads( $form );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'f1', $result->get_error_data() );
	}

	public function test_optional_field_with_no_file_is_fine() {
		$form = [
			'fields' => [
				[ 'id' => 'f1', 'type' => 'file-upload', 'label' => 'Resume' ],
			],
		];

		$this->assertSame( [], $this->uploads->process_form_uploads( $form ) );
	}

	public function test_forms_without_file_fields_are_ignored() {
		$form = [ 'fields' => [ [ 'id' => 'f1', 'type' => 'text' ] ] ];

		$this->assertSame( [], $this->uploads->process_form_uploads( $form ) );
	}

	public function test_php_upload_errors_are_translated() {
		$too_big = $this->invoke( 'check_php_upload_error', [ $this->file( 'a.jpg', 1, UPLOAD_ERR_INI_SIZE ) ] );
		$partial = $this->invoke( 'check_php_upload_error', [ $this->file( 'a.jpg', 1, UPLOAD_ERR_PARTIAL ) ] );
		$ok      = $this->invoke( 'check_php_upload_error', [ $this->file() ] );

		$this->assertSame( 'upload_too_large', $too_big->get_error_code() );
		$this->assertSame( 'upload_partial', $partial->get_error_code() );
		$this->assertTrue( $ok );
	}

	public function test_camera_counts_as_a_file_field() {
		$this->assertTrue( Uploads::is_file_field( [ 'type' => 'camera' ] ) );
		$this->assertTrue( Uploads::is_file_field( [ 'type' => 'file-upload' ] ) );
		$this->assertFalse( Uploads::is_file_field( [ 'type' => 'text' ] ) );
		$this->assertFalse( Uploads::is_file_field( [] ) );
	}

	public function test_camera_fields_only_allow_images_whatever_the_settings_say() {
		$extensions = $this->invoke( 'get_allowed_extensions', [ [
			'type'             => 'camera',
			'allowedFileTypes' => 'specify',
			'specifiedTypes'   => 'pdf, docx, exe',
		] ] );

		$this->assertSame( [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ], $extensions );
	}

	public function test_email_attachments_only_include_flagged_fields() {
		$existing = tempnam( sys_get_temp_dir(), 'fta' );

		$form = [
			'fields' => [
				[ 'id' => 'f1', 'type' => 'file-upload', 'attachToEmail' => true ],
				[ 'id' => 'f2', 'type' => 'file-upload', 'attachToEmail' => false ],
			],
		];

		$entry = [
			'f1' => [ [ 'name' => 'a.jpg', 'file' => $existing, 'url' => '' ] ],
			'f2' => [ [ 'name' => 'b.jpg', 'file' => $existing, 'url' => '' ] ],
		];

		$attachments = Uploads::get_email_attachments( $form, $entry );

		$this->assertSame( [ $existing ], $attachments );

		unlink( $existing );
	}

	public function test_email_attachments_skip_missing_files() {
		$form = [
			'fields' => [ [ 'id' => 'f1', 'type' => 'file-upload', 'attachToEmail' => true ] ],
		];

		$entry = [ 'f1' => [ [ 'name' => 'a.jpg', 'file' => '/does/not/exist.jpg' ] ] ];

		$this->assertSame( [], Uploads::get_email_attachments( $form, $entry ) );
	}

	/**
	 * cleanup() is public so Signature can remove already-stored upload
	 * files when a later step in the same request (a signature field)
	 * fails - a rejected submission must never leave files behind.
	 */
	public function test_cleanup_deletes_stored_files() {
		$file = tempnam( sys_get_temp_dir(), 'fta' );
		$this->assertFileExists( $file );

		Uploads::cleanup( [ 'f1' => [ [ 'file' => $file ] ] ] );

		$this->assertFileDoesNotExist( $file );
	}

	public function test_cleanup_ignores_records_with_no_file_on_disk() {
		// Must not error when a record's file was never created, or was
		// already removed by an earlier cleanup call.
		Uploads::cleanup( [ 'f1' => [ [ 'file' => '/does/not/exist.png' ] ] ] );

		$this->addToAssertionCount( 1 );
	}
}
