# Privacy Compliance Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Register WordPress Privacy API exporter/eraser callbacks for Formtura entries, add an opt-in automatic entry-retention purge, and disclose data handling (including reCAPTCHA) in `readme.txt`.

**Architecture:** A new `Privacy` class in `src/Admin/` owns all three responsibilities (exporter, eraser, retention cron) and is instantiated unconditionally from `Core::init_components()`. It depends on `Entries_DB` (three new query methods) and `Forms_DB` (existing `get_all()`) through the same constructor-injection pattern already used by `Entry_Export`, so every method is testable with plain PHP fakes and no database.

**Tech Stack:** PHP 7.4+, WordPress Privacy API (`wp_privacy_personal_data_exporters`/`erasers`), WP-Cron, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-16-privacy-compliance-design.md`

## Global Constraints

- Exporter/eraser callbacks must match the exact WP Privacy API return contract: `export_data()` returns `['data' => [...], 'done' => bool]`; `erase_data()` returns `['items_removed' => bool, 'items_retained' => bool, 'messages' => [...], 'done' => bool]`.
- Pagination is 20 entries per page (`Privacy::PAGE_SIZE`).
- `entry_retention_days` defaults to `0` (automatic purge disabled) so existing installs see no behavior change on upgrade.
- All new `Entries_DB` deletion paths go through the existing `Entries_DB::delete( $entry_id )` method — never write new file-deletion code.
- Follow the codebase's existing dependency-injection pattern: constructors accept an optional duck-typed collaborator (`is_object($x) && method_exists($x, 'some_method')`), falling back to `new RealClass()` via a private lazy accessor.
- Every new PHP file starts with the standard file docblock (`@package Formtura`, `@since 1.0.6`) and an `ABSPATH` exit guard, matching every existing file in `src/`.

---

### Task 1: `Entries_DB` privacy query methods

**Files:**
- Modify: `src/Database/Entries_DB.php` (add three public methods after `get_count()`, i.e. after line 476)
- Test: `tests/Unit/Database/EntryPrivacyQueriesTest.php` (create)

**Interfaces:**
- Produces: `Entries_DB::get_ids_by_user( int $user_id ): int[]`, `Entries_DB::get_ids_by_meta_match( int $form_id, string[] $meta_keys, string $value ): int[]`, `Entries_DB::get_ids_older_than( string $cutoff ): int[]`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Database/EntryPrivacyQueriesTest.php`:

```php
<?php
/**
 * Query methods used by the WordPress Privacy API integration to find and
 * age-out entries without loading full rows.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Database\Entries_DB;
use Formtura\Tests\TestCase;

class EntryPrivacyQueriesTest extends TestCase {

	/**
	 * @var object
	 */
	private $wpdbDouble;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb             = $this->makeWpdb();
		$this->wpdbDouble = $wpdb;
	}

	private function makeWpdb() {
		return new class {
			public $prefix     = 'wp_';
			public $queries    = [];
			public $col_result = [];

			public function prepare( $query, ...$args ) {
				foreach ( $args as $arg ) {
					$query = preg_replace( '/%d|%s/', is_int( $arg ) ? (string) $arg : "'" . $arg . "'", $query, 1 );
				}

				return $query;
			}

			public function get_col( $query, $x = 0 ) {
				$this->queries[] = $query;

				return $this->col_result;
			}
		};
	}

	public function test_get_ids_by_user_queries_by_user_id_and_returns_integers() {
		$this->wpdbDouble->col_result = [ '3', '7' ];

		$ids = ( new Entries_DB() )->get_ids_by_user( 42 );

		$this->assertSame( [ 3, 7 ], $ids );
		$this->assertStringContainsString( 'user_id = 42', end( $this->wpdbDouble->queries ) );
	}

	public function test_get_ids_by_meta_match_scopes_to_the_given_form_and_matches_case_insensitively() {
		$this->wpdbDouble->col_result = [ '9' ];

		$ids = ( new Entries_DB() )->get_ids_by_meta_match( 5, [ 'email', 'work_email' ], 'Jane@Example.com' );

		$this->assertSame( [ 9 ], $ids );

		$query = end( $this->wpdbDouble->queries );
		$this->assertStringContainsString( 'form_id = 5', $query );
		$this->assertStringContainsString( "IN ('email','work_email')", $query );
		$this->assertStringContainsString( "LOWER(m.meta_value) = LOWER('Jane@Example.com')", $query );
	}

	/**
	 * A field name in one form must never match an entry belonging to a
	 * different form that happens to reuse the same field name.
	 */
	public function test_get_ids_by_meta_match_is_scoped_per_form() {
		( new Entries_DB() )->get_ids_by_meta_match( 5, [ 'email' ], 'jane@example.com' );

		$this->assertStringContainsString( 'e.form_id = 5', end( $this->wpdbDouble->queries ) );
	}

	public function test_get_ids_by_meta_match_returns_empty_without_querying_when_no_email_fields() {
		$ids = ( new Entries_DB() )->get_ids_by_meta_match( 5, [], 'jane@example.com' );

		$this->assertSame( [], $ids );
		$this->assertSame( [], $this->wpdbDouble->queries );
	}

	public function test_get_ids_older_than_queries_by_created_at_and_returns_integers() {
		$this->wpdbDouble->col_result = [ '101', '102' ];

		$ids = ( new Entries_DB() )->get_ids_older_than( '2026-01-01 00:00:00' );

		$this->assertSame( [ 101, 102 ], $ids );
		$this->assertStringContainsString( "created_at < '2026-01-01 00:00:00'", end( $this->wpdbDouble->queries ) );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Database/EntryPrivacyQueriesTest.php`
Expected: FAIL with "Call to undefined method Formtura\Database\Entries_DB::get_ids_by_user()" (and similarly for the other two methods).

- [ ] **Step 3: Implement the three methods**

In `src/Database/Entries_DB.php`, add after `get_count()` (after line 476, before the `get_entry_meta()` docblock):

```php

	/**
	 * Entry IDs belonging to a WordPress user.
	 *
	 * Used by the Privacy API integration to find entries submitted by a
	 * logged-in requester.
	 *
	 * @since 1.0.6
	 * @param int $user_id WordPress user ID.
	 * @return int[] Entry IDs.
	 */
	public function get_ids_by_user( $user_id ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE user_id = %d", $user_id )
			)
		);
	}

	/**
	 * Entry IDs, scoped to one form, whose meta value under any of the given
	 * keys case-insensitively matches a value.
	 *
	 * Used by the Privacy API integration to find guest entries by an
	 * email-type field's answer. Scoped to a single form so a field name
	 * reused across forms cannot cross-match another form's entries.
	 *
	 * @since 1.0.6
	 * @param int      $form_id   Form ID.
	 * @param string[] $meta_keys Meta keys (field names) to check.
	 * @param string   $value     Value to match, case-insensitively.
	 * @return int[] Entry IDs.
	 */
	public function get_ids_by_meta_match( $form_id, array $meta_keys, $value ) {
		global $wpdb;

		if ( empty( $meta_keys ) ) {
			return [];
		}

		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		$query = $wpdb->prepare(
			"SELECT DISTINCT m.entry_id FROM {$this->meta_table_name} m
			INNER JOIN {$this->table_name} e ON e.id = m.entry_id
			WHERE e.form_id = %d AND m.meta_key IN ({$placeholders}) AND LOWER(m.meta_value) = LOWER(%s)",
			$form_id,
			...$meta_keys,
			$value
		);

		return array_map( 'intval', $wpdb->get_col( $query ) );
	}

	/**
	 * Entry IDs older than a cutoff, across every form.
	 *
	 * Used by the automatic retention purge.
	 *
	 * @since 1.0.6
	 * @param string $cutoff MySQL datetime string.
	 * @return int[] Entry IDs.
	 */
	public function get_ids_older_than( $cutoff ) {
		global $wpdb;

		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare( "SELECT id FROM {$this->table_name} WHERE created_at < %s", $cutoff )
			)
		);
	}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Database/EntryPrivacyQueriesTest.php`
Expected: PASS (5 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Database/Entries_DB.php tests/Unit/Database/EntryPrivacyQueriesTest.php
git commit -m "feat(privacy): add Entries_DB query methods for privacy requests and retention"
```

---

### Task 2: Retention setting (`entry_retention_days`)

**Files:**
- Modify: `src/Admin/Settings.php` (`get_defaults()` at line 155, `sanitize_settings()` at line 95)
- Test: `tests/Unit/Admin/RetentionSettingsTest.php` (create)

**Interfaces:**
- Consumes: nothing new.
- Produces: `fta_settings['entry_retention_days']` — non-negative integer, default `0`. Read later (Task 6) via `fta_get_setting( 'entry_retention_days', 0 )`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Admin/RetentionSettingsTest.php`:

```php
<?php
/**
 * Tests for the entry_retention_days setting.
 *
 * Modeled on UninstallSettingsTest.php: the two failure modes that matter are
 * the sanitizer writing a different key than the settings view posts, and a
 * negative or non-numeric value producing something other than "disabled".
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Settings;
use Formtura\Tests\TestCase;

class RetentionSettingsTest extends TestCase {

	/**
	 * @var Settings
	 */
	private $settings;

	protected function setUp(): void {
		parent::setUp();
		$this->settings = new Settings();
	}

	/**
	 * Call the private sanitize_settings( $settings ).
	 *
	 * @param array $settings Raw settings, as posted by the settings form.
	 * @return array Sanitized settings.
	 */
	private function sanitize( array $settings ) {
		$reflection = new \ReflectionMethod( Settings::class, 'sanitize_settings' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->settings, $settings );
	}

	public function test_default_is_zero_meaning_disabled() {
		$this->assertSame( 0, $this->settings->get_defaults()['entry_retention_days'] );
	}

	public function test_a_posted_value_is_saved_as_an_integer() {
		$this->assertSame( 90, $this->sanitize( [ 'entry_retention_days' => '90' ] )['entry_retention_days'] );
	}

	public function test_a_negative_value_is_clamped_to_zero() {
		$this->assertSame( 0, $this->sanitize( [ 'entry_retention_days' => '-5' ] )['entry_retention_days'] );
	}

	public function test_a_missing_value_is_not_written() {
		$this->assertArrayNotHasKey( 'entry_retention_days', $this->sanitize( [] ) );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Admin/RetentionSettingsTest.php`
Expected: FAIL — `get_defaults()` has no `entry_retention_days` key (undefined array key), sanitize tests fail the same way.

- [ ] **Step 3: Implement**

In `src/Admin/Settings.php`, in `sanitize_settings()` (after the `recaptcha_score_threshold` block, before the `// Currency settings.` comment at line 134):

```php

		// Automatic entry retention. 0 means "never delete automatically" -
		// the only value that must never change an existing install's
		// behavior on upgrade, so it is the default.
		if ( isset( $settings['entry_retention_days'] ) ) {
			$sanitized['entry_retention_days'] = max( 0, absint( $settings['entry_retention_days'] ) );
		}
```

In `get_defaults()` (inside the returned array, after `'currency' => 'USD',` at line 165):

```php
			'entry_retention_days'      => 0,
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Admin/RetentionSettingsTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Admin/Settings.php tests/Unit/Admin/RetentionSettingsTest.php
git commit -m "feat(privacy): add entry_retention_days setting"
```

---

### Task 3: Retention field in the settings view

**Files:**
- Modify: `src/Admin/views/settings.php` (add a table row after the "Delete Data on Uninstall" row, i.e. after line 121)
- Test: `tests/Unit/Admin/SettingsViewRetentionTest.php` (create)

**Interfaces:**
- Consumes: `$settings['entry_retention_days']` (from Task 2).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Admin/SettingsViewRetentionTest.php`:

```php
<?php
/**
 * Settings screen rendering for the entry-retention field.
 *
 * Modeled on SmtpSettingsViewTest.php: the failure mode that matters is the
 * input posting under a different key than Settings::sanitize_settings()
 * reads (see RetentionSettingsTest), which would make the field silently do
 * nothing.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Tests\TestCase;

class SettingsViewRetentionTest extends TestCase {

	/**
	 * @param array $settings As Settings::render() would pass them in.
	 * @return string
	 */
	private function render( array $settings = [] ) {
		ob_start();
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/settings.php';

		return ob_get_clean();
	}

	public function test_the_field_posts_the_key_the_sanitizer_reads() {
		$html = $this->render();

		$this->assertStringContainsString( 'name="settings[entry_retention_days]"', $html );
	}

	public function test_a_saved_value_is_reflected_in_the_field() {
		$html = $this->render( [ 'entry_retention_days' => 90 ] );

		$field = substr( $html, strpos( $html, 'id="fta-entry-retention-days"' ) );
		$field = substr( $field, 0, strpos( $field, '>' ) );

		$this->assertStringContainsString( 'value="90"', $field );
	}

	public function test_a_missing_value_defaults_the_field_to_zero() {
		$html  = $this->render();
		$field = substr( $html, strpos( $html, 'id="fta-entry-retention-days"' ) );
		$field = substr( $field, 0, strpos( $field, '>' ) );

		$this->assertStringContainsString( 'value="0"', $field );
	}

	public function test_the_field_explains_that_zero_disables_automatic_deletion() {
		$this->assertStringContainsString( '0', $this->render() );
		$this->assertStringContainsString( 'disable', strtolower( $this->render() ) );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Admin/SettingsViewRetentionTest.php`
Expected: FAIL — `strpos()` on `false` (the field does not exist yet), or the assertion against an empty substring.

- [ ] **Step 3: Implement**

In `src/Admin/views/settings.php`, add after the "Delete Data on Uninstall" `</tr>` (after line 121):

```php

					<tr>
						<th scope="row">
							<label for="fta-entry-retention-days"><?php esc_html_e( 'Automatically Delete Entries After', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="number"
								id="fta-entry-retention-days"
								name="settings[entry_retention_days]"
								value="<?php echo esc_attr( isset( $settings['entry_retention_days'] ) ? $settings['entry_retention_days'] : 0 ); ?>"
								step="1"
								min="0"
								class="small-text">
							<?php esc_html_e( 'days', FORMTURA_TEXTDOMAIN ); ?>
							<p class="description">
								<?php esc_html_e( 'Entries older than this are deleted automatically, across all forms. Set to 0 to disable automatic deletion.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Admin/SettingsViewRetentionTest.php`
Expected: PASS (4 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Admin/views/settings.php tests/Unit/Admin/SettingsViewRetentionTest.php
git commit -m "feat(privacy): add entry retention field to the settings screen"
```

---

### Task 4: `Privacy` class — exporter

**Files:**
- Create: `src/Admin/Privacy.php`
- Modify: `tests/wp-stubs.php` (add `get_user_by()`, `WP_User`, and filter-recording to `add_filter()`)
- Test: `tests/Unit/Admin/PrivacyTest.php` (create)

**Interfaces:**
- Consumes: `Entries_DB::get_ids_by_user()`, `Entries_DB::get_ids_by_meta_match()`, `Entries_DB::get( $id )` (Task 1 and pre-existing), `Forms_DB::get_all( $args )` (pre-existing), `fta_get_field_name( $field )` (pre-existing, `src/Functions.php:426`).
- Produces: `Privacy::__construct( $entries = null, $forms = null )`; `Privacy::export_data( string $email_address, int $page = 1 ): array`; private `Privacy::matching_entry_ids( string $email ): int[]` (consumed by Task 5).

- [ ] **Step 1: Add test stubs**

In `tests/wp-stubs.php`, add near the other class stubs (after the `WP_Error` block, e.g. after line 72):

```php

if ( ! class_exists( 'WP_User' ) ) {
	/**
	 * Minimal stand-in for WordPress's WP_User — only the properties this
	 * plugin reads.
	 */
	class WP_User {

		/** @var int */
		public $ID;

		/** @var string */
		public $user_email;

		public function __construct( $id, $email ) {
			$this->ID         = $id;
			$this->user_email = $email;
		}
	}
}
```

And near `add_filter` (after line 520):

```php

if ( ! function_exists( 'get_user_by' ) ) {
	/**
	 * Reads from $GLOBALS['fta_test_users'], keyed by lowercase email, so a
	 * test can seed which WordPress accounts exist without a database.
	 */
	function get_user_by( $field, $value ) {
		if ( 'email' !== $field ) {
			return false;
		}

		$users = isset( $GLOBALS['fta_test_users'] ) ? $GLOBALS['fta_test_users'] : [];
		$key   = strtolower( (string) $value );

		return isset( $users[ $key ] ) ? $users[ $key ] : false;
	}
}
```

Replace the existing `add_filter` stub (lines 516-520) with a recording version, mirroring `add_action` immediately above it:

```php
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		// Recorded so tests can assert which filters a component registers,
		// the same way add_action() already records actions.
		if ( isset( $GLOBALS['fta_test_filters'] ) && is_array( $GLOBALS['fta_test_filters'] ) ) {
			$GLOBALS['fta_test_filters'][] = [ $hook, $callback, $priority, $accepted_args ];
		}

		return true;
	}
}
```

- [ ] **Step 2: Write the failing tests**

Create `tests/Unit/Admin/PrivacyTest.php`:

```php
<?php
/**
 * Formtura's WordPress Privacy API integration: finding, exporting, and
 * erasing entries for a requested email address, and purging entries past
 * the configured retention window.
 *
 * Entries aren't tied to a fixed "email" schema field, so matching unions two
 * strategies: the WordPress user account behind a logged-in submission, and
 * any email-type field's answer on a guest submission.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Privacy;
use Formtura\Tests\TestCase;

class PrivacyTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_users'], $GLOBALS['fta_test_options'], $GLOBALS['fta_test_actions'], $GLOBALS['fta_test_filters'] );

		parent::tearDown();
	}

	/**
	 * @return object A fake Entries_DB exposing exactly the methods Privacy
	 *         uses, so no database is involved.
	 */
	private function makeEntries() {
		return new class {
			public $byUser        = [];
			public $byMeta        = [];
			public $olderThan     = [];
			public $store         = [];
			public $deleted       = [];
			public $deleteResult  = true;
			public $metaMatchCalls = [];
			public $olderThanCalls = [];

			public function get_ids_by_user( $user_id ) {
				return isset( $this->byUser[ $user_id ] ) ? $this->byUser[ $user_id ] : [];
			}

			public function get_ids_by_meta_match( $form_id, $meta_keys, $value ) {
				$this->metaMatchCalls[] = [ $form_id, $meta_keys, $value ];
				$key = $form_id . '|' . strtolower( $value );

				return isset( $this->byMeta[ $key ] ) ? $this->byMeta[ $key ] : [];
			}

			public function get_ids_older_than( $cutoff ) {
				$this->olderThanCalls[] = $cutoff;

				return $this->olderThan;
			}

			public function get( $id ) {
				return isset( $this->store[ $id ] ) ? $this->store[ $id ] : null;
			}

			public function delete( $id ) {
				$this->deleted[] = $id;

				return $this->deleteResult;
			}
		};
	}

	/**
	 * @param array $forms Forms in Forms_DB::get_all() shape.
	 * @return object
	 */
	private function makeForms( array $forms ) {
		return new class( $forms ) {
			private $forms;

			public function __construct( $forms ) {
				$this->forms = $forms;
			}

			public function get_all( $args = [] ) {
				return $this->forms;
			}
		};
	}

	public function test_exporter_matches_entries_by_wp_user_account() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$entries = $this->makeEntries();
		$entries->byUser[5]    = [ 101 ];
		$entries->store[101]   = [
			'id'         => 101,
			'form_id'    => 1,
			'created_at' => '2026-01-01 00:00:00',
			'ip_address' => '1.2.3.4',
			'user_agent' => 'UA',
			'data'       => [ 'name' => 'Owner' ],
		];

		$privacy = new Privacy( $entries, $this->makeForms( [] ) );
		$result  = $privacy->export_data( 'owner@example.com', 1 );

		$this->assertCount( 1, $result['data'] );
		$this->assertSame( 'formtura-entry-101', $result['data'][0]['item_id'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_exporter_matches_entries_by_an_email_type_field_case_insensitively() {
		$forms = [
			[
				'id'     => 2,
				'fields' => [
					[ 'type' => 'email', 'name' => 'contact_email' ],
					[ 'type' => 'text', 'name' => 'message' ],
				],
			],
		];

		$entries = $this->makeEntries();
		$entries->byMeta['2|jane@example.com'] = [ 55 ];
		$entries->store[55] = [
			'id'         => 55,
			'form_id'    => 2,
			'created_at' => '2026-01-02 00:00:00',
			'ip_address' => '5.6.7.8',
			'user_agent' => 'UA',
			'data'       => [ 'contact_email' => 'jane@example.com', 'message' => 'Hi' ],
		];

		$privacy = new Privacy( $entries, $this->makeForms( $forms ) );
		$result  = $privacy->export_data( 'JANE@EXAMPLE.COM', 1 );

		$this->assertCount( 1, $result['data'] );
		$this->assertSame( [ 2, [ 'contact_email' ], 'JANE@EXAMPLE.COM' ], $entries->metaMatchCalls[0] );
	}

	public function test_exporter_does_not_match_an_unrelated_email() {
		$forms = [
			[ 'id' => 2, 'fields' => [ [ 'type' => 'email', 'name' => 'contact_email' ] ] ],
		];

		$privacy = new Privacy( $this->makeEntries(), $this->makeForms( $forms ) );
		$result  = $privacy->export_data( 'nobody@example.com', 1 );

		$this->assertSame( [], $result['data'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_exporter_paginates_and_sets_done_on_the_final_page() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$entries = $this->makeEntries();
		$ids     = range( 1, 25 );
		$entries->byUser[5] = $ids;

		foreach ( $ids as $id ) {
			$entries->store[ $id ] = [
				'id'         => $id,
				'form_id'    => 1,
				'created_at' => '2026-01-01 00:00:00',
				'ip_address' => '1.2.3.4',
				'user_agent' => 'UA',
				'data'       => [],
			];
		}

		$privacy = new Privacy( $entries, $this->makeForms( [] ) );

		$page1 = $privacy->export_data( 'owner@example.com', 1 );
		$this->assertCount( 20, $page1['data'] );
		$this->assertFalse( $page1['done'] );

		$page2 = $privacy->export_data( 'owner@example.com', 2 );
		$this->assertCount( 5, $page2['data'] );
		$this->assertTrue( $page2['done'] );
	}

	public function test_constructor_registers_the_wp_privacy_hooks() {
		$GLOBALS['fta_test_actions'] = [];
		$GLOBALS['fta_test_filters'] = [];

		new Privacy( $this->makeEntries(), $this->makeForms( [] ) );

		$filterHooks = array_column( $GLOBALS['fta_test_filters'], 0 );
		$actionHooks = array_column( $GLOBALS['fta_test_actions'], 0 );

		$this->assertContains( 'wp_privacy_personal_data_exporters', $filterHooks );
		$this->assertContains( 'wp_privacy_personal_data_erasers', $filterHooks );
		$this->assertContains( 'fta_purge_old_entries_event', $actionHooks );
	}
}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Admin/PrivacyTest.php`
Expected: FAIL with "Class 'Formtura\Admin\Privacy' not found".

- [ ] **Step 4: Implement `Privacy` (exporter half)**

Create `src/Admin/Privacy.php`:

```php
<?php
/**
 * Privacy Class
 *
 * WordPress Privacy API integration: exports and erases Formtura entries for
 * a requested email address, and purges entries past the configured
 * retention window.
 *
 * @package Formtura
 * @since 1.0.6
 */

namespace Formtura\Admin;

use Formtura\Database\Entries_DB;
use Formtura\Database\Forms_DB;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Privacy class.
 */
class Privacy {

	/**
	 * Entries returned per exporter/eraser page, per the WP Privacy API
	 * convention.
	 */
	const PAGE_SIZE = 20;

	/**
	 * Entries source.
	 *
	 * @var object|null
	 */
	private $entries;

	/**
	 * Forms source.
	 *
	 * @var object|null
	 */
	private $forms;

	/**
	 * Constructor.
	 *
	 * @since 1.0.6
	 * @param object|null $entries Optional entries source. Anything exposing
	 *        get_ids_by_user(); injected by tests.
	 * @param object|null $forms   Optional forms source. Anything exposing
	 *        get_all(); injected by tests.
	 */
	public function __construct( $entries = null, $forms = null ) {
		$this->entries = ( is_object( $entries ) && method_exists( $entries, 'get_ids_by_user' ) ) ? $entries : null;
		$this->forms   = ( is_object( $forms ) && method_exists( $forms, 'get_all' ) ) ? $forms : null;

		$this->init_hooks();
	}

	/**
	 * Register WordPress hooks.
	 *
	 * @since 1.0.6
	 */
	private function init_hooks() {
		add_filter( 'wp_privacy_personal_data_exporters', [ $this, 'register_exporter' ] );
		add_filter( 'wp_privacy_personal_data_erasers', [ $this, 'register_eraser' ] );
		add_action( 'fta_purge_old_entries_event', [ $this, 'purge_old_entries' ] );
	}

	/**
	 * The entries source, created on demand.
	 *
	 * @since 1.0.6
	 * @return object
	 */
	private function entries() {
		if ( null === $this->entries ) {
			$this->entries = new Entries_DB();
		}

		return $this->entries;
	}

	/**
	 * The forms source, created on demand.
	 *
	 * @since 1.0.6
	 * @return object
	 */
	private function forms() {
		if ( null === $this->forms ) {
			$this->forms = new Forms_DB();
		}

		return $this->forms;
	}

	/**
	 * Register Formtura's exporter with WordPress's Privacy Tools.
	 *
	 * @since 1.0.6
	 * @param array $exporters Registered exporters.
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['formtura-entries'] = [
			'exporter_friendly_name' => __( 'Formtura Form Entries', FORMTURA_TEXTDOMAIN ),
			'callback'               => [ $this, 'export_data' ],
		];

		return $exporters;
	}

	/**
	 * Register Formtura's eraser with WordPress's Privacy Tools.
	 *
	 * @since 1.0.6
	 * @param array $erasers Registered erasers.
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['formtura-entries'] = [
			'eraser_friendly_name' => __( 'Formtura Form Entries', FORMTURA_TEXTDOMAIN ),
			'callback'              => [ $this, 'erase_data' ],
		];

		return $erasers;
	}

	/**
	 * Entry IDs (deduplicated) matching a requested email address.
	 *
	 * Unions two strategies: the WordPress user account behind a logged-in
	 * submission, and any email-type field's answer on a guest submission -
	 * entries have no fixed schema, so there is no single reliable column to
	 * match on.
	 *
	 * @since 1.0.6
	 * @param string $email Requested email address.
	 * @return int[] Entry IDs.
	 */
	private function matching_entry_ids( $email ) {
		$email = trim( (string) $email );

		if ( '' === $email ) {
			return [];
		}

		$ids = [];

		$user = get_user_by( 'email', $email );

		if ( $user ) {
			$ids = array_merge( $ids, $this->entries()->get_ids_by_user( $user->ID ) );
		}

		// A high, explicit limit: Forms_DB::get_all()'s default caps at 20,
		// which would silently miss forms past the first page on any site
		// with more than 20 forms.
		foreach ( $this->forms()->get_all( [ 'limit' => 100000 ] ) as $form ) {
			$email_fields = $this->email_field_names( $form );

			if ( empty( $email_fields ) ) {
				continue;
			}

			$ids = array_merge(
				$ids,
				$this->entries()->get_ids_by_meta_match( $form['id'], $email_fields, $email )
			);
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Names (storage keys) of a form's email-type fields.
	 *
	 * @since 1.0.6
	 * @param array $form Form, as Forms_DB returns it.
	 * @return string[]
	 */
	private function email_field_names( $form ) {
		$names = [];

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $names;
		}

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['type'] ) && 'email' === $field['type'] ) {
				$name = fta_get_field_name( $field );

				if ( '' !== $name ) {
					$names[] = $name;
				}
			}
		}

		return $names;
	}

	/**
	 * WP Privacy API exporter callback.
	 *
	 * @since 1.0.6
	 * @param string $email_address Requested email address.
	 * @param int    $page          1-indexed page number.
	 * @return array{data: array[], done: bool}
	 */
	public function export_data( $email_address, $page = 1 ) {
		$page = max( 1, (int) $page );
		$ids  = $this->matching_entry_ids( $email_address );

		$offset   = ( $page - 1 ) * self::PAGE_SIZE;
		$page_ids = array_slice( $ids, $offset, self::PAGE_SIZE );

		$items = [];

		foreach ( $page_ids as $entry_id ) {
			$entry = $this->entries()->get( $entry_id );

			if ( $entry ) {
				$items[] = $this->export_item( $entry );
			}
		}

		return [
			'data' => $items,
			'done' => ( $offset + count( $page_ids ) ) >= count( $ids ),
		];
	}

	/**
	 * Build one entry's export item.
	 *
	 * @since 1.0.6
	 * @param array $entry Entry, as Entries_DB::get() returns it.
	 * @return array
	 */
	private function export_item( $entry ) {
		$data = [
			[ 'name' => __( 'Submitted', FORMTURA_TEXTDOMAIN ), 'value' => isset( $entry['created_at'] ) ? $entry['created_at'] : '' ],
			[ 'name' => __( 'IP Address', FORMTURA_TEXTDOMAIN ), 'value' => isset( $entry['ip_address'] ) ? $entry['ip_address'] : '' ],
			[ 'name' => __( 'User Agent', FORMTURA_TEXTDOMAIN ), 'value' => isset( $entry['user_agent'] ) ? $entry['user_agent'] : '' ],
		];

		$answers = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : [];

		foreach ( $answers as $key => $value ) {
			$data[] = [
				'name'  => (string) $key,
				'value' => is_array( $value ) ? wp_json_encode( $value ) : (string) $value,
			];
		}

		return [
			'group_id'    => 'formtura-entries',
			'group_label' => __( 'Form Entries', FORMTURA_TEXTDOMAIN ),
			'item_id'     => 'formtura-entry-' . $entry['id'],
			'data'        => $data,
		];
	}
}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Admin/PrivacyTest.php`
Expected: PASS (5 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Admin/Privacy.php tests/Unit/Admin/PrivacyTest.php tests/wp-stubs.php
git commit -m "feat(privacy): register WP Privacy API exporter for Formtura entries"
```

---

### Task 5: `Privacy` class — eraser

**Files:**
- Modify: `src/Admin/Privacy.php` (add `erase_data()` after `export_item()`)
- Modify: `tests/Unit/Admin/PrivacyTest.php` (add eraser tests)

**Interfaces:**
- Consumes: `Privacy::matching_entry_ids()` (Task 4, private, same class), `Entries_DB::delete( $id ): bool` (pre-existing).
- Produces: `Privacy::erase_data( string $email_address, int $page = 1 ): array`.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Admin/PrivacyTest.php` (inside the class, after `test_constructor_registers_the_wp_privacy_hooks`):

```php

	public function test_eraser_deletes_matched_entries_and_reports_items_removed() {
		$GLOBALS['fta_test_users']['owner@example.com'] = new \WP_User( 5, 'owner@example.com' );

		$entries = $this->makeEntries();
		$entries->byUser[5]  = [ 101, 102 ];
		$entries->store[101] = [ 'id' => 101, 'form_id' => 1, 'data' => [] ];
		$entries->store[102] = [ 'id' => 102, 'form_id' => 1, 'data' => [] ];

		$privacy = new Privacy( $entries, $this->makeForms( [] ) );
		$result  = $privacy->erase_data( 'owner@example.com', 1 );

		$this->assertSame( [ 101, 102 ], $entries->deleted );
		$this->assertTrue( $result['items_removed'] );
		$this->assertFalse( $result['items_retained'] );
		$this->assertTrue( $result['done'] );
	}

	public function test_eraser_is_a_no_op_when_nothing_matches() {
		$privacy = new Privacy( $this->makeEntries(), $this->makeForms( [] ) );
		$result  = $privacy->erase_data( 'nobody@example.com', 1 );

		$this->assertSame( [], $result['messages'] );
		$this->assertFalse( $result['items_removed'] );
		$this->assertTrue( $result['done'] );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Admin/PrivacyTest.php`
Expected: FAIL with "Call to undefined method Formtura\Admin\Privacy::erase_data()".

- [ ] **Step 3: Implement**

In `src/Admin/Privacy.php`, add after `export_item()` (and before the closing class brace):

```php

	/**
	 * WP Privacy API eraser callback.
	 *
	 * Deletion goes through Entries_DB::delete(), which already removes the
	 * entry row, its meta, and any uploaded files or signatures - no
	 * separate file-cleanup step is needed here.
	 *
	 * @since 1.0.6
	 * @param string $email_address Requested email address.
	 * @param int    $page          1-indexed page number.
	 * @return array{items_removed: bool, items_retained: bool, messages: string[], done: bool}
	 */
	public function erase_data( $email_address, $page = 1 ) {
		$page = max( 1, (int) $page );
		$ids  = $this->matching_entry_ids( $email_address );

		$offset   = ( $page - 1 ) * self::PAGE_SIZE;
		$page_ids = array_slice( $ids, $offset, self::PAGE_SIZE );

		$removed = 0;

		foreach ( $page_ids as $entry_id ) {
			if ( $this->entries()->delete( $entry_id ) ) {
				$removed++;
			}
		}

		return [
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => $removed > 0
				? [ sprintf( __( '%d form entries removed.', FORMTURA_TEXTDOMAIN ), $removed ) ]
				: [],
			'done'           => ( $offset + count( $page_ids ) ) >= count( $ids ),
		];
	}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Admin/PrivacyTest.php`
Expected: PASS (7 tests).

- [ ] **Step 5: Commit**

```bash
git add src/Admin/Privacy.php tests/Unit/Admin/PrivacyTest.php
git commit -m "feat(privacy): register WP Privacy API eraser for Formtura entries"
```

---

### Task 6: `Privacy` class — retention purge

**Files:**
- Modify: `src/Admin/Privacy.php` (add `purge_old_entries()`)
- Modify: `tests/wp-stubs.php` (add `DAY_IN_SECONDS`)
- Modify: `tests/Unit/Admin/PrivacyTest.php` (add purge tests)

**Interfaces:**
- Consumes: `fta_get_setting( 'entry_retention_days', 0 )` (pre-existing, `src/Functions.php:147`, backed by Task 2's setting), `Entries_DB::get_ids_older_than()` (Task 1), `Entries_DB::delete()` (pre-existing).
- Produces: `Privacy::purge_old_entries(): void` — the callback registered in Task 4's `init_hooks()` for `fta_purge_old_entries_event`.

- [ ] **Step 1: Add the `DAY_IN_SECONDS` stub**

In `tests/wp-stubs.php`, add near the other constant definitions at the top (after the `ARRAY_N` block, e.g. after line 24):

```php

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
```

- [ ] **Step 2: Write the failing tests**

Add to `tests/Unit/Admin/PrivacyTest.php` (after the eraser tests):

```php

	public function test_purge_is_a_no_op_when_retention_is_disabled() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'entry_retention_days' => 0 ];

		$entries = $this->makeEntries();
		( new Privacy( $entries, $this->makeForms( [] ) ) )->purge_old_entries();

		$this->assertSame( [], $entries->olderThanCalls );
		$this->assertSame( [], $entries->deleted );
	}

	public function test_purge_deletes_entries_older_than_the_configured_window() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'entry_retention_days' => 30 ];

		$entries = $this->makeEntries();
		$entries->olderThan = [ 201, 202 ];

		$before = time();
		( new Privacy( $entries, $this->makeForms( [] ) ) )->purge_old_entries();
		$after  = time();

		$this->assertSame( [ 201, 202 ], $entries->deleted );

		$cutoff = strtotime( $entries->olderThanCalls[0] . ' UTC' );
		$this->assertGreaterThanOrEqual( $before - ( 30 * DAY_IN_SECONDS ) - 2, $cutoff );
		$this->assertLessThanOrEqual( $after - ( 30 * DAY_IN_SECONDS ) + 2, $cutoff );
	}
```

- [ ] **Step 3: Run tests to verify they fail**

Run: `vendor/bin/phpunit tests/Unit/Admin/PrivacyTest.php`
Expected: FAIL with "Call to undefined method Formtura\Admin\Privacy::purge_old_entries()".

- [ ] **Step 4: Implement**

In `src/Admin/Privacy.php`, add after `erase_data()` (and before the closing class brace):

```php

	/**
	 * Delete every entry, across all forms, older than the configured
	 * retention window. A no-op when retention is disabled (the default).
	 *
	 * Registered against the daily fta_purge_old_entries_event cron action
	 * (scheduled in formtura.php on plugin activation). The event stays
	 * scheduled regardless of whether retention is currently enabled - this
	 * check is what makes re-enabling it later require no re-scheduling.
	 *
	 * @since 1.0.6
	 */
	public function purge_old_entries() {
		$days = (int) fta_get_setting( 'entry_retention_days', 0 );

		if ( $days <= 0 ) {
			return;
		}

		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		foreach ( $this->entries()->get_ids_older_than( $cutoff ) as $entry_id ) {
			$this->entries()->delete( $entry_id );
		}
	}
```

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit tests/Unit/Admin/PrivacyTest.php`
Expected: PASS (9 tests).

- [ ] **Step 6: Commit**

```bash
git add src/Admin/Privacy.php tests/Unit/Admin/PrivacyTest.php tests/wp-stubs.php
git commit -m "feat(privacy): add automatic entry-retention purge"
```

---

### Task 7: Wire `Privacy` into the plugin

**Files:**
- Modify: `src/Core.php` (`init_components()`, after line 121's `new Blocks\Form_Selector();`)
- Modify: `formtura.php` (`fta_activate()` at lines 85-93, `fta_deactivate()` at lines 101-104)

**Interfaces:**
- Consumes: `Privacy::__construct()` (Task 4-6, no-arg form uses real `Entries_DB`/`Forms_DB`).

There is no PHPUnit coverage for this task: `formtura.php` registers its activation/deactivation callbacks and calls `Core::instance()` at the `plugins_loaded` hook, none of which the test bootstrap (`tests/bootstrap.php`) loads or fires — the same reason no existing test exercises `fta_activate()`/`fta_deactivate()` or `Core::init_components()`. Verify this task with the manual steps below instead.

- [ ] **Step 1: Instantiate `Privacy` unconditionally**

In `src/Core.php`, in `init_components()`, add after `new Blocks\Form_Selector();` (after line 121, before `// Initialize integrations.`):

```php

		// Registers WP Privacy API exporter/eraser hooks and the retention
		// purge cron callback - must run in both admin and frontend
		// contexts, like Frontend\Submission above.
		new Admin\Privacy();
```

- [ ] **Step 2: Schedule and clear the retention cron event**

In `formtura.php`, in `fta_activate()` (after the `Installer::activate()` block, before `flush_rewrite_rules();` at line 92):

```php

	// Schedule the daily retention purge. The callback itself is a no-op
	// when entry_retention_days is 0 (the default), so scheduling it
	// unconditionally needs no coordination with the setting's value.
	if ( ! wp_next_scheduled( 'fta_purge_old_entries_event' ) ) {
		wp_schedule_event( time(), 'daily', 'fta_purge_old_entries_event' );
	}
```

In `fta_deactivate()` (before `flush_rewrite_rules();` at line 103):

```php

	// Stop the retention purge from firing while the plugin is inactive.
	wp_clear_scheduled_hook( 'fta_purge_old_entries_event' );
```

- [ ] **Step 3: Manual verification**

On a local WordPress install with the plugin active:

1. Deactivate and reactivate the plugin; confirm `wp cron event list` (WP-CLI) or a debug plugin shows `fta_purge_old_entries_event` scheduled daily.
2. Deactivate the plugin; confirm the event is gone from the schedule.
3. Reactivate; go to Tools → Export Personal Data, request an export for an email address that matches an existing entry (either the submitter's WP account email, or an email-type field's answer); confirm the request completes and the download includes a "Form Entries" group with that entry's data.
4. Go to Tools → Erase Personal Data for the same email; confirm the entry is deleted from Formtura → Entries afterward.

- [ ] **Step 4: Commit**

```bash
git add src/Core.php formtura.php
git commit -m "feat(privacy): wire Privacy class into plugin bootstrap and cron lifecycle"
```

---

### Task 8: `readme.txt` privacy disclosure

**Files:**
- Modify: `readme.txt` (insert a new `== Privacy ==` section after `== Frequently Asked Questions ==`, i.e. after line 98, before `== Screenshots ==` at line 100)

**Interfaces:** None — content only.

- [ ] **Step 1: Write the section**

In `readme.txt`, insert after line 98 (the last FAQ answer, "Yes, you can export entries to CSV format from the Entries page.") and before `== Screenshots ==`:

```
== Privacy ==

Formtura stores the data visitors submit through your forms: the answers to whatever fields you add (which may include personal information depending on how you build your forms), uploaded files, signature images, the visitor's IP address, their browser's user agent string, and - for logged-in visitors - their WordPress user ID.

This data is stored only in your site's own database and file storage. Formtura does not transmit it off your site. It is retained indefinitely by default; you can delete individual entries or a form's entries at any time from Formtura > Entries, or turn on automatic deletion under Formtura > Settings ("Automatically Delete Entries After").

Formtura supports WordPress's built-in personal data tools (Tools > Export Personal Data and Tools > Erase Personal Data), so a request for a visitor's data will include matching form entries, and an erasure request will delete them.

If you enable reCAPTCHA (Formtura > Settings), submitting a protected form sends the visitor's reCAPTCHA response token and IP address to Google's reCAPTCHA verification service. This is governed by Google's Privacy Policy (https://policies.google.com/privacy) and Terms of Service (https://policies.google.com/terms).

```

- [ ] **Step 2: Verify placement**

Run: `grep -n "^== " readme.txt`
Expected: `== Privacy ==` appears between `== Frequently Asked Questions ==` and `== Screenshots ==`, and every other heading is unchanged.

- [ ] **Step 3: Commit**

```bash
git add readme.txt
git commit -m "docs(privacy): disclose data retention and reCAPTCHA data sharing in readme"
```

---

### Task 9: Final verification

**Files:** None — verification only.

- [ ] **Step 1: Run the full PHPUnit suite**

Run: `vendor/bin/phpunit`
Expected: All tests pass, including the new `EntryPrivacyQueriesTest`, `RetentionSettingsTest`, `SettingsViewRetentionTest`, and `PrivacyTest` suites, and every pre-existing test still passes.

- [ ] **Step 2: PHP syntax check over every changed file**

Run: `for f in src/Database/Entries_DB.php src/Admin/Settings.php src/Admin/views/settings.php src/Admin/Privacy.php src/Core.php formtura.php tests/wp-stubs.php tests/Unit/Database/EntryPrivacyQueriesTest.php tests/Unit/Admin/RetentionSettingsTest.php tests/Unit/Admin/SettingsViewRetentionTest.php tests/Unit/Admin/PrivacyTest.php; do php -l "$f"; done`
Expected: "No syntax errors detected" for every file.

- [ ] **Step 3: Confirm no unrelated files changed**

Run: `git status`
Expected: Only the files touched by this plan are modified/new; no pre-existing unrelated working-tree changes were altered.
