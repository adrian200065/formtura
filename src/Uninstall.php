<?php
/**
 * Uninstall routine.
 *
 * The single entry point for destructive plugin cleanup. Everything here is
 * gated behind one canonical, explicitly-opted-into setting so that deleting
 * the plugin never silently destroys a site's forms and entries.
 *
 * @package Formtura
 * @since 1.0.5
 */

namespace Formtura;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uninstall class.
 */
class Uninstall {

	/**
	 * Options this plugin owns, removed only on a destructive uninstall.
	 *
	 * `fta_keep_data_on_uninstall` is obsolete and no longer read, but it is
	 * still listed so a destructive run leaves no orphaned rows behind.
	 *
	 * @since 1.0.5
	 * @var string[]
	 */
	private static $options = array(
		'fta_version',
		'fta_settings',
		'fta_smtp_settings',
		'fta_captcha_settings',
		'fta_integrations',
		'fta_payment_settings',
		'fta_keep_data_on_uninstall',
		'fta_private_migration_failed',
		'fta_db_version',
	);

	/**
	 * Tables this plugin owns, without the site prefix.
	 *
	 * @since 1.0.5
	 * @var string[]
	 */
	private static $tables = array(
		'fta_forms',
		'fta_entries',
		'fta_entry_meta',
	);

	/**
	 * Run the uninstall routine.
	 *
	 * Retains everything unless the administrator explicitly opted into
	 * deletion. Absent or false settings both mean retain.
	 *
	 * @since 1.0.5
	 * @param \Formtura\Frontend\File_Storage|null $storage Optional storage
	 *        service. Injected by tests so deletion runs against a
	 *        temporary vault.
	 * @return void
	 */
	public static function run( $storage = null ) {
		if ( ! self::should_delete_data() ) {
			return;
		}

		self::drop_tables();
		self::delete_files( $storage );
		self::delete_options();

		wp_cache_flush();
	}

	/**
	 * Remove stored files on a destructive uninstall.
	 *
	 * Only directories this plugin owns are touched: its own vault for the
	 * current site, and the legacy public directory it used to write to.
	 *
	 * @since 1.0.5
	 * @param \Formtura\Frontend\File_Storage|null $storage Optional service.
	 * @return void
	 */
	private static function delete_files( $storage = null ) {
		if ( ! $storage instanceof \Formtura\Frontend\File_Storage ) {
			if ( ! class_exists( \Formtura\Frontend\File_Storage::class ) ) {
				return;
			}

			$storage = new \Formtura\Frontend\File_Storage();
		}

		$storage->remove_site_files();
		$storage->remove_legacy_files();
	}

	/**
	 * Whether the administrator opted into destroying plugin data.
	 *
	 * Reads only the canonical key inside `fta_settings`. The absence of the
	 * key is treated as "retain", so an upgrade from a version that never
	 * saved it cannot be read as consent to delete.
	 *
	 * @since 1.0.5
	 * @return bool
	 */
	private static function should_delete_data() {
		$settings = get_option( 'fta_settings', array() );

		if ( ! is_array( $settings ) ) {
			return false;
		}

		return ! empty( $settings['delete_data_on_uninstall'] );
	}

	/**
	 * Drop this plugin's tables for the current site.
	 *
	 * Multisite note: this operates on the current site's prefixed tables
	 * only. Network-wide cleanup is not attempted.
	 *
	 * @since 1.0.5
	 * @return void
	 */
	private static function drop_tables() {
		global $wpdb;

		foreach ( self::$tables as $table ) {
			$prefixed = $wpdb->prefix . $table;

			$wpdb->query( "DROP TABLE IF EXISTS {$prefixed}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		}
	}

	/**
	 * Delete this plugin's options.
	 *
	 * @since 1.0.5
	 * @return void
	 */
	private static function delete_options() {
		foreach ( self::$options as $option ) {
			delete_option( $option );
		}
	}
}
