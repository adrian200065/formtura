# Formtura Follow-Up Audit — Findings & Tracking

**Date opened:** 2026-08-20
**Context:** After release blockers 1–6 were fixed and verified in CI (settings data-loss/merge, dead controls, plaintext reCAPTCHA secret, SMTP `smtp_auth` checkbox, and baseline submission abuse protection — rate limiting, honeypot, trusted-proxy IP resolution), a follow-up audit was run across `src/Admin`, `src/Database`, `src/Frontend`, `src/Integrations`, the React builder (`builder/`), and release engineering (versioning, changelog, uninstall cleanup, i18n). This file tracks what that audit found and the status of each item. Check items off as they're fixed; leave the write-up in place as a record rather than deleting finished items, so the history of what shipped and why stays visible.

Nothing in `src/Admin`, `src/Database`, or the submission/upload/payment pipeline in `src/Frontend` turned up new security, IDOR, SQL, or data-loss defects — those layers came back clean. The remaining findings are below.

---

## High priority — same bug class as blockers already fixed

### [x] 1. `Form_Builder::sanitize_field_data()` silently discards ~20 field settings on save — FIXED 2026-08-21

- **File:** `src/Admin/Form_Builder.php` (the field sanitizer's `isset($field[key])` allowlist)
- **What's wrong:** The sanitizer is a hard allowlist. Any key the builder UI writes that isn't explicitly named in it is dropped before the field is stored — same defect class as the `submitButtonText`/`successMessage` and form-id `"0"` sentinel bugs already fixed, just with far more surface area.
- **Confirmed dead (builder writes them, sanitizer drops them):** `enableDisable`, `branchingLogic` / `branchTarget` (page-navigation / skip logic), `autoResize`, `collapsible`, `deleteOnReplace`, `dynamicDefault`, `enableQuantity`, `maxRows` / `minRows`, `unique`, `useIconChoices` / `useImageChoices`, `visibility`, `addNewLabel` / `addOtherChoice` / `removeLabel` / `repeatLayout`.
- **Failure scenario:** An admin configures any of these in `FieldLibrary.jsx`, saves the form, reopens the field — the setting is gone, silently, every time.
- **Why it's sized separately from #2:** This is "wire up existing keys to the allowlist + sanitize each appropriately," not a design question — each key's sanitization rule (bool, enum, int, string) can be inferred from how the builder uses it.
- **Fix applied:** Added all 18 missing branches to `sanitize_field_data()`, each typed from how `FieldLibrary.jsx` actually produces the value: booleans for the 11 toggle-only settings (`enableDisable`, `dynamicDefault`, `branchingLogic`, `autoResize`, `deleteOnReplace`, `addOtherChoice`, `useImageChoices`, `useIconChoices`, `unique`, `enableQuantity`, `collapsible`); `sanitize_key()` for `visibility` (a WP role slug populated dynamically from `Admin.php`, so not a fixed enum); `sanitize_text_field()` for the free-text `branchTarget`, `addNewLabel`, `removeLabel`; an `in_array()` enum with fallback for `repeatLayout` (`default`/`inline`/`grid`, matching the builder's own `<select>`); and `is_numeric()`-guarded `absint()` for `minRows`/`maxRows`, matching the existing `minFileSize`/`maxFileSize` pattern so an empty value survives as `''` rather than a bogus `0`. TDD: wrote failing tests for each key first (`tests/Unit/Admin/FormBuilderSanitizeTest.php`), watched them fail with "Undefined array key", then implemented. Full suite (552 tests) green after; `src/Admin/Form_Builder.php` PHPCS-clean.

### [x] 2. "Custom Validation Rules" is a dead end on both ends — FIXED 2026-08-21

- **Files:** `builder/components/FieldLibrary.jsx` (writes `field.customValidation` / `field.validationRule` as a free-text expression, e.g. `age >= 18`), `src/Admin/Form_Builder.php::sanitize_field_data()` (never reads either key), `src/Functions.php::fta_validate_field()` (the only server-side validation-rules consumer, expects a structured shape — `required`/`email`/`url`/`min_length`/`max_length` — under `field['validation']`, a key the builder never writes at all).
- **What's wrong:** Two independent broken halves, not one wiring bug. Even if the sanitizer passed the free-text expression through untouched, nothing anywhere evaluates an arbitrary expression string against submitted data.
- **Failure scenario:** Admin turns on "Custom Validation Rules," writes an expression, saves, reopens the field — empty, and was always inert; no form has ever actually enforced one.
- **Before implementing:** this needs a scope decision, not just a fix — e.g., (a) build a small, safe expression evaluator (whitelisted operators/fields only, no arbitrary code execution) and wire both ends to it, or (b) remove the UI entirely until there's a real design for it. Recommend surfacing this as its own AskUserQuestion when picked up, since guessing the evaluator's grammar/safety boundary wrong is expensive to redo.
- **Decision (via AskUserQuestion):** remove the UI rather than build an evaluator. Deleted the entire "Validation Rules" settings group (toggle + expression textarea) from `FieldLibrary.jsx`. No other code referenced `customValidation`/`validationRule`, so nothing else to clean up. Jest suite (115 tests) and PHPUnit suite (552 tests) both green after.

---

## Medium priority — release hygiene / advertised-but-unreachable features

### [x] 3. Plugin version and changelog are stale (three point-releases behind the code) — FIXED 2026-08-20

- **Files:** `formtura.php` (`Version: 1.0.6`, `FORMTURA_VERSION`), `package.json` (`"version": "1.0.6"`), `readme.txt` (`Stable tag: 1.0.6`, changelog stops at `= 1.0.6 =`).
- **What's wrong:** `@since` tags in the code already reach `1.0.9` (17+ occurrences across `src/` for the SMTP encryption, conditional logic, and this session's settings/abuse-protection fixes). The changelog documents none of blockers 1–6.
- **Impact:** Whatever `scripts/build-release.sh` packages right now ships as "1.0.6" with no changelog trail — any update mechanism keyed on version number sees no update available, and users get no visibility into the SMTP checkbox fix, reCAPTCHA secret encryption, settings data-loss fix, or new rate-limiting/honeypot/trusted-proxy protections.
- **Fix applied:** No shipped release ever actually existed at 1.0.7 or 1.0.8 (no git tag, no version-bump commit for either) — the `@since` tags were incremented in code comments without a real release ever being cut. Rather than reconstruct three artificial version boundaries, bumped straight from 1.0.6 to **1.0.9** (matching the highest `@since` already in the code) in `formtura.php` (`Version:` header + `FORMTURA_VERSION`), `package.json`, `readme.txt` (`Stable tag:`), and `tests/bootstrap.php`'s test-only `FORMTURA_VERSION` stub. Wrote one comprehensive `= 1.0.9 =` changelog entry (19 bullets) and a `= 1.0.9 =` Upgrade Notice covering all 41 commits of user-facing work since the `1.0.6` changelog was last written (`git log --oneline f5d3f38..HEAD`) — filtered down to real user-facing changes, skipping pure internal/tooling commits (CI setup, PHPCS/ESLint ruleset config, dependency audits, E2E test infra, Jest config fixes). Full PHPUnit suite (546 tests) green after.

### [x] 4. Mailchimp integration is advertised in `readme.txt` but is fully unreachable — FIXED 2026-08-21

- **Files:** `src/Integrations/Integrations.php` (hardcodes `'enabled' => false` for Mailchimp), `src/Admin/Settings.php` (never handles `mailchimp_api_key`), builder/settings views (no UI anywhere writes the per-form `mailchimp_enabled` / `mailchimp_list_id` / `mailchimp_email_field` keys), `readme.txt` (claims data "leaves the site through optional integrations such as Mailchimp if you enable and configure them").
- **What's wrong:** The claim in `readme.txt` describes a capability that doesn't exist — there is no way for an admin to enable or configure this today.
- **Related, lower-severity bugs in the same class, live only once this is wired up:**
  - `src/Integrations/Providers/Mailchimp.php` (`add_subscriber()`): API failures are silently swallowed — no `is_wp_error()` check, no `fta_log()` call, unlike every other integration point in this codebase (e.g. `Notifications::send_notification()`).
  - `src/Integrations/Providers/Mailchimp.php` (`set_api_endpoint()`): the API datacenter is parsed out of the admin-entered key by splitting on `-` with no format validation — a malformed key builds a bogus URL instead of failing cleanly. Not attacker-reachable (admin-supplied, not visitor-controlled), just sloppy.
- **Fix shape:** Either build the admin settings UI + per-form fields and fix the two bugs above as part of that work, or remove the `readme.txt` claim and consider whether to keep the dead provider class at all. Needs a decision on which, same as item 2.
- **Decision (via AskUserQuestion):** remove the claim, don't build the UI. Dropped the Mailchimp clause from `readme.txt`'s Privacy section. Left `Integrations.php`'s registration and `Providers/Mailchimp.php` in place rather than deleting them — the `mailchimp` entry is registered through the public `fta_integrations` filter (`Integrations::register_integrations()`), so unlike the dead builder UI in #2, this is a genuine (if currently unused) extension point a site owner could enable via a filter, not pure dead code. The two related bugs in `Mailchimp.php` are left as documented above ("live only once this is wired up") since nothing wires it up.

### [x] 5. `redirect_url` is fully wired server-side but has no admin UI to set it — FIXED 2026-08-21

- **Files:** `src/Admin/Form_Builder.php` (sanitizes `redirect_url`), `src/Frontend/Submission.php` (`build_success_response()` returns it), `assets/js/frontend.js` (already redirects on it if present) — but no field in `builder/components/FormSettingsDialog.jsx` (the only form-settings UI) ever writes it.
- **What's wrong:** Inverse of the other findings here — a working feature nobody can reach, not data loss.
- **Fix shape:** Add a "Redirect URL" field to `FormSettingsDialog.jsx` that writes the `redirect_url` key. Low risk, no design decision needed.
- **Fix applied:** Added a "Redirect URL" input to `FormSettingsDialog.jsx`, saved under the snake_case `redirect_url` key — unlike `submitButtonText`/`successMessage`, `Form_Builder.php::sanitize_settings_data()` has no camelCase alias for this one, so the key had to match exactly. TDD: added failing tests to `FormSettingsDialog.test.jsx` first (prefill + save-under-correct-key), watched them fail on the missing field, then implemented. Full Jest (208 tests) and PHPUnit (552 tests) suites green after.

---

## Low priority — cleanup / product-completeness, not urgent

### [x] 6. `fta_migrated_choice_types` option orphaned on uninstall — FIXED 2026-08-21

- **Files:** `src/Database/Installer.php` (creates the option via `update_option()`), `src/Uninstall.php` (`$options` cleanup list doesn't include it).
- **Impact:** A destructive "Delete Data on Uninstall" run leaves this one row behind. One-line fix — add the key to `Uninstall::$options`.
- **Fix applied:** Added `'fta_migrated_choice_types'` to `Uninstall::$options`. TDD: added a failing test to `UninstallTest.php` first, watched it fail, then added the one-line fix. Full suite (553 tests) green after.

### [ ] 7. `license_key` is dead schema — needs a decision, not urgent

- **Files:** `src/Admin/Settings.php` (`get_defaults()` / `sanitize_settings()` both handle it), `src/Admin/views/settings.php` (no field for it at all), and no license-validation/activation code exists anywhere in `src/`.
- **Fix shape:** Either finish it (add a UI field + real activation/validation flow) or remove the dead key from the schema. Harmless as shipped.

### [ ] 8. "Rich Text" field's WYSIWYG toolbar is misleading

- **Files:** `builder/components/FieldLibrary.jsx` (full bold/links/lists toolbar for the `rich-text` field type), `templates/fields/rich-text.php` (renders the saved `content` through `wp_strip_all_tags()` into a plain `<textarea>` default value on the frontend).
- **Impact:** Not a security issue — the server-side stripping is the *correct* choice for a plain-text-rendered field — but the editor UI promises formatting the field type can never deliver. Either simplify the builder's toolbar to match what actually survives to the frontend, or make the frontend template honor safe formatting (would need its own `wp_kses_post()`-style review).

---

## Investigated and ruled out (no action needed)

Documented so these aren't re-investigated from scratch in a future pass:

- **Email header injection via smart tags / field values:** every header-bound field (`to`, `subject`, `reply_to`, `cc`, `bcc`) is CRLF-stripped in `Notifications.php`, not just the one path patched in commit `1285425`. Checked exhaustively — clean.
- **HTML/XSS injection into notification email bodies via field values:** all visitor-facing field types route through `Utils\Sanitize::field()`, which strips tags for every type that can hold visitor text. The only field type that preserves markup (`html`) is presentational and never reads `$_POST`.
- **New `dangerouslySetInnerHTML` XSS in the builder:** all three sites (`FormPreview.jsx`, `FieldPreview.jsx`, `FieldLibrary.jsx`) are admin-only, capability-gated, and the two field types that persist HTML to the frontend are `wp_kses_post()`'d both on save and on render.
- **Path traversal / IDOR on file downloads:** ownership is proven by looking the file record up through its entry; `File_Storage::resolve_relative()` does `realpath()` + prefix comparison against the vault root; null-byte/absolute-path values rejected.
- **Payment/coupon manipulation via crafted POST data:** prices and coupon values come exclusively from the stored form definition server-side, never trusted from POST.
- **Other unprotected `wp_ajax_nopriv_*` endpoints:** only `fta_submit_form` and `fta_validate_coupon` exist anywhere in `src/`; both are already rate-limited.
- **Conditional-logic / required-field bypass via crafted requests:** server recomputes visibility from the same submitted trigger values a crafted request would supply — correct by design, not a gap.
- **`has_form_on_page()` in `src/Frontend/Frontend.php`:** dead code (defined, never called — a deliberate earlier choice per its own comment), harmless, not worth fixing on its own.
- **Smart-tag second-order substitution** (`Notifications::parse_smart_tags()` re-expands system tags like `{admin_email}` in a visitor's own literal answer text after field substitution): a genuine quirk, but none of the five system tags are secret, so not treated as a vulnerability. Flagged in case a future reviewer wants to confirm this is intentional.
- **i18n gaps, TODO/FIXME comments, cron cleanup on deactivation:** all checked, all clean.

---

## When picking this back up

Suggested order: #3 (cheap, should happen regardless of what else is picked) → #1 (biggest data-loss surface, no design decision needed) → #2 and #4 (each needs a scoping conversation before implementation — use AskUserQuestion) → #5 → #6 → #7/#8 (product decisions, not urgent).
