# Production Blockers 1-3 Design

Date: 2026-08-15
Status: Approved for implementation

## Objective

Resolve the first three production blockers without changing unrelated form-builder behavior:

1. Make uninstall data retention safe and consistent.
2. Produce a deterministic, installable release ZIP containing Composer autoloading and compiled builder assets.
3. Replace public upload and signature URLs with administrator-only downloads and complete file lifecycle cleanup.

## Non-goals

- Redesigning the entries administration interface.
- Changing which upload types or signature payloads are accepted.
- Adding customer-facing or anonymously signed download links.
- Changing the existing opt-in `attachToEmail` behavior.
- Addressing the remaining production-readiness findings outside these three blockers.

## 1. Uninstall data-retention contract

`delete_data_on_uninstall` becomes the single canonical setting. It is stored inside the existing `fta_settings` option and defaults to `false` so upgrades and fresh installations retain data unless an administrator explicitly opts into deletion.

The settings view, sanitizer, defaults, installer defaults, and uninstall routine will all use this key. Unchecked checkboxes must be written as `false`; saving settings must not preserve a stale `true` value merely because the checkbox is absent from the request.

On uninstall:

- If `delete_data_on_uninstall` is absent or false, the routine returns without deleting tables, options, uploaded files, or private files.
- If it is true, the routine deletes Formtura tables, Formtura options, the private file vault, and any legacy public Formtura upload directory.
- The obsolete standalone `fta_keep_data_on_uninstall` option is no longer read. It may be deleted only during an explicitly destructive uninstall.
- Multisite cleanup operates on the current site's prefixed tables and site-specific vault directory. Network-wide uninstall behavior is not added in this change.

The safe default intentionally does not infer a destructive preference from the legacy `keep_data_on_uninstall` value because the previous UI never saved that key reliably.

## 2. Deterministic release ZIP

A repository script will build the release in an isolated temporary workspace so it does not depend on ignored artifacts already present in the developer checkout.

The script will:

1. Copy the working tree into a temporary build workspace while excluding Git metadata, existing dependency directories, and prior release output.
2. Run `composer install --no-dev --classmap-authoritative` in the workspace.
3. Run `pnpm install --frozen-lockfile` and the production Vite build in the workspace.
4. Copy only runtime files into a top-level `formtura/` package directory using a committed distribution exclusion list.
5. Assert that `vendor/autoload.php`, `assets/js/builder.js`, `assets/css/builder.css`, `formtura.php`, `src/`, and `templates/` exist in the package.
6. Assert that development-only directories such as `node_modules`, `tests`, and `builder` are absent.
7. Create `dist/formtura-<version>.zip` and print its path.

The build must fail on any missing command, dependency-install failure, asset-build failure, missing required runtime file, or unexpected development directory.

A GitHub Actions workflow will run the PHP and JavaScript tests, build the release artifact, inspect its contents, and upload the ZIP. Tag pushes matching `v*` and manual workflow dispatches will produce release artifacts; pull requests will validate that the same packaging process succeeds without publishing a release.

The main plugin bootstrap will fail gracefully with an administrator-facing notice when Composer autoloading is missing instead of reaching an unguarded class call. The release smoke test remains the primary guarantee that published artifacts never enter that state.

## 3. Private file vault

### Storage location

New uploads and signatures are stored in a Formtura vault outside the WordPress document root. The default is a site-specific directory beside `ABSPATH`, with a stable hash derived from the absolute WordPress path to prevent collisions between installations sharing a parent directory. Operators can override it with `FORMTURA_PRIVATE_UPLOAD_DIR`.

Multisite files are separated below the vault by blog ID. Files retain year/month partitioning and randomized stored filenames.

The storage layer validates that its resolved paths remain below the configured vault root. It creates the directory with restrictive permissions where the host permits them. If the directory cannot be created or written, file-producing submissions fail before an entry is saved. There is no fallback to publicly readable storage.

### File records

New entry metadata stores:

- the visitor-visible original filename;
- a vault-relative storage path;
- verified MIME type;
- byte size.

It does not store a public URL or a new absolute filesystem path. A central storage service resolves relative paths for internal operations such as download streaming, cleanup, and explicitly configured email attachments.

The resolver also understands legacy records containing `file` and `url` so entries created before this change remain usable after their files are migrated.

### Administrator-only downloads

An `admin-post.php` action will handle downloads. The request identifies an entry, field, and file index rather than accepting a filesystem path.

The controller will:

1. Require an authenticated user with `manage_options`.
2. Load the entry from Formtura's database.
3. Select the referenced file record from that entry's metadata.
4. Resolve and validate the file through the storage service.
5. Stream it with no-cache headers, `X-Content-Type-Options: nosniff`, a sanitized attachment filename, a verified content type, and an exact content length.

No anonymous action is registered. The action is read-only, and authorization plus ownership lookup provide the security boundary; durable email links therefore do not depend on expiring WordPress nonces.

Notification formatting receives the entry ID and replaces file-record values with links to the authenticated controller. A recipient who is not logged in as a capable administrator cannot retrieve the file. The existing `attachToEmail` field option remains an explicit bypass of link-only delivery and attaches the resolved private file as it does today.

### Legacy-file migration

During the database/plugin upgrade path, the migration moves files from the legacy `wp-content/uploads/formtura` tree into the site-specific private vault while preserving their relative year/month paths.

The migration follows these rules:

- Guard files are installed in the legacy directory before migration begins to reduce exposure on Apache/IIS during the move.
- A same-filesystem rename is preferred for individual files; copy-and-verified-delete is used when rename is unavailable.
- A source file is removed only after the destination exists with the same byte size.
- Existing destination files are not overwritten.
- Empty legacy directories are removed after successful moves.
- Legacy metadata need not be rewritten immediately: the compatibility resolver maps an old absolute path beneath the former public root to its new vault-relative location and ignores the old public URL.
- A failed migration records an actionable administrator notice and leaves the database record intact. New files still use the private vault.

The upgrade version advances past this migration only after the migration completes successfully, allowing a failed migration to retry.

### File lifecycle cleanup

All cleanup uses the central storage resolver and silently ignores records that contain no Formtura-managed file. It never deletes a path outside the private vault or recognized legacy Formtura upload root.

- Failed submission: delete all uploads and signatures already stored during that request, including successful files earlier in a multi-file field when a later file fails.
- Entry deletion: load file records first; delete database rows; only after database deletion succeeds, delete the captured files.
- Form deletion: capture files for all associated entries; delete the entries and form; delete captured files only after the corresponding database deletion succeeds.
- Destructive uninstall: remove the site-specific vault and legacy public Formtura directory only after the canonical setting explicitly opts into deletion.
- Retained uninstall: do not touch either database data or files.

Cleanup failure after successful database deletion is logged because restoring the deleted entry is not reliable. The cleanup routine returns failure details for tests and diagnostics, but an already completed database deletion is not reported as undone.

## Error handling and observability

- Public submissions receive the existing generic field/submission failure format; filesystem paths and internal storage errors are never exposed.
- Administrators receive a clear notice when the private vault is not writable or a legacy migration is incomplete.
- Unauthorized and invalid download requests use WordPress's standard error response without revealing whether a particular entry or file exists.
- File streaming exits immediately after completion and does not render WordPress admin HTML.
- Filesystem deletion and migration failures are sent to `error_log` only when Formtura debug mode or `WP_DEBUG` is enabled.

## Testing strategy

Implementation follows red-green-refactor. Each behavior receives a failing regression test before production code changes.

### Uninstall tests

- Missing setting retains tables, options, and files.
- False setting retains tables, options, and files.
- True setting deletes tables, options, private files, and legacy files.
- Settings sanitization writes false for an unchecked checkbox and true for a checked checkbox.

### Packaging tests

- The release command succeeds from a clean temporary workspace.
- The ZIP contains autoloading and compiled builder assets.
- The ZIP excludes development dependencies, tests, builder sources, and Git metadata.
- The packaged plugin bootstrap loads in a minimal WordPress test harness.
- A missing local autoloader produces the controlled bootstrap behavior rather than an unguarded class fatal.

### Storage and download tests

- Upload and signature records contain relative storage paths and no public URL.
- Resolved paths cannot escape the vault.
- Anonymous and insufficiently capable users cannot download.
- An administrator can download a file that belongs to the requested entry.
- A request cannot download a different entry's file or an arbitrary path.
- Notification links target the authenticated controller.
- Explicit email attachments resolve private files.
- Legacy records resolve after migration and their former public source disappears.

### Cleanup tests

- Partial multi-file failure removes earlier successful files.
- Failed entry creation removes all request files.
- Entry deletion removes its uploads and signatures only after database success.
- Failed entry deletion retains files.
- Form deletion removes files belonging to all deleted entries.
- Retained uninstall preserves every file; destructive uninstall removes only Formtura-owned directories.

### Final verification

- Full PHPUnit suite.
- Full Jest suite.
- PHP syntax check over production and test PHP.
- Production Vite build.
- Release ZIP construction and content inspection.
- Git diff review confirming the pre-existing `builder/styles/workspace.css` change was not modified by this work.

## Rollout and compatibility

This change is safe-by-default: uninstall retains data unless explicitly enabled, file fields fail closed if private storage is unavailable, and downloads require an administrator. Existing entries remain readable through the legacy record resolver.

Release notes must call out the private-vault requirement, the `FORMTURA_PRIVATE_UPLOAD_DIR` override, administrator-only file links, and the handling of previously public file URLs.
