<?php
/**
 * Signature Class
 *
 * Turns the signature pad's PNG data URL into a stored file. Verification
 * happens before anything touches disk: the value must be a PNG data URL,
 * decode cleanly, stay under the size cap, and carry real PNG magic bytes.
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
	 * Process every signature field on a form.
	 *
	 * Mirrors Uploads::process_form_uploads(): returns a map of field name
	 * to file records, or a WP_Error carrying per-field messages.
	 *
	 * @since 1.0.4
	 * @param array $form Form data.
	 * @return array|\WP_Error Map of field name => file records, or WP_Error.
	 */
	public function process_form_signatures( $form ) {
		$results = [];
		$errors  = [];

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $results;
		}

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
			// full before anything is done with it.
			$value = isset( $_POST[ $field_name ] ) ? (string) wp_unslash( $_POST[ $field_name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

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

			$stored = $this->store_png( $binary );

			if ( is_wp_error( $stored ) ) {
				$errors[ $field_name ] = $stored->get_error_message();
				continue;
			}

			// A list with one record, matching the uploads shape, so entry
			// display and email attachment treat both identically.
			$results[ $field_name ] = [ $stored ];
		}

		if ( ! empty( $errors ) ) {
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
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'signature_store_failed', __( 'The signature could not be saved. Please try again.', FORMTURA_TEXTDOMAIN ) );
		}

		$dir = $uploads['basedir'] . '/' . Uploads::UPLOAD_DIR . $uploads['subdir'];
		$url = $uploads['baseurl'] . '/' . Uploads::UPLOAD_DIR . $uploads['subdir'];

		Uploads::protect_upload_dir( $uploads['basedir'] . '/' . Uploads::UPLOAD_DIR );

		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'signature_store_failed', __( 'The signature could not be saved. Please try again.', FORMTURA_TEXTDOMAIN ) );
		}

		// Random filename, matching the uploads convention, so stored
		// signatures cannot be enumerated.
		$filename = wp_generate_password( 24, false, false ) . '.png';
		$path     = $dir . '/' . $filename;

		if ( false === file_put_contents( $path, $binary ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new \WP_Error( 'signature_store_failed', __( 'The signature could not be saved. Please try again.', FORMTURA_TEXTDOMAIN ) );
		}

		return [
			'name' => 'signature.png',
			'file' => $path,
			'url'  => $url . '/' . $filename,
			'type' => 'image/png',
			'size' => strlen( $binary ),
		];
	}
}
