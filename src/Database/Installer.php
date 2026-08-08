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
	const DB_VERSION = '1.0.3';

	/**
	 * Run activation tasks.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		$is_new_install = ! get_option( 'fta_db_version' );

		self::create_tables();
		self::set_default_options();
		self::protect_upload_dir();

		// A fresh install has no legacy forms to rewrite.
		if ( ! $is_new_install ) {
			self::run_migrations();
		}

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
				'load_css'                  => true,
				'load_js'                   => true,
				'debug_mode'                => false,
				'recaptcha_site_key'        => '',
				'recaptcha_secret_key'      => '',
				'recaptcha_version'         => 'v2',
				'recaptcha_score_threshold' => 0.5,
				'currency'                  => 'USD',
				'keep_data_on_uninstall'    => false,
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
		if ( ! self::needs_update() ) {
			return;
		}

		self::create_tables();
		self::protect_upload_dir();
		self::run_migrations();
		self::update_db_version();
	}

	/**
	 * Create and guard the plugin's upload directory.
	 *
	 * @since 1.0.3
	 */
	private static function protect_upload_dir() {
		$uploads = wp_upload_dir();

		if ( empty( $uploads['basedir'] ) ) {
			return;
		}

		\Formtura\Frontend\Uploads::protect_upload_dir(
			$uploads['basedir'] . '/' . \Formtura\Frontend\Uploads::UPLOAD_DIR
		);
	}

	/**
	 * Run data migrations for the stored database version.
	 *
	 * @since 1.0.3
	 */
	private static function run_migrations() {
		$from = get_option( 'fta_db_version', '0' );

		if ( version_compare( $from, '1.0.3', '<' ) ) {
			self::migrate_choice_field_types();
		}
	}

	/**
	 * Align choice field type slugs with their meaning.
	 *
	 * Before 1.0.3 the builder offered `checkbox` labelled "Multiple Choice"
	 * but rendered it as radio inputs, and used `checkboxes` for the real
	 * multi-answer field - the opposite of what fta_get_field_types() declared.
	 * Saved forms are rewritten to the conventional slugs:
	 *
	 *   checkbox   -> radio     (single answer, radio inputs)
	 *   checkboxes -> checkbox  (multiple answers, checkbox inputs)
	 *
	 * Both are computed from the original type in a single pass, so the two
	 * rules cannot cascade into each other within one run. Entry data is keyed
	 * by field id and is unaffected.
	 *
	 * The rewrite is NOT idempotent - a second pass would map an already
	 * migrated `checkbox` on to `radio`, and the slug alone cannot reveal which
	 * meaning it carries. Forms are therefore recorded as they are migrated so
	 * a run interrupted part way through can resume without corrupting the
	 * forms it already handled.
	 *
	 * @since 1.0.3
	 */
	private static function migrate_choice_field_types() {
		global $wpdb;

		$table = $wpdb->prefix . 'fta_forms';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$forms = $wpdb->get_results( "SELECT id, fields FROM {$table}" );

		if ( empty( $forms ) ) {
			return;
		}

		$done = get_option( 'fta_migrated_choice_types', [] );

		if ( ! is_array( $done ) ) {
			$done = [];
		}

		foreach ( $forms as $form ) {
			$form_id = (int) $form->id;

			if ( in_array( $form_id, $done, true ) ) {
				continue;
			}

			$fields = json_decode( $form->fields, true );

			if ( ! is_array( $fields ) ) {
				continue;
			}

			$migrated = self::migrate_field_types( $fields );

			if ( $migrated !== $fields ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->update(
					$table,
					[ 'fields' => wp_json_encode( $migrated ) ],
					[ 'id' => $form_id ],
					[ '%s' ],
					[ '%d' ]
				);

				fta_log( sprintf( 'Migrated choice field types on form %d.', $form_id ) );
			}

			// Recorded even when nothing changed, so a resumed run skips it.
			$done[] = $form_id;
			update_option( 'fta_migrated_choice_types', $done, false );
		}
	}

	/**
	 * Rewrite legacy choice field type slugs in a field list.
	 *
	 * Both rules are resolved against the original type in one pass, so a
	 * `checkboxes` field becomes `checkbox` without then being caught by the
	 * `checkbox` -> `radio` rule.
	 *
	 * This must be applied exactly once per form. A migrated `checkbox` is
	 * indistinguishable from a legacy one, so a second pass would wrongly turn
	 * it into `radio`; the caller is responsible for tracking what it has
	 * already handled.
	 *
	 * @since 1.0.3
	 * @param array $fields Field definitions.
	 * @return array Field definitions with updated types.
	 */
	public static function migrate_field_types( array $fields ) {
		$map = [
			'checkbox'   => 'radio',
			'checkboxes' => 'checkbox',
		];

		foreach ( $fields as $index => $field ) {
			if ( ! isset( $field['type'] ) || ! isset( $map[ $field['type'] ] ) ) {
				continue;
			}

			$fields[ $index ]['type'] = $map[ $field['type'] ];
		}

		return $fields;
	}
}
