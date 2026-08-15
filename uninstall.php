<?php
/**
 * Uninstall Formtura
 *
 * Thin bootstrap. All cleanup logic lives in Formtura\Uninstall so that it is
 * testable; this file only loads the autoloader and delegates.
 *
 * @package Formtura
 * @since 1.0.0
 */

// Exit if accessed directly or not in uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$fta_autoloader = __DIR__ . '/vendor/autoload.php';

// Without the autoloader there is no way to run the guarded routine. Doing
// nothing is the correct outcome: retaining data is always recoverable,
// deleting it is not.
if ( ! file_exists( $fta_autoloader ) ) {
	return;
}

require_once $fta_autoloader;

if ( ! class_exists( \Formtura\Uninstall::class ) ) {
	return;
}

\Formtura\Uninstall::run();
