<?php
/**
 * Secret Crypto Class
 *
 * Reversible at-rest encryption for credentials stored in wp_options (the
 * SMTP password and the other provider secrets sanitize_smtp_settings()
 * handles). wp_options is readable by anything with database access - a
 * backup, a migration plugin, another admin - so a credential going in has to
 * come back out different.
 *
 * @package Formtura
 * @since 1.0.7
 */

namespace Formtura\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Secret_Crypto class.
 */
class Secret_Crypto {

	/**
	 * Cipher used for at-rest encryption.
	 *
	 * @var string
	 */
	const CIPHER = 'aes-256-cbc';

	/**
	 * Encrypt a secret for storage.
	 *
	 * @since 1.0.7
	 * @param string $plaintext Value to encrypt.
	 * @return string Base64-encoded IV + ciphertext, or '' for an empty input.
	 */
	public static function encrypt( $plaintext ) {
		$plaintext = (string) $plaintext;

		if ( '' === $plaintext ) {
			return '';
		}

		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( self::CIPHER ) );

		$ciphertext = openssl_encrypt( $plaintext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		return base64_encode( $iv . $ciphertext ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt a value previously produced by encrypt().
	 *
	 * A value that does not decode to a valid IV + ciphertext pair - most
	 * notably, a password saved by a version of this plugin that stored it
	 * as plaintext - is handed back unchanged rather than turned into an
	 * empty string, so an upgrade does not silently break an existing SMTP
	 * connection.
	 *
	 * @since 1.0.7
	 * @param string $stored Stored value, as returned by encrypt().
	 * @return string Decrypted value.
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;

		if ( '' === $stored ) {
			return '';
		}

		$iv_length = openssl_cipher_iv_length( self::CIPHER );
		$decoded   = base64_decode( $stored, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $decoded || strlen( $decoded ) <= $iv_length ) {
			return $stored;
		}

		$iv         = substr( $decoded, 0, $iv_length );
		$ciphertext = substr( $decoded, $iv_length );

		$plaintext = openssl_decrypt( $ciphertext, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv );

		return false === $plaintext ? $stored : $plaintext;
	}

	/**
	 * Derive the encryption key from WordPress's own auth salt, so the key
	 * material lives in wp-config.php rather than in this plugin or the
	 * database row it is protecting.
	 *
	 * @since 1.0.7
	 * @return string 32-byte raw key for aes-256-cbc.
	 */
	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ), true );
	}
}
