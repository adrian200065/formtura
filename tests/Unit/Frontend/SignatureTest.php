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
		parent::tearDown();
	}

	private function dataUrl() {
		return 'data:image/png;base64,' . self::PNG_BASE64;
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

	public function test_invalid_base64_is_rejected() {
		$result = Signature::decode_data_url( 'data:image/png;base64,!!!not-base64!!!' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_oversize_payload_is_rejected() {
		// Base64 of >1MB of PNG-prefixed data.
		$blob   = "\x89PNG\r\n\x1a\n" . str_repeat( 'A', 1048577 );
		$result = Signature::decode_data_url( 'data:image/png;base64,' . base64_encode( $blob ) );

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

	public function test_valid_signature_is_stored_as_a_file_record() {
		$_POST['field_sig'] = $this->dataUrl();
		$form = [ 'fields' => [ [ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here' ] ] ];

		$result = ( new Signature() )->process_form_signatures( $form );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'field_sig', $result );

		$record = $result['field_sig'][0];
		$this->assertSame( 'signature.png', $record['name'] );
		$this->assertSame( 'image/png', $record['type'] );
		$this->assertFileExists( $record['file'] );
		$this->assertStringEndsWith( '.png', $record['file'] );

		unlink( $record['file'] );
	}
}
