<?php
/**
 * Database Installer Class
 *
 * Creates and updates custom database tables.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Database;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Installer class.
 */
class Installer {

	/**
	 * Database version.
	 *
	 * @var string
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Run activation tasks.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		self::create_tables();
		self::set_default_options();
		self::update_db_version();
	}

	/**
	 * Create custom database tables.
	 *
	 * @since 1.0.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Forms table.
		$forms_table = $wpdb->prefix . 'fta_forms';
		$forms_sql = "CREATE TABLE {$forms_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			title varchar(255) NOT NULL,
			description text,
			fields longtext,
			settings longtext,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		// Entries table.
		$entries_table = $wpdb->prefix . 'fta_entries';
		$entries_sql = "CREATE TABLE {$entries_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			form_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			ip_address varchar(45),
			user_agent varchar(255),
			is_read tinyint(1) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL,
			PRIMARY KEY (id),
			KEY form_id (form_id),
			KEY user_id (user_id),
			KEY is_read (is_read),
			KEY created_at (created_at)
		) {$charset_collate};";

		// Entry meta table.
		$entry_meta_table = $wpdb->prefix . 'fta_entry_meta';
		$entry_meta_sql = "CREATE TABLE {$entry_meta_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			entry_id bigint(20) unsigned NOT NULL,
			meta_key varchar(255) NOT NULL,
			meta_value longtext,
			PRIMARY KEY (id),
			KEY entry_id (entry_id),
			KEY meta_key (meta_key)
		) {$charset_collate};";

		// Include WordPress upgrade functions.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// Create tables.
		dbDelta( $forms_sql );
		dbDelta( $entries_sql );
		dbDelta( $entry_meta_sql );
	}

	/**
	 * Set default plugin options.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		// Set default settings if not already set.
		if ( ! get_option( 'fta_settings' ) ) {
			$default_settings = [
				'load_css'               => true,
				'load_js'                => true,
				'debug_mode'             => false,
				'recaptcha_site_key'     => '',
				'recaptcha_secret_key'   => '',
				'recaptcha_version'      => 'v2',
				'currency'               => 'USD',
				'keep_data_on_uninstall' => false,
			];

			add_option( 'fta_settings', $default_settings );
		}

		// Set default SMTP settings if not already set.
		if ( ! get_option( 'fta_smtp_settings' ) ) {
			$default_smtp = [
				'enabled'           => false,
				'mailer'            => 'smtp',
				'from_email'        => get_option( 'admin_email' ),
				'from_name'         => get_option( 'blogname' ),
				'smtp_host'         => '',
				'smtp_port'         => 587,
				'smtp_auth'         => true,
				'smtp_username'     => '',
				'smtp_password'     => '',
				'smtp_encryption'   => 'tls',
			];

			add_option( 'fta_smtp_settings', $default_smtp );
		}
	}

	/**
	 * Update database version.
	 *
	 * @since 1.0.0
	 */
	private static function update_db_version() {
		update_option( 'fta_db_version', self::DB_VERSION );
	}

	/**
	 * Check if database needs update.
	 *
	 * @since 1.0.0
	 * @return bool True if update needed.
	 */
	public static function needs_update() {
		$current_version = get_option( 'fta_db_version', '0' );
		return version_compare( $current_version, self::DB_VERSION, '<' );
	}

	/**
	 * Update database if needed.
	 *
	 * @since 1.0.0
	 */
	public static function maybe_update() {
		if ( self::needs_update() ) {
			self::create_tables();
			self::update_db_version();
		}
	}
}
