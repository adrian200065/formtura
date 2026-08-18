<?php
/**
 * E2E mail capture.
 *
 * Short-circuits wp_mail() so E2E runs never send real email (there is no
 * SMTP server configured in the disposable test instance, and there never
 * should be one). Every call is instead appended as one JSON line to
 * e2e-mail-log.json in the WordPress root, which tests read to assert a
 * notification was sent with the right recipient/subject/body.
 *
 * Symlinked into wp-content/mu-plugins by scripts/e2e-env.sh; not loaded
 * outside the E2E environment.
 *
 * @package Formtura
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pre_wp_mail',
	function ( $null, $atts ) {
		$log_file = ABSPATH . 'e2e-mail-log.json';

		$entry = array(
			'to'      => $atts['to'],
			'subject' => $atts['subject'],
			'message' => $atts['message'],
			'headers' => $atts['headers'],
			'time'    => time(),
		);

		file_put_contents( $log_file, wp_json_encode( $entry ) . "\n", FILE_APPEND | LOCK_EX );

		return true;
	},
	10,
	2
);
