# Privacy Compliance Design

Date: 2026-08-16
Status: Approved for implementation

## Objective

Close the privacy-compliance gap identified in QA finding 9: the plugin stores form PII, IP address, user agent, WordPress user ID, uploads, and signatures indefinitely with no WordPress privacy exporter, eraser, or retention mechanism, and `readme.txt` does not disclose that reCAPTCHA sends the visitor's token and IP address to Google.

## Non-goals

- Redesigning entry storage or the entries admin UI.
- Building a manual "purge now" admin button (the retention setting plus daily cron covers the requirement; the audit did not ask for an interactive tool).
- Per-form retention overrides — one site-wide setting.
- Adding disclosure UI inline in the reCAPTCHA settings screen (the audit specifically flags the public `readme.txt`; the settings screen is not scoped here).
- Any change to how reCAPTCHA verification itself works.

## 1. Email-to-entry matching

Formtura entries are not tied to a fixed "email" schema field — forms can have any field layout. Matching a WordPress Privacy request's target email to entries uses two independent strategies, unioned:

1. **WP user account.** `get_user_by( 'email', $email )`; if found, all entries with that `user_id` match.
2. **Email-type fields.** For every form, inspect `field['type'] === 'email'` fields and get each one's storage key via the existing `fta_get_field_name( $field )` helper. For that form's entries, a case-insensitive match of the stored value against the requested email counts as a match.

This only catches guest submissions that included an email-type field, which is the best signal available without assuming a fixed schema.

## 2. `Entries_DB` additions

Three new prepared-statement query methods, alongside the existing CRUD methods:

- `get_ids_by_user( $user_id )` — entry IDs where `user_id = %d`.
- `get_ids_by_meta_match( $form_id, array $meta_keys, $value )` — entry IDs scoped to one form, joined against `fta_entry_meta` where `meta_key IN (...)` and `LOWER(meta_value) = LOWER(%s)`.
- `get_ids_older_than( $cutoff )` — entry IDs across all forms where `created_at < %s`.

No changes to existing methods. Both the exporter, eraser, and purge routine delete/read through the existing `Entries_DB::delete( $entry_id )` method, which already deletes entry meta, the entry row, and associated uploaded files/signatures via `File_Storage::delete_records()` — no new file-deletion code is needed.

## 3. `src/Admin/Privacy.php`

New class, instantiated unconditionally in `Core::init_components()` (privacy tools and the purge cron must run in both admin and frontend contexts, matching how `Frontend\Submission` is already registered unconditionally).

Responsibilities:

- Register `wp_privacy_personal_data_exporters` → `export_data( $email, $page )`.
- Register `wp_privacy_personal_data_erasers` → `erase_data( $email, $page )`.
- Handle the `fta_purge_old_entries_event` cron action → `purge_old_entries()`.

### Exporter

`export_data( $email, $page = 1 )`:

1. Resolve matching entry IDs via the strategy in section 1, deduplicated.
2. Paginate at 20 entries per page, per the WP Privacy API contract (`page` is 1-indexed; response must include `done => true` once the last page is returned).
3. For each entry in the page, build one export item: group `formtura-entries` ("Form Entries"), item ID `formtura-entry-{id}`, with field label/value pairs (labels resolved from the form definition), IP address, user agent, submission date, and form name.

### Eraser

`erase_data( $email, $page = 1 )` mirrors the exporter's matching and pagination, calling `Entries_DB::delete( $entry_id )` for each match in the page and reporting `items_removed`, `items_retained` (always `false`), and a confirmation message per WP's eraser contract.

### Retention purge

`purge_old_entries()`:

1. Read `entry_retention_days` from `fta_settings` (default `0`).
2. If `0` or absent, no-op.
3. Otherwise compute `$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS )`, fetch matching IDs via `Entries_DB::get_ids_older_than( $cutoff )`, and delete each via `Entries_DB::delete()`.

## 4. Settings

`Settings::get_defaults()` gains `'entry_retention_days' => 0`. `Settings::sanitize_settings()` gains:

```php
if ( isset( $settings['entry_retention_days'] ) ) {
    $sanitized['entry_retention_days'] = max( 0, absint( $settings['entry_retention_days'] ) );
}
```

`views/settings.php` gains a new "Privacy" section: a number input labeled "Automatically delete entries after N days" with helper text explaining `0` disables automatic deletion and that this applies to all forms, alongside existing settings sections.

## 5. Cron lifecycle

`formtura.php`:

- `fta_activate()` additionally schedules `fta_purge_old_entries_event` daily via `wp_schedule_event()` if not already scheduled.
- `fta_deactivate()` additionally clears it via `wp_clear_scheduled_hook()`.

The purge callback itself no-ops when the setting is `0`, so the event can safely remain scheduled regardless of whether retention is currently enabled — no need to sync scheduling with settings changes.

## 6. `readme.txt`

New `== Privacy ==` section disclosing:

- What is stored per submission: the field values the form collects (which may include PII depending on the site owner's field configuration), uploaded files, signature images, the visitor's IP address, browser user agent, and the WordPress user ID for logged-in submitters.
- Data is retained indefinitely by default, stored only in the site's own database and file storage (never transmitted off-site by the plugin itself), until an administrator deletes it manually or configures automatic deletion in Settings.
- The plugin supports WordPress's built-in Export Personal Data and Erase Personal Data tools (Tools → Export/Erase Personal Data).
- If the site owner enables reCAPTCHA, the visitor's response token and IP address are sent to Google's reCAPTCHA verification API, subject to Google's Privacy Policy and Terms of Service.

## Error handling and observability

- Exporter/eraser callbacks follow the WP Privacy API contract exactly (return shape, pagination, `done` flag) so WP core's admin UI for personal-data requests works without special-casing Formtura.
- A purge or erase deletion failure surfaces the same way `Entries_DB::delete()` failures already do today (return `false`, no partial state) — no new error-handling contract is introduced.
- No new data is exposed to unauthenticated users; the exporter/eraser callbacks only run inside WP core's authenticated Privacy Tools admin flow, which already gates on `manage_options`-equivalent capabilities.

## Testing strategy

Implementation follows red-green-refactor.

### `Entries_DB` tests

- `get_ids_by_user` returns only entries owned by the given user ID.
- `get_ids_by_meta_match` matches case-insensitively and is scoped to the given form ID (a same-named field in another form does not match).
- `get_ids_older_than` returns only entries with `created_at` strictly before the cutoff.

### `Privacy` tests

- Exporter matches entries by WP user account.
- Exporter matches entries by an email-type field's value, case-insensitively.
- Exporter does not match an unrelated email.
- Exporter paginates correctly and sets `done` on the final page.
- Eraser deletes matched entries (including their files, verified via the existing `Entries_DB::delete` file-cleanup path) and reports items removed.
- Eraser is a no-op (zero entries removed, `done => true`) when no entries match.
- `purge_old_entries` is a no-op when `entry_retention_days` is `0`.
- `purge_old_entries` deletes only entries older than the configured window.

### Settings tests

- `entry_retention_days` sanitizes to a non-negative integer; a missing key leaves the default of `0`.

### Final verification

- Full PHPUnit suite.
- PHP syntax check over production and test PHP.

## Rollout and compatibility

Safe-by-default: `entry_retention_days` defaults to `0` (no automatic deletion), so existing installs see no behavior change on upgrade until an administrator opts in. The exporter/eraser are additive registrations with no effect outside WP's Privacy Tools admin flow. Release notes should call out the new Privacy settings section and the `readme.txt` disclosure update.
