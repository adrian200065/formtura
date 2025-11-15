<?php
/**
 * Uninstall Formtura
 *
 * Handles cleanup when the plugin is deleted.
 *
 * @package Formtura
 * @since 1.0.0
 */

// Exit if accessed directly or not in uninstall context.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin data on uninstall.
 *
 * This function will only run if the user has chosen to remove all data
 * in the plugin settings.
 */
function fta_uninstall() {
	global $wpdb;

	// Check if user wants to keep data.
	$keep_data = get_option( 'fta_keep_data_on_uninstall', false );

	if ( $keep_data ) {
		return;
	}

	// Drop custom tables.
	$tables = [
		$wpdb->prefix . 'fta_forms',
		$wpdb->prefix . 'fta_entries',
		$wpdb->prefix . 'fta_entry_meta',
	];

	foreach ( $tables as $table ) {
		$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	// Remove plugin options.
	$options = [
		'fta_version',
		'fta_settings',
		'fta_smtp_settings',
		'fta_captcha_settings',
		'fta_integrations',
		'fta_payment_settings',
		'fta_keep_data_on_uninstall',
		'fta_db_version',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}

	// Clear any cached data.
	wp_cache_flush();
}

fta_uninstall();
