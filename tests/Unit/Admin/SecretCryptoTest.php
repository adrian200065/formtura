<?php
/**
 * Reversible at-rest encryption for stored secrets (the SMTP password and
 * the other provider credentials sanitize_smtp_settings() handles).
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Secret_Crypto;
use Formtura\Tests\TestCase;

class SecretCryptoTest extends TestCase {

	public function test_encrypting_then_decrypting_returns_the_original_value() {
		$this->assertSame( 'hunter2', Secret_Crypto::decrypt( Secret_Crypto::encrypt( 'hunter2' ) ) );
	}

	/**
	 * The whole point: whatever gets stored in wp_options must not be the
	 * plaintext password.
	 */
	public function test_the_stored_value_does_not_contain_the_plaintext() {
		$stored = Secret_Crypto::encrypt( 'hunter2' );

		$this->assertStringNotContainsString( 'hunter2', $stored );
	}

	public function test_encrypting_the_same_value_twice_produces_different_ciphertext() {
		// A fresh random IV each call - otherwise two accounts sharing a
		// password would be visible as identical ciphertext in the database.
		$this->assertNotSame( Secret_Crypto::encrypt( 'hunter2' ), Secret_Crypto::encrypt( 'hunter2' ) );
	}

	public function test_an_empty_value_encrypts_to_an_empty_string() {
		$this->assertSame( '', Secret_Crypto::encrypt( '' ) );
	}

	public function test_decrypting_an_empty_string_returns_an_empty_string() {
		$this->assertSame( '', Secret_Crypto::decrypt( '' ) );
	}

	/**
	 * Sites already running the unencrypted version have a plaintext
	 * password sitting in wp_options. The upgrade must not turn that into a
	 * broken SMTP connection - decrypt() has to recognise a value that was
	 * never encrypted and hand it back unchanged.
	 */
	public function test_a_legacy_plaintext_value_is_returned_unchanged() {
		$this->assertSame( 'hunter2', Secret_Crypto::decrypt( 'hunter2' ) );
	}
}
