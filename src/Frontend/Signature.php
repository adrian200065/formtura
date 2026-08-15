<?php
/**
 * Signature Class
 *
 * Turns the signature pad's PNG data URL into a stored file. Verification
 * happens before anything touches disk: the value must be a PNG data URL,
 * decode cleanly, stay under the size cap, carry real PNG magic bytes, and
 * parse as a genuine PNG image.
 *
 * @package Formtura
 * @since 1.0.4
 */

namespace Formtura\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signature class.
 */
class Signature {

	/**
	 * Largest accepted decoded payload, in bytes.
	 */
	const MAX_BYTES = 1048576;

	/**
	 * Private storage service.
	 *
	 * @var File_Storage
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @since 1.0.5
	 * @param File_Storage|null $storage Optional storage service. Injected by
	 *                                   tests so they write to a temporary
	 *                                   vault instead of the real one.
	 */
	public function __construct( $storage = null ) {
		$this->storage = $storage instanceof File_Storage ? $storage : new File_Storage();
	}

	/**
	 * Process every signature field on a form.
	 *
	 * Mirrors Uploads::process_form_uploads(): returns a map of field name
	 * to file records, or a WP_Error carrying per-field messages.
	 *
	 * Two-phase: every field is decoded and verified first, and only once
	 * all of them pass does the second phase write anything to disk. This
	 * matters when a form has more than one signature field - without it, an
	 * earlier field's file would already be written by the time a later
	 * field fails, orphaning it.
	 *
	 * @since 1.0.4
	 * @param array $form Form data.
	 * @return array|\WP_Error Map of field name => file records, or WP_Error.
	 */
	public function process_form_signatures( $form ) {
		$results = [];
		$errors  = [];
		$decoded = [];

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $results;
		}

		// Phase 1: decode and verify every field. Nothing is written yet.
		foreach ( $form['fields'] as $field ) {
			if ( 'signature' !== ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
				continue;
			}

			$field_name = fta_get_field_name( $field );

			if ( '' === $field_name ) {
				continue;
			}

			// Deliberately not sanitize_text_field(): a multi-hundred-KB
			// data URL is not text, and decode_data_url() validates it in
			// full before anything is done with it. A crafted array value
			// (e.g. field_sig[]=x) is treated as no value rather than cast
			// to string, which would emit an "Array to string conversion"
			// warning and could corrupt the AJAX JSON response.
			$raw   = isset( $_POST[ $field_name ] ) ? $_POST[ $field_name ] : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$value = is_string( $raw ) ? wp_unslash( $raw ) : '';

			if ( '' === $value ) {
				if ( ! empty( $field['required'] ) ) {
					$errors[ $field_name ] = sprintf(
						/* translators: %s: field label */
						__( '%s is required.', FORMTURA_TEXTDOMAIN ),
						isset( $field['label'] ) ? $field['label'] : $field_name
					);
				}

				continue;
			}

			$binary = self::decode_data_url( $value );

			if ( is_wp_error( $binary ) ) {
				$errors[ $field_name ] = $binary->get_error_message();
				continue;
			}

			$decoded[ $field_name ] = $binary;
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'signature_failed',
				__( 'Please correct the errors below.', FORMTURA_TEXTDOMAIN ),
				$errors
			);
		}

		// Phase 2: every field verified, so it is now safe to write.
		foreach ( $decoded as $field_name => $binary ) {
			$stored = $this->store_png( $binary );

			if ( is_wp_error( $stored ) ) {
				$errors[ $field_name ] = $stored->get_error_message();
				break;
			}

			// A list with one record, matching the uploads shape, so entry
			// display and download link generation treat signatures and
			// uploads identically. Email attachment does not:
			// Uploads::get_email_attachments() filters on the literal
			// 'file-upload' type, so signatures are not attached to
			// notification emails today.
			$results[ $field_name ] = [ $stored ];
		}

		if ( ! empty( $errors ) ) {
			// A later field's write failed after an earlier one succeeded;
			// do not leave that earlier file behind.
			$this->storage->delete_records( $results );

			return new \WP_Error(
				'signature_failed',
				__( 'Please correct the errors below.', FORMTURA_TEXTDOMAIN ),
				$errors
			);
		}

		return $results;
	}

	/**
	 * Decode and verify a signature data URL.
	 *
	 * @since 1.0.4
	 * @param string $value Raw submitted value.
	 * @return string|\WP_Error Binary PNG bytes, or WP_Error.
	 */
	public static function decode_data_url( $value ) {
		$invalid = new \WP_Error( 'invalid_signature', __( 'The signature could not be read. Please sign again.', FORMTURA_TEXTDOMAIN ) );

		if ( 0 !== strpos( $value, 'data:image/png;base64,' ) ) {
			return $invalid;
		}

		$base64 = substr( $value, strlen( 'data:image/png;base64,' ) );

		// A 1MB PNG is ~1.37MB of base64; anything larger cannot pass the
		// decoded cap, so reject before spending memory on the decode.
		if ( strlen( $base64 ) > self::MAX_BYTES * 1.4 ) {
			return $invalid;
		}

		$binary = base64_decode( $base64, true );

		if ( false === $binary || strlen( $binary ) > self::MAX_BYTES ) {
			return $invalid;
		}

		// Real PNG bytes, not just a claimed mime type.
		if ( "\x89PNG\r\n\x1a\n" !== substr( $binary, 0, 8 ) ) {
			return $invalid;
		}

		// The magic bytes alone only rule out non-PNG content; they say
		// nothing about whether the rest of the file is a well-formed
		// image. getimagesizefromstring() parses the header for real,
		// entirely in memory, so this closes that gap without a temp file.
		$image = @getimagesizefromstring( $binary ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( false === $image || IMAGETYPE_PNG !== $image[2] ) {
			return $invalid;
		}

		return $binary;
	}

	/**
	 * Write PNG bytes into the plugin's protected upload directory.
	 *
	 * @since 1.0.4
	 * @param string $binary Verified PNG bytes.
	 * @return array|\WP_Error File record matching the uploads shape.
	 */
	private function store_png( $binary ) {
		$failed = new \WP_Error( 'signature_store_failed', __( 'The signature could not be saved. Please try again.', FORMTURA_TEXTDOMAIN ) );

		// Fails closed: with no writable private vault there is nowhere safe to
		// put a signature, and falling back to a public directory is exactly
		// the behaviour this replaces.
		$dir = $this->storage->prepare_directory();

		if ( false === $dir ) {
			return $failed;
		}

		// Random filename, matching the uploads convention, so stored
		// signatures cannot be enumerated.
		$filename = wp_generate_password( 24, false, false ) . '.png';
		$path     = $dir . '/' . $filename;

		if ( false === file_put_contents( $path, $binary ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return $failed;
		}

		@chmod( $path, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		$record = $this->storage->create_record( 'signature.png', $path, 'image/png', strlen( $binary ) );

		if ( false === $record ) {
			wp_delete_file( $path );

			return $failed;
		}

		return $record;
	}
}
