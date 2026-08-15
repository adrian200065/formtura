# Production Blockers 1-3 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make uninstall retention safe, build a complete release ZIP, and make every stored upload/signature accessible only to WordPress administrators with complete lifecycle cleanup.

**Architecture:** A canonical `delete_data_on_uninstall` option controls all destructive uninstall work. A focused `File_Storage` service owns private paths, record resolution, migration, and deletion; `File_Download` provides the only browser access route. A temporary-workspace release script builds dependencies and assets before copying a runtime-only package.

**Tech Stack:** PHP 7.4+, WordPress 5.8+, PHPUnit 9, Composer, Bash, pnpm 10, Vite 5, GitHub Actions.

**Spec:** `docs/superpowers/specs/2026-08-15-production-blockers-1-3-design.md`

## Global Constraints

- `delete_data_on_uninstall` is the only canonical uninstall preference and defaults to `false`.
- File records contain a vault-relative `path`, never a public URL or new absolute path.
- Downloads require `manage_options`; no `admin_post_nopriv_*` route is registered.
- New file storage fails closed when the private vault is unavailable.
- Existing `attachToEmail` behavior remains supported with resolved private paths.
- Cleanup never removes a path outside the private vault or recognized legacy Formtura upload root.
- Preserve the user's pre-existing `builder/styles/workspace.css` modification.
- Each production behavior is preceded by a failing test and a verified red-green cycle.

---

### Task 1: Canonical uninstall preference

**Files:**
- Create: `src/Uninstall.php`
- Create: `tests/Unit/Admin/UninstallSettingsTest.php`
- Create: `tests/Unit/UninstallTest.php`
- Modify: `src/Admin/Settings.php:75-150`
- Modify: `src/Admin/views/settings.php:104-119`
- Modify: `src/Database/Installer.php:120-138`
- Modify: `uninstall.php:11-61`

**Interfaces:**
- Produces: `Formtura\Uninstall::run(): void`, the single destructive cleanup entry point.
- Consumes later: `Uninstall::run()` will call `File_Storage::remove_site_files()` after Task 5 adds it.

- [ ] **Step 1: Write failing sanitizer tests**

Add a reflection helper and these cases to `UninstallSettingsTest.php`:

```php
public function test_checked_delete_setting_is_saved_as_true() {
	$this->assertTrue( $this->sanitize( [ 'delete_data_on_uninstall' => '1' ] )['delete_data_on_uninstall'] );
}

public function test_missing_delete_setting_is_saved_as_false() {
	$this->assertFalse( $this->sanitize( [] )['delete_data_on_uninstall'] );
}
```

The production change caught by these tests is accepting the wrong key or retaining a stale destructive value.

- [ ] **Step 2: Run the sanitizer tests and verify RED**

Run: `vendor/bin/phpunit tests/Unit/Admin/UninstallSettingsTest.php --do-not-cache-result`

Expected: FAIL because `delete_data_on_uninstall` is absent from sanitized output.

- [ ] **Step 3: Implement the canonical setting**

In `Settings::sanitize_settings()` always assign:

```php
$sanitized['delete_data_on_uninstall'] = ! empty( $settings['delete_data_on_uninstall'] );
```

Use the same key in `Settings::get_defaults()`, the installer defaults, and the settings view. Remove the legacy `keep_data_on_uninstall` default.

- [ ] **Step 4: Run the sanitizer tests and verify GREEN**

Run: `vendor/bin/phpunit tests/Unit/Admin/UninstallSettingsTest.php --do-not-cache-result`

Expected: 2 tests pass.

- [ ] **Step 5: Write failing uninstall retention tests**

Load `Formtura\Uninstall` with an in-memory option stub and recording `$wpdb`. Add:

```php
public function test_missing_setting_retains_all_data() {
	$GLOBALS['fta_test_options']['fta_settings'] = [];
	Uninstall::run();
	$this->assertSame( [], $GLOBALS['fta_test_dropped_tables'] );
	$this->assertSame( [], $GLOBALS['fta_test_deleted_options'] );
}

public function test_true_setting_deletes_formtura_data() {
	$GLOBALS['fta_test_options']['fta_settings'] = [ 'delete_data_on_uninstall' => true ];
	Uninstall::run();
	$this->assertSame( [ 'wp_fta_forms', 'wp_fta_entries', 'wp_fta_entry_meta' ], $GLOBALS['fta_test_dropped_tables'] );
	$this->assertContains( 'fta_settings', $GLOBALS['fta_test_deleted_options'] );
}
```

The production change caught is any default-destructive branch or read from the obsolete standalone option.

- [ ] **Step 6: Run uninstall tests and verify RED**

Run: `vendor/bin/phpunit tests/Unit/UninstallTest.php --do-not-cache-result`

Expected: FAIL because `Formtura\Uninstall` does not exist.

- [ ] **Step 7: Implement `Uninstall::run()` and thin bootstrap**

Implement the guard first:

```php
$settings = get_option( 'fta_settings', [] );
if ( empty( $settings['delete_data_on_uninstall'] ) ) {
	return;
}
```

Then delete only the three plugin tables and known plugin options. Change `uninstall.php` to load `vendor/autoload.php`, call `Formtura\Uninstall::run()`, and do nothing if the autoloader is unavailable.

- [ ] **Step 8: Run uninstall tests and the existing suite**

Run: `vendor/bin/phpunit tests/Unit/Admin/UninstallSettingsTest.php tests/Unit/UninstallTest.php --do-not-cache-result`

Expected: all targeted tests pass.

- [ ] **Step 9: Commit Task 1**

```bash
git add src/Uninstall.php src/Admin/Settings.php src/Admin/views/settings.php src/Database/Installer.php uninstall.php tests/Unit/Admin/UninstallSettingsTest.php tests/Unit/UninstallTest.php
git commit -m "Fix uninstall data retention contract"
```

---

### Task 2: Release artifact builder and bootstrap guard

**Files:**
- Create: `.distignore`
- Create: `scripts/build-release.sh`
- Create: `scripts/verify-release.sh`
- Create: `.github/workflows/release.yml`
- Modify: `.gitignore`
- Modify: `formtura.php:28-52`
- Modify: `package.json:10-25`

**Interfaces:**
- Produces: `scripts/build-release.sh [output-directory]`, generating `formtura-<FORMTURA_VERSION>.zip`.
- Produces: `scripts/verify-release.sh <zip-path>`, returning nonzero for incomplete or development-contaminated packages.

- [ ] **Step 1: Write the failing release verification script**

Create `scripts/verify-release.sh` so it extracts a supplied ZIP to `mktemp -d`, verifies these paths, and rejects the forbidden paths:

```bash
required=(formtura/formtura.php formtura/vendor/autoload.php formtura/assets/js/builder.js formtura/assets/css/builder.css formtura/src formtura/templates)
forbidden=(formtura/node_modules formtura/tests formtura/builder formtura/.git)
```

The production change caught is publishing a source archive without runtime dependencies/assets or with development-only content.

- [ ] **Step 2: Verify RED against a tracked-source ZIP**

Run:

```bash
tmp_zip="$(mktemp --suffix=.zip)"
git archive --format=zip --prefix=formtura/ HEAD > "$tmp_zip"
scripts/verify-release.sh "$tmp_zip"
```

Expected: nonzero exit reporting missing `vendor/autoload.php` and builder assets.

- [ ] **Step 3: Implement the isolated release build**

`scripts/build-release.sh` must use `set -euo pipefail`, `mktemp -d`, a cleanup trap, and command checks for `composer`, `pnpm`, `rsync`, and `zip`. Copy the repository into a temporary workspace excluding `.git`, `node_modules`, `vendor`, and `dist`; run:

```bash
composer install --no-dev --classmap-authoritative --no-interaction
pnpm install --frozen-lockfile
pnpm run build
```

Copy the runtime tree through `.distignore`, create the versioned ZIP, and invoke `scripts/verify-release.sh` before moving it to `dist/` or the caller-specified output directory.

- [ ] **Step 4: Guard the missing-autoloader bootstrap**

Change `formtura.php` to return before registering runtime hooks when `vendor/autoload.php` is missing, and register an admin notice that tells developers to install dependencies or use an official release package. Never call `Formtura\Core::instance()` in that state.

- [ ] **Step 5: Add GitHub Actions packaging**

The workflow runs on pull requests, `v*` tags, and manual dispatch. Use PHP 7.4 for package compatibility, Node 18, pnpm from the `packageManager` field, Composer caching, PHPUnit, Jest, `scripts/build-release.sh`, and artifact upload. Only tag/manual runs publish the ZIP as a workflow artifact; PRs still execute and verify the build.

- [ ] **Step 6: Build and verify GREEN**

Run: `scripts/build-release.sh /tmp/formtura-release`

Then run: `scripts/verify-release.sh /tmp/formtura-release/formtura-1.0.4.zip`

Expected: both exit 0; all required runtime files exist; all forbidden paths are absent.

- [ ] **Step 7: Commit Task 2**

```bash
git add .distignore .github/workflows/release.yml .gitignore formtura.php package.json scripts/build-release.sh scripts/verify-release.sh
git commit -m "Add deterministic release package workflow"
```

---

### Task 3: Private storage service

**Files:**
- Create: `src/Frontend/File_Storage.php`
- Create: `tests/Unit/Frontend/FileStorageTest.php`
- Modify: `tests/wp-stubs.php`

**Interfaces:**
- Produces: `File_Storage::get_root(): string`, `get_site_root(): string`, `relative_path(string $absolute): string|false`, `resolve(array $record): string|false`, `create_record(string $name, string $absolute, string $type, int $size): array`, `delete_records(array $entry_data): bool`, `remove_site_files(): bool`, and `migrate_legacy_files(): bool`.
- Consumes: `FORMTURA_PRIVATE_UPLOAD_DIR` when defined; otherwise derives a stable directory beside `ABSPATH`.

- [ ] **Step 1: Write path-containment and record tests**

Add tests using a temporary override root:

```php
public function test_record_contains_relative_path_and_no_public_location() {
	$path = $this->storage->get_site_root() . '/2026/08/random.png';
	$this->writeFile( $path, 'png' );
	$record = $this->storage->create_record( 'signature.png', $path, 'image/png', 3 );
	$this->assertSame( '2026/08/random.png', $record['path'] );
	$this->assertArrayNotHasKey( 'url', $record );
	$this->assertArrayNotHasKey( 'file', $record );
}

public function test_resolver_rejects_path_traversal() {
	$this->assertFalse( $this->storage->resolve( [ 'path' => '../outside.txt' ] ) );
}
```

The production change caught is exposing public/absolute locations or resolving outside the vault.

- [ ] **Step 2: Run and verify RED**

Run: `vendor/bin/phpunit tests/Unit/Frontend/FileStorageTest.php --do-not-cache-result`

Expected: FAIL because `File_Storage` does not exist.

- [ ] **Step 3: Implement private-root and containment primitives**

Derive the default base with:

```php
$wordpress_root = rtrim( wp_normalize_path( ABSPATH ), '/' );
$root = dirname( $wordpress_root ) . '/.formtura-private-' . substr( hash( 'sha256', $wordpress_root ), 0, 12 );
```

Append `site-<get_current_blog_id()>`. Normalize separators, reject null bytes, reject absolute record paths, and require every resolved path to begin with the normalized site-root prefix plus `/`.

- [ ] **Step 4: Implement record creation, legacy resolution, and guarded deletion**

`create_record()` returns exactly `name`, `path`, `type`, and `size`. `resolve()` accepts new `path` records and legacy `file` records only when the legacy path is beneath `wp_upload_dir()['basedir'] . '/formtura'`; map that legacy relative suffix into the private site root when the source no longer exists.

`delete_records()` walks nested entry values, resolves only recognized records, and calls `wp_delete_file()` only for validated files.

- [ ] **Step 5: Run and verify GREEN**

Run: `vendor/bin/phpunit tests/Unit/Frontend/FileStorageTest.php --do-not-cache-result`

Expected: all storage tests pass.

- [ ] **Step 6: Commit Task 3**

```bash
git add src/Frontend/File_Storage.php tests/Unit/Frontend/FileStorageTest.php tests/wp-stubs.php
git commit -m "Add private file storage service"
```

---

### Task 4: Store uploads and signatures privately

**Files:**
- Modify: `src/Frontend/Uploads.php`
- Modify: `src/Frontend/Signature.php`
- Modify: `tests/Unit/Frontend/UploadsTest.php`
- Modify: `tests/Unit/Frontend/SignatureTest.php`
- Modify: `tests/Unit/Frontend/SubmissionFileCleanupTest.php`
- Modify: `tests/Unit/Frontend/SubmissionEntryFailureCleanupTest.php`

**Interfaces:**
- Consumes: `File_Storage::get_site_root()`, `create_record()`, `resolve()`, and `delete_records()` from Task 3.
- Preserves: `Uploads::cleanup(array $results): void` as a compatibility wrapper over the storage service.

- [ ] **Step 1: Write failing private-record tests**

Update upload/signature success assertions:

```php
$this->assertArrayHasKey( 'path', $record );
$this->assertArrayNotHasKey( 'url', $record );
$this->assertArrayNotHasKey( 'file', $record );
$this->assertStringStartsNotWith( '/', $record['path'] );
```

Add a partial multi-file failure test whose first file stores successfully and whose second file returns `WP_Error`; assert no file remains in the vault.

- [ ] **Step 2: Run and verify RED**

Run: `vendor/bin/phpunit tests/Unit/Frontend/UploadsTest.php tests/Unit/Frontend/SignatureTest.php --do-not-cache-result`

Expected: FAIL because stored records still expose `file` and `url` and partial current-field files are omitted from cleanup.

- [ ] **Step 3: Route WordPress upload handling into the vault**

Inject an optional `File_Storage` into `Uploads`. Its `upload_dir` filter sets `basedir` and `path` to the private site root/year/month and sets URL fields to empty strings. After `wp_handle_upload()`, create the entry record through `File_Storage::create_record()` and ignore `$moved['url']`.

When a later file in the same field fails, merge `$stored` into the cleanup input before returning `WP_Error`.

- [ ] **Step 4: Store signatures through the same service**

Inject `File_Storage` into `Signature`, allocate its date-partitioned directory, write the PNG, and return `create_record( 'signature.png', ... )`. Do not invoke the old public upload-directory protector for new files.

- [ ] **Step 5: Update cleanup and attachment resolution**

Make `Uploads::cleanup()` call `File_Storage::delete_records()`. Make `get_email_attachments()` resolve each opted-in record through `File_Storage::resolve()` and include only existing files.

- [ ] **Step 6: Run targeted and cross-field tests GREEN**

Run: `vendor/bin/phpunit tests/Unit/Frontend/UploadsTest.php tests/Unit/Frontend/SignatureTest.php tests/Unit/Frontend/SubmissionFileCleanupTest.php tests/Unit/Frontend/SubmissionEntryFailureCleanupTest.php --do-not-cache-result`

Expected: all targeted tests pass and temporary vault directories are removed in teardown.

- [ ] **Step 7: Commit Task 4**

```bash
git add src/Frontend/Uploads.php src/Frontend/Signature.php tests/Unit/Frontend/UploadsTest.php tests/Unit/Frontend/SignatureTest.php tests/Unit/Frontend/SubmissionFileCleanupTest.php tests/Unit/Frontend/SubmissionEntryFailureCleanupTest.php
git commit -m "Store submitted files in private vault"
```

---

### Task 5: Administrator-only downloads and notifications

**Files:**
- Create: `src/Frontend/File_Download.php`
- Create: `tests/Unit/Frontend/FileDownloadTest.php`
- Create: `tests/Unit/Frontend/NotificationsFileLinkTest.php`
- Modify: `src/Core.php:90-120`
- Modify: `src/Frontend/Notifications.php:45-190`
- Modify: `tests/wp-stubs.php`

**Interfaces:**
- Produces: `File_Download::handle(): void` on `admin_post_fta_download_file`.
- Produces: `File_Download::url(int $entry_id, string $field, int $index): string`.
- Consumes: entry metadata from `fta_get_entry()` and file resolution from `File_Storage`.

- [ ] **Step 1: Write failing authorization and ownership tests**

Add cases that seed a real temporary vault record:

```php
public function test_user_without_manage_options_cannot_download() {
	$GLOBALS['fta_test_current_user_can'] = false;
	$this->expectException( \FTA_Test_Wp_Die::class );
	$this->download->handle();
}

public function test_admin_can_only_download_record_owned_by_requested_entry() {
	$GLOBALS['fta_test_current_user_can'] = true;
	$GLOBALS['fta_test_entries'][8] = [ 'data' => [ 'resume' => [ $this->record ] ] ];
	$_GET = [ 'entry_id' => 8, 'field' => 'resume', 'file' => 0 ];
	$this->assertSame( 'payload', $this->captureDownload() );
}
```

Also request a missing field/index and assert the same generic error. The production changes caught are registering anonymous access, trusting a supplied path, or skipping entry ownership.

- [ ] **Step 2: Run and verify RED**

Run: `vendor/bin/phpunit tests/Unit/Frontend/FileDownloadTest.php --do-not-cache-result`

Expected: FAIL because `File_Download` does not exist.

- [ ] **Step 3: Implement the authenticated controller**

Register only:

```php
add_action( 'admin_post_fta_download_file', [ $this, 'handle' ] );
```

Require `manage_options`, parse `entry_id`, sanitize `field`, and `absint` the file index. Load the entry, select the record, resolve it, and stream using `Content-Type`, `Content-Disposition: attachment`, `Content-Length`, `X-Content-Type-Options: nosniff`, and WordPress no-cache headers. Never accept a path parameter.

- [ ] **Step 4: Write failing notification-link test**

Invoke notification smart-tag formatting with entry ID 8 and field `resume`; assert the rendered link contains `admin-post.php?action=fta_download_file&entry_id=8&field=resume&file=0` and does not contain the legacy public URL.

- [ ] **Step 5: Pass entry context through notifications**

Change `send_notification`, `parse_smart_tags`, and `format_value` to receive the entry ID and field name. When a value is a recognized file record, call `File_Download::url()` and render the escaped original name. Register `File_Download` from `Core::init_components()`.

- [ ] **Step 6: Run download and notification tests GREEN**

Run: `vendor/bin/phpunit tests/Unit/Frontend/FileDownloadTest.php tests/Unit/Frontend/NotificationsFileLinkTest.php --do-not-cache-result`

Expected: all authorization, ownership, and link tests pass.

- [ ] **Step 7: Commit Task 5**

```bash
git add src/Frontend/File_Download.php src/Frontend/Notifications.php src/Core.php tests/Unit/Frontend/FileDownloadTest.php tests/Unit/Frontend/NotificationsFileLinkTest.php tests/wp-stubs.php
git commit -m "Add administrator-only file downloads"
```

---

### Task 6: Entry, form, migration, and uninstall cleanup

**Files:**
- Create: `tests/Unit/Database/EntryFileCleanupTest.php`
- Create: `tests/Unit/Database/FormFileCleanupTest.php`
- Create: `tests/Unit/Database/PrivateFileMigrationTest.php`
- Modify: `src/Database/Entries_DB.php:205-270`
- Modify: `src/Database/Forms_DB.php:180-210`
- Modify: `src/Database/Installer.php:15-320`
- Modify: `src/Uninstall.php`
- Modify: `src/Frontend/File_Storage.php`

**Interfaces:**
- Consumes: `File_Storage::delete_records()`, `migrate_legacy_files()`, and `remove_site_files()`.
- Produces: database version `1.0.5`, advanced only when all required migrations succeed.

- [ ] **Step 1: Write failing entry deletion-order tests**

Use a recording `$wpdb` and temporary file records:

```php
public function test_successful_entry_delete_removes_captured_files() {
	$this->seedEntryWithPrivateFile( 4 );
	$this->assertTrue( $this->entries->delete( 4 ) );
	$this->assertFileDoesNotExist( $this->file );
}

public function test_failed_entry_delete_retains_files() {
	$this->seedEntryWithPrivateFile( 4 );
	$this->wpdb->failEntryDelete = true;
	$this->assertFalse( $this->entries->delete( 4 ) );
	$this->assertFileExists( $this->file );
}
```

The production change caught is deleting files before database success or never deleting them.

- [ ] **Step 2: Run entry cleanup tests RED**

Run: `vendor/bin/phpunit tests/Unit/Database/EntryFileCleanupTest.php --do-not-cache-result`

Expected: FAIL because `Entries_DB::delete()` does not load or clean file records.

- [ ] **Step 3: Implement post-database-success entry cleanup**

Capture `$entry = $this->get( $entry_id )` before deletion. Delete metadata and row using the existing tables. Only when both database operations succeed call `( new File_Storage() )->delete_records( $entry['data'] )`.

For `delete_by_form()`, capture each entry's `data`, delete the database rows, then clean every captured data map after successful deletion.

- [ ] **Step 4: Add and pass form cleanup tests**

Test that `Forms_DB::delete()` does not delete the form if associated-entry deletion fails and that successful form deletion removes files for every entry. Make `Forms_DB::delete()` check the `delete_by_form()` result before deleting the form.

Run: `vendor/bin/phpunit tests/Unit/Database/EntryFileCleanupTest.php tests/Unit/Database/FormFileCleanupTest.php --do-not-cache-result`

Expected: all entry/form cleanup tests pass.

- [ ] **Step 5: Write failing legacy migration tests**

Create a legacy `uploads/formtura/2026/08/file.png`, run the migration, and assert:

```php
$this->assertFileDoesNotExist( $legacy );
$this->assertFileExists( $this->storage->get_site_root() . '/2026/08/file.png' );
$this->assertSame( $this->storage->get_site_root() . '/2026/08/file.png', $this->storage->resolve( [ 'file' => $legacy, 'url' => 'https://example.test/file.png' ] ) );
```

Add a forced copy failure and assert the legacy source remains and migration returns false.

- [ ] **Step 6: Implement retryable migration**

`File_Storage::migrate_legacy_files()` recursively moves only files below the legacy Formtura root, prefers `rename()`, falls back to copy plus equal-size verification, and removes the source only after destination verification. Skip guard files and remove empty directories.

Change `Installer::run_migrations()` to return boolean, invoke this migration when upgrading below `1.0.5`, and call `update_db_version()` only on true. Activation/maybe-update must leave the old version in place on failure so the migration retries.

- [ ] **Step 7: Complete destructive uninstall file cleanup**

After the explicit true-setting guard, call `File_Storage::remove_site_files()` and remove the recognized legacy root. Add uninstall assertions that false retains both roots and true removes them without touching a sibling sentinel directory.

- [ ] **Step 8: Run migration, cleanup, and uninstall tests GREEN**

Run: `vendor/bin/phpunit tests/Unit/Database/EntryFileCleanupTest.php tests/Unit/Database/FormFileCleanupTest.php tests/Unit/Database/PrivateFileMigrationTest.php tests/Unit/UninstallTest.php --do-not-cache-result`

Expected: all targeted lifecycle tests pass.

- [ ] **Step 9: Commit Task 6**

```bash
git add src/Database/Entries_DB.php src/Database/Forms_DB.php src/Database/Installer.php src/Frontend/File_Storage.php src/Uninstall.php tests/Unit/Database/EntryFileCleanupTest.php tests/Unit/Database/FormFileCleanupTest.php tests/Unit/Database/PrivateFileMigrationTest.php tests/Unit/UninstallTest.php
git commit -m "Clean private files across entry lifecycle"
```

---

### Task 7: Release notes and full verification

**Files:**
- Modify: `readme.txt`
- Modify: `README.md`
- Modify: `doc/CHECKLIST.md`

**Interfaces:**
- Documents: private-vault default, `FORMTURA_PRIVATE_UPLOAD_DIR`, administrator-only links, destructive uninstall opt-in, and release build command.

- [ ] **Step 1: Update operational documentation**

Document:

```text
File uploads and signatures are stored outside the public WordPress document root. Define FORMTURA_PRIVATE_UPLOAD_DIR to an absolute writable directory outside the document root when the default parent directory is not writable. File links require a logged-in administrator with manage_options.
```

Add `scripts/build-release.sh` as the only supported release-package command and state that source archives are development checkouts, not installable release packages.

- [ ] **Step 2: Run full PHP and JavaScript test suites**

Run:

```bash
vendor/bin/phpunit --do-not-cache-result
pnpm exec jest --runInBand
```

Expected: all PHPUnit and Jest tests pass with zero failures.

- [ ] **Step 3: Run syntax and production build checks**

Run:

```bash
find . -path ./vendor -prune -o -path ./node_modules -prune -o -name '*.php' -print0 | xargs -0 -n1 php -l
pnpm run build
git diff --check
```

Expected: every PHP file reports no syntax errors; Vite exits 0; diff check emits no output.

- [ ] **Step 4: Build and inspect the actual ZIP**

Run:

```bash
scripts/build-release.sh /tmp/formtura-release-final
scripts/verify-release.sh /tmp/formtura-release-final/formtura-1.0.4.zip
unzip -l /tmp/formtura-release-final/formtura-1.0.4.zip
```

Expected: verification exits 0, required runtime files are listed, and forbidden development paths are absent.

- [ ] **Step 5: Inspect scope and preserve user work**

Run: `git status --short && git diff -- builder/styles/workspace.css`

Expected: the pre-existing CSS modification remains present and is absent from every implementation commit.

- [ ] **Step 6: Commit documentation**

```bash
git add README.md readme.txt doc/CHECKLIST.md
git commit -m "Document private storage and release packaging"
```
