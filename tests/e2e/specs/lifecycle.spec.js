const { test, expect } = require( '@playwright/test' );
const { wp, wpEval } = require( '../fixtures/wp-cli' );

/**
 * Covers two flows every other spec skips: the version-gated migration
 * system (src/Database/Installer.php) actually running end-to-end against a
 * real database on a real WordPress bootstrap, and the uninstall routine
 * (src/Uninstall.php) actually dropping - or keeping - real tables, options
 * and files. Both are pure PHP/WP-CLI flows with no browser or admin UI
 * involved, so these tests never touch `page`.
 *
 * Uninstall is triggered by deactivating the plugin and then loading
 * uninstall.php directly with WP_UNINSTALL_PLUGIN defined - the same two
 * steps `wp plugin uninstall --deactivate` performs internally - rather
 * than by running that command itself. `wp plugin uninstall` also deletes
 * the plugin's directory afterwards, and scripts/e2e-env.sh symlinks
 * wp-content/plugins/formtura straight to this repo's working tree (see
 * e2e-env.sh's "Symlinking the plugin from the working tree" step):
 * running it for real would delete the repo.
 *
 * The deactivate step isn't optional set-dressing: leaving the plugin
 * active means its own plugins_loaded hook (Installer::maybe_update(), see
 * Core.php) fires on the very next wp-cli bootstrap, sees the fta_db_version
 * option uninstall.php just deleted, and silently recreates the tables it
 * just dropped - self-healing the uninstall away before a separate process
 * ever gets to assert on it. Found by watching exactly that happen while
 * writing this test: a raw DROP TABLE persisted fine standalone, but the
 * table was back by the next `wp eval-file` call whenever the plugin was
 * still active.
 */

const RUN_UNINSTALL = `
define( 'WP_UNINSTALL_PLUGIN', 'formtura/formtura.php' );
require WP_PLUGIN_DIR . '/formtura/uninstall.php';
`;

test.describe( 'Plugin upgrade migrations', () => {
	test( 'a stale fta_db_version is upgraded, and legacy field types migrated, on the next request', () => {
		// wp eval-file bootstraps WordPress fresh for every call, firing
		// plugins_loaded (and therefore Installer::maybe_update()) each time -
		// so the version has to be made stale in one call and checked in a
		// separate one for the migration to actually be exercised, rather than
		// just asserting on code that never ran.
		const formId = wpEval( `
			$id = fta_create_form( array(
				'title'  => 'E2E Legacy Form',
				'status' => 'active',
				'fields' => array(
					array( 'id' => 'field_choice', 'type' => 'checkboxes', 'label' => 'Pick one' ),
				),
			) );
			update_option( 'fta_db_version', '1.0.2' );
			echo $id;
		` );

		expect( wpEval( `echo get_option( 'fta_db_version' );` ) ).toBe( '1.0.5' );
		expect( wpEval( `echo get_option( 'fta_private_migration_failed' ) ? 'failed' : 'ok';` ) ).toBe( 'ok' );

		const fieldType = wpEval( `echo fta_get_form( ${ formId } )['fields'][0]['type'];` );
		expect( fieldType ).toBe( 'checkbox' );

		wpEval( `fta_delete_form( ${ formId } );` );
	} );
} );

test.describe( 'Uninstall data retention', () => {
	// Whichever test ran (drop or retain), reactivate unconditionally - even
	// if assertions above failed - so specs that run after this file in the
	// same disposable instance still find a working, active plugin. `wp
	// plugin activate` fires the real activation hook (Installer::activate()),
	// which is safe to re-run even when nothing was actually dropped.
	test.afterEach( () => {
		wp( 'plugin activate formtura' );
	} );

	test( 'retains forms, settings and uploaded files by default', () => {
		// Setup needs the plugin's own fta_create_form()/File_Storage helpers,
		// so it has to happen before deactivating - only uninstall.php itself
		// (loaded directly below) is self-contained enough to run without the
		// plugin active.
		const formId = wpEval( `echo fta_create_form( array( 'title' => 'E2E Retain Form' ) );` );
		const vaultDir = wpEval( `
			$storage = new \\Formtura\\Frontend\\File_Storage();
			$dir = $storage->prepare_directory();
			file_put_contents( $dir . '/keep-me.txt', 'keep' );
			echo $storage->get_site_root();
		` );

		wp( 'plugin deactivate formtura' );
		wpEval( RUN_UNINSTALL );
		wp( 'plugin activate formtura' ); // back on before using fta_* helpers to verify.

		expect( wpEval( `global $wpdb; echo $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fta_forms'" );` ) ).toContain( 'fta_forms' );
		expect( wpEval( `echo fta_get_form( ${ formId } ) ? 'kept' : 'gone';` ) ).toBe( 'kept' );
		expect( wpEval( `echo get_option( 'fta_settings' ) ? 'kept' : 'gone';` ) ).toBe( 'kept' );
		expect( wpEval( `echo is_dir( '${ vaultDir }' ) ? 'kept' : 'gone';` ) ).toBe( 'kept' );

		wpEval( `fta_delete_form( ${ formId } );` );
	} );

	test( 'drops tables, options and uploaded files once an admin opts in', () => {
		const formId = wpEval( `echo fta_create_form( array( 'title' => 'E2E Delete Form' ) );` );
		const vaultDir = wpEval( `
			$settings = get_option( 'fta_settings', array() );
			$settings['delete_data_on_uninstall'] = true;
			update_option( 'fta_settings', $settings );

			$storage = new \\Formtura\\Frontend\\File_Storage();
			$dir = $storage->prepare_directory();
			file_put_contents( $dir . '/delete-me.txt', 'delete' );
			echo $storage->get_site_root();
		` );

		wp( 'plugin deactivate formtura' );
		wpEval( RUN_UNINSTALL );

		// No fta_* helper calls here - the tables these rely on are exactly
		// what's being asserted gone, so verification stays on raw wpdb/
		// get_option/is_dir, same as it would need to be after the plugin
		// were truly removed.
		expect( wpEval( `global $wpdb; echo $wpdb->get_var( "SHOW TABLES LIKE '{$wpdb->prefix}fta_forms'" );` ) ).toBe( '' );
		expect( wpEval( `echo get_option( 'fta_settings' ) ? 'kept' : 'gone';` ) ).toBe( 'gone' );
		expect( wpEval( `echo is_dir( '${ vaultDir }' ) ? 'kept' : 'gone';` ) ).toBe( 'gone' );
	} );
} );
