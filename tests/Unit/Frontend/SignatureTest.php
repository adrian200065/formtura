<?php
/**
 * Signature decoding and storage tests.
 *
 * The pad submits a PNG data URL; the server must verify it really is a
 * small PNG before anything touches disk, and must fail closed on junk.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Signature;
use Formtura\Tests\TestCase;

class SignatureTest extends TestCase {

	const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	protected function setUp(): void {
		parent::setUp();
		$_POST = [];
	}

	protected function tearDown(): void {
		$_POST = [];
		$this->removeUploadTree();
		parent::tearDown();
	}

	private function dataUrl() {
		return 'data:image/png;base64,' . self::PNG_BASE64;
	}

	private function realPngBinary() {
		return base64_decode( self::PNG_BASE64 );
	}

	/**
	 * Absolute path to the directory Signature/Uploads write to under the
	 * stubbed wp_upload_dir().
	 *
	 * @return string
	 */
	private function uploadDir() {
		return sys_get_temp_dir() . '/formtura-tests-uploads/formtura';
	}

	/**
	 * Delete everything the tests wrote under the stubbed upload base, so a
	 * storage test does not leave .htaccess/index.php/the directory itself
	 * behind for the next test run.
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

	public function test_valid_png_data_url_decodes() {
		$binary = Signature::decode_data_url( $this->dataUrl() );

		$this->assertIsString( $binary );
		$this->assertStringStartsWith( "\x89PNG", $binary );
	}

	public function test_non_data_url_is_rejected() {
		$this->assertInstanceOf( \WP_Error::class, Signature::decode_data_url( 'hello' ) );
	}

	public function test_jpeg_data_url_is_rejected() {
		$result = Signature::decode_data_url( 'data:image/jpeg;base64,' . self::PNG_BASE64 );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_png_prefix_with_non_png_bytes_is_rejected() {
		$result = Signature::decode_data_url( 'data:image/png;base64,' . base64_encode( '<?php evil(); ?>' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * The 8-byte magic-byte check alone would accept this - it only rules
	 * out non-PNG content, not a genuinely well-formed image.
	 * getimagesizefromstring() catches what the prefix check cannot.
	 */
	public function test_magic_bytes_alone_are_not_enough_a_truncated_png_is_rejected() {
		$blob = "\x89PNG\r\n\x1a\n" . 'short';

		$result = Signature::decode_data_url( 'data:image/png;base64,' . base64_encode( $blob ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_invalid_base64_is_rejected() {
		$result = Signature::decode_data_url( 'data:image/png;base64,!!!not-base64!!!' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * base64_decode() is called in strict mode. Whitespace is tolerated by
	 * both strict and non-strict decoding, but a genuinely invalid
	 * character (here, "@") is only rejected in strict mode - non-strict
	 * decoding silently discards it and would still decode this into a
	 * valid-looking PNG. This fails if the strict flag is ever dropped.
	 */
	public function test_strict_mode_rejects_base64_with_an_invalid_character() {
		$corrupted = substr( self::PNG_BASE64, 0, 10 ) . '@' . substr( self::PNG_BASE64, 10 );

		$result = Signature::decode_data_url( 'data:image/png;base64,' . $corrupted );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_oversize_payload_is_rejected() {
		// Base64 of >1MB of PNG-prefixed data. Below the raw base64-length
		// precheck threshold, so this specifically exercises the decoded-size
		// cap rather than the precheck (see the next test for that).
		$blob   = "\x89PNG\r\n\x1a\n" . str_repeat( 'A', 1048577 );
		$result = Signature::decode_data_url( 'data:image/png;base64,' . base64_encode( $blob ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/**
	 * Exercises the raw base64-length precheck itself (`strlen( $base64 ) >
	 * MAX_BYTES * 1.4`), which the test above never reaches because its
	 * base64 string is shorter than this threshold. For any syntactically
	 * valid base64, a string this long is guaranteed to decode past the
	 * cap too, so this cannot be distinguished from the decoded-size check
	 * by outcome alone - what it guarantees is that a base64 string this
	 * large is rejected without a behavioural regression, and it directly
	 * covers the precheck's branch so it cannot be deleted silently.
	 */
	public function test_base64_length_precheck_rejects_oversize_base64() {
		$base64 = str_repeat( 'A', (int) ( Signature::MAX_BYTES * 1.4 ) + 4 );

		$result = Signature::decode_data_url( 'data:image/png;base64,' . $base64 );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_decoded_size_at_cap_is_accepted() {
		$binary = $this->realPngBinary();
		$padded = $binary . str_repeat( "\x00", Signature::MAX_BYTES - strlen( $binary ) );
		$this->assertSame( Signature::MAX_BYTES, strlen( $padded ) );

		$result = Signature::decode_data_url( 'data:image/png;base64,' . base64_encode( $padded ) );

		$this->assertIsString( $result );
	}

	public function test_decoded_size_one_byte_over_cap_is_rejected() {
		$binary = $this->realPngBinary();
		$padded = $binary . str_repeat( "\x00", Signature::MAX_BYTES + 1 - strlen( $binary ) );
		$this->assertSame( Signature::MAX_BYTES + 1, strlen( $padded ) );

		$result = Signature::decode_data_url( 'data:image/png;base64,' . base64_encode( $padded ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_missing_required_signature_is_a_field_error() {
		$form = [ 'fields' => [ [ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here', 'required' => true ] ] ];

		$result = ( new Signature() )->process_form_signatures( $form );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$errors = $result->get_error_data();
		$this->assertArrayHasKey( 'field_sig', $errors );
	}

	public function test_missing_optional_signature_is_skipped() {
		$form = [ 'fields' => [ [ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here', 'required' => false ] ] ];

		$this->assertSame( [], ( new Signature() )->process_form_signatures( $form ) );
	}

	/**
	 * A crafted `field_sig[]=x` submission makes $_POST['field_sig'] an
	 * array. Casting that to string emits an "Array to string conversion"
	 * warning, which PHPUnit turns into a test failure - so this fails if
	 * the is_string() guard is ever removed.
	 */
	public function test_array_value_is_treated_as_empty_without_warning() {
		$_POST['field_sig'] = [ 'unexpected' ];
		$form = [ 'fields' => [ [ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here', 'required' => true ] ] ];

		$result = ( new Signature() )->process_form_signatures( $form );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'field_sig', $result->get_error_data() );
	}

	public function test_valid_signature_is_stored_as_a_file_record() {
		$_POST['field_sig'] = $this->dataUrl();
		$form = [ 'fields' => [ [ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here' ] ] ];

		$result = ( new Signature() )->process_form_signatures( $form );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'field_sig', $result );

		$record = $result['field_sig'][0];
		$this->assertSame( 'signature.png', $record['name'] );
		$this->assertSame( 'image/png', $record['type'] );
		$this->assertArrayHasKey( 'url', $record );
		$this->assertNotEmpty( $record['url'] );
		$this->assertSame( strlen( $this->realPngBinary() ), $record['size'] );
		$this->assertFileExists( $record['file'] );
		$this->assertStringEndsWith( '.png', $record['file'] );

		unlink( $record['file'] );
	}

	/**
	 * Proves the two-phase fix for the orphaned-file defect: a form with two
	 * signature fields where the first is valid and the second is not must
	 * not write the first field's file to disk at all, since the whole
	 * request fails.
	 */
	public function test_second_field_failure_does_not_orphan_first_fields_file() {
		$_POST['field_sig_1'] = $this->dataUrl();
		$_POST['field_sig_2'] = 'not-a-data-url';

		$form = [
			'fields' => [
				[ 'id' => 'field_sig_1', 'type' => 'signature', 'label' => 'First' ],
				[ 'id' => 'field_sig_2', 'type' => 'signature', 'label' => 'Second' ],
			],
		];

		$result = ( new Signature() )->process_form_signatures( $form );

		$this->assertInstanceOf( \WP_Error::class, $result );

		$written = is_dir( $this->uploadDir() ) ? glob( $this->uploadDir() . '/*.png' ) : [];

		$this->assertSame( [], $written, "The first field's file must not be written when a later field fails." );
	}
}
