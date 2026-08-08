# Formtura Plugin - Development Checklist

## ✅ Phase 1: Plugin Scaffolding & Initialization (COMPLETED)

### Root Files
- [x] formtura.php - Main plugin file with header
- [x] uninstall.php - Cleanup on deletion
- [x] readme.txt - WordPress.org standard readme
- [x] README.md - Developer documentation
- [x] SETUP.md - Setup instructions
- [x] composer.json - PHP dependency management
- [x] package.json - JavaScript dependency management
- [x] .gitignore - Git ignore rules

### Directory Structure
- [x] /assets/css/ - Stylesheets
- [x] /assets/js/ - JavaScript files
- [x] /assets/images/ - Images and icons
- [x] /assets/fonts/ - Custom fonts
- [x] /src/ - PHP source code
- [x] /src/Admin/ - Admin functionality
- [x] /src/Frontend/ - Frontend functionality
- [x] /src/Database/ - Database operations
- [x] /src/Integrations/ - Third-party integrations
- [x] /src/Blocks/ - Gutenberg blocks
- [x] /src/Utils/ - Utility classes
- [x] /templates/ - Overridable templates
- [x] /templates/fields/ - Field templates
- [x] /templates/email/ - Email templates
- [x] /languages/ - Translation files

### Core Classes
- [x] Core.php - Main plugin class (Singleton)
- [x] Functions.php - Global helper functions

### Admin Classes
- [x] Admin.php - Admin controller
- [x] Form_Builder.php - Form builder logic
- [x] Form_Entries.php - Entry management
- [x] Form_Templates.php - Template library
- [x] Settings.php - Settings page
- [x] SMTP.php - SMTP configuration

### Frontend Classes
- [x] Frontend.php - Frontend controller
- [x] Submission.php - Form submission handler
- [x] Notifications.php - Email notifications
- [x] Uploads.php - File upload validation and storage

### Database Classes
- [x] Installer.php - Database installer, upgrade routine and migrations
- [x] Forms_DB.php - Forms CRUD
- [x] Entries_DB.php - Entries CRUD

### Integration Classes
- [x] Integrations.php - Integration manager
- [x] Providers/Mailchimp.php - Mailchimp integration

### Block Classes
- [x] Form_Selector.php - Gutenberg block

### Utility Classes
- [x] Sanitize.php - Sanitization utilities

### Assets
- [x] admin.css - Admin styles (modern CSS)
- [x] builder-src.css - Builder styles (modern CSS)
- [x] frontend.css - Frontend styles (modern CSS)
- [x] admin.js - Admin JavaScript
- [x] frontend.js - Frontend JavaScript

### Templates
- [x] form-wrapper.php - Main form template
- [x] email/notification.php - Email template
- [x] fields/ - 29 field templates plus 2 legacy aliases (see Field Type Coverage)

### Admin Views
- [x] views/forms-list.php - Forms list page
- [x] views/templates-library.php - Templates page
- [x] views/entries-list.php - Entries page
- [x] views/settings.php - Settings page
- [x] views/smtp-settings.php - SMTP page
- [x] views/form-builder.php - Builder mount point

### Translation
- [x] languages/formtura.pot - Translation template

### Dependencies
- [x] Composer autoloader configured
- [x] Composer dependencies installed
- [x] pnpm package.json created (migrated from npm)

---

## ✅ Phase 2: Form Builder UI (COMPLETED)

### React Application
- [x] Set up Vite build configuration
- [x] Create React app structure
- [x] Implement drag-and-drop with @dnd-kit
- [x] Build field library component
- [x] Build field options panel
- [x] Build form canvas component
- [x] Implement save/load functionality
- [ ] Add undo/redo functionality
- [x] Create empty state component
- [x] Add form preview mode (FormPreview.jsx)

### Builder Features
- [x] Field drag and drop
- [x] Field reordering
- [x] Field duplication
- [x] Field deletion
- [x] Field settings panel
- [x] Conditional logic builder — UI, persistence and frontend evaluation
      are wired end to end; not yet verified in a browser
- [ ] Validation rules UI
- [x] Form settings panel
- [ ] Notification settings (only the per-field "attach to email" toggle exists)
- [ ] Integration settings

---

## 🟡 Field Type Coverage

The builder palette offers **38 field types**. **29 render on the
frontend**. Of the other 9: 8 are genuine gaps blocked on missing
subsystems (a form using one renders nothing on the public site), and 1
(`captcha`) needs no template - it is complete by design, see below.

Since 1.0.3 a missing template is logged, and shown inline to administrators
when `WP_DEBUG` is on, rather than failing silently.

### Rendering end to end (29)
- [x] text, textarea, name, email, select, radio, checkbox
- [x] number, phone, website, html, hidden
- [x] datetime, password, file-upload, rating, number-slider
- [x] content, section-divider, rich-text, address, camera, signature
- [x] payment-single, payment-checkbox, payment-multiple, payment-dropdown
- [x] coupon, total

Payment amounts (`payment-single`/`-checkbox`/`-multiple`/`-dropdown`,
`coupon`, `total`) are **recorded with the entry**; no payment is collected,
since no gateway integration exists yet.

Legacy aliases kept for forms saved before 1.0.3: `checkboxes` → checkbox,
`date` → datetime.

### In the palette, no frontend template (8)

None of these can be placed on a form: every one of the eight palette entries
opens an info dialog saying what is missing, rather than adding a field that
would render nothing on the public site.

- [ ] repeater — blocked on builder nesting support: the builder saves
      `children: []` with no UI to fill it
- [ ] page-break, entry-preview, layout — blocked on the multi-page
      subsystem, which does not exist
- [ ] paypal, stripe, square, authorize-net — payment gateway integrations,
      not templates; their dialogs point at the payments settings tab, since
      connecting an account there is what will enable them

`captcha` is not on this list, and is not a gap: it is the 9th type that
does not render, but protection is form-wide via the reCAPTCHA settings
(1.0.4), and its palette item opens an info dialog by design rather than
needing a per-field template.

> `readme.txt` documents the 29 types that render. Add each remaining type
> to it as its template lands.

---

## ⏳ Phase 3: Advanced Features (IN PROGRESS)

### Form Features
- [ ] Multi-page forms
- [ ] Progress bar
- [ ] Save and continue later
- [ ] Form scheduling
- [ ] Entry limits
- [ ] Geolocation
- [~] Calculations — number field emits `data-calculation` and frontend.js
      evaluates it; no builder UI for formulas beyond a text input
- [x] Signature field
- [x] Rating field
- [ ] Likert scale

### File Uploads
- [x] Upload field in the builder
- [x] Frontend template (dropzone and compact styles)
- [x] Server-side validation (size, extension, contents/extension match)
- [x] Hard block list for executable and browser-executable types
- [x] Storage under uploads/formtura with randomised names and .htaccess guard
- [x] Attach uploads to email notifications
- [ ] Delete stored files when an entry is deleted
- [ ] Admin UI to download or remove a submitted file
- [ ] `deleteOnReplace` and `autoResize` field options are saved but unused

### Payment Integration
- [ ] PayPal integration
- [ ] Stripe integration
- [ ] Square integration
- [ ] Authorize.Net integration
- [ ] Product fields
- [ ] Coupon codes
- [ ] Tax calculations
- [ ] Recurring payments

### Marketing Integration
- [ ] Mailchimp (enhance existing)
- [ ] ConvertKit integration
- [ ] ActiveCampaign integration
- [ ] HubSpot integration
- [ ] Zapier webhooks
- [ ] Custom webhooks

### Analytics
- [ ] Form views tracking
- [ ] Conversion tracking
- [ ] Abandonment tracking
- [ ] Field analytics
- [ ] Entry charts
- [ ] Export reports

---

## ⏳ Phase 4: Pro Features (PENDING)

### Advanced Fields
- [~] Phone field — renders as `tel`, but there is no server-side validation
- [ ] Address field with autocomplete
- [~] Password field — renders, but no strength meter
- [x] Hidden field
- [ ] Camera/photo capture
- [ ] Layout fields (columns, sections)
- [ ] Repeater field
- [ ] Entry preview field
- [ ] Custom captcha
- [ ] Net Promoter Score (NPS)

### User Experience
- [ ] A/B testing
- [ ] Smart conditional logic
- [ ] Conversational forms
- [ ] Form templates marketplace
- [ ] Import/export forms
- [ ] Form versioning
- [x] Form cloning (ajax_duplicate_form)
- [ ] Bulk actions

### Developer Features
- [ ] REST API
- [ ] Webhooks
- [ ] Custom field types API (`fta_field_types` filter exists)
- [ ] Custom integrations API
- [ ] Developer documentation
- [ ] Code examples
- [ ] Hooks reference

### White Label
- [ ] Custom branding
- [ ] Remove Formtura branding
- [ ] Custom admin colors
- [ ] Custom email templates
- [ ] Custom CSS editor

---

## 🧪 Testing (ONGOING)

The PHP suite did not run at all before 1.0.3: `tests/bootstrap.php` loaded the
Composer autoloader before defining `ABSPATH`, and the `files` autoload of
`Functions.php` hit its exit guard, ending the process silently with status 0.

**Current: 103 PHP tests / 210 assertions, 46 JS tests. All passing.**

```bash
php vendor/bin/phpunit      # PHP
npx jest                    # JavaScript
npm run build               # Production bundle
```

### Unit Tests
- [ ] Core class tests
- [~] Database class tests — field type migration covered (MigrationTest)
- [x] Sanitization tests (SanitizeTest)
- [~] Validation tests — upload validation covered (UploadsTest); field and
      submission validation not yet
- [x] Field template rendering (FieldTemplateTest, all 18 types)

### Integration Tests
- [ ] Form submission tests (needs a live WordPress)
- [ ] Email notification tests
- [ ] SMTP tests
- [ ] Payment tests
- [ ] API tests
- [ ] `wp_handle_upload()` move-to-disk step (needs a live WordPress)
- [ ] The `$wpdb` loop in `migrate_choice_field_types()` (needs a live WordPress)

### Browser Testing
- [ ] Chrome
- [ ] Firefox
- [ ] Safari
- [ ] Edge
- [ ] Mobile browsers

### WordPress Compatibility
- [ ] WordPress 5.8+
- [ ] PHP 7.4+ (declared minimum)
- [ ] PHP 8.0+
- [ ] PHP 8.1+ (developed against PHP 8.4)
- [ ] Multisite compatibility

---

## 🔧 Recently Fixed (1.0.3)

Bugs found while building out the field templates and upload handling. Each
was load-bearing enough to keep a core feature from working at all.

- **Submissions never reached the handler.** `Core::init_components()` built
  the frontend only when `! is_admin()`, but `admin-ajax.php` sets
  `is_admin()` to true, so `wp_ajax_fta_submit_form` was never registered on
  the request that submits a form.
- **Submissions saved nothing.** The builder never set a field `name` and the
  sanitizer never persisted one, so validation and storage both looked up
  `$submission['']`. Required validation was bypassed for every field and each
  entry was stored under a single empty key. The field id is now the
  submission key via `fta_get_field_name()`.
- **`parse_smart_tags()` called `get_blogname()`**, which is not a WordPress
  function — a fatal on every notification, masked by the above.
- **Missing field templates failed silently**, rendering nothing at all.
- **Choice field slugs disagreed across three sources.** `radio` is now
  Multiple Choice (single answer) and `checkbox` is Checkboxes (multiple),
  matching what `fta_get_field_types()` always declared. Saved forms are
  migrated by `Installer::migrate_choice_field_types()`.
- **`Installer::maybe_update()` was never called**, so there was no upgrade
  path. Now runs on `plugins_loaded`.
- Removed `FieldSettings.jsx` (1,003 lines, imported nowhere).

### Migration caution

`Installer::migrate_field_types()` is **not idempotent** — a migrated
`checkbox` is indistinguishable from a legacy one, so a second pass would
wrongly produce `radio`. Migrated form ids are recorded in the
`fta_migrated_choice_types` option so an interrupted run resumes safely.
**Back up the database before upgrading a site with existing forms.**

---

## 📝 Documentation (ONGOING)

### User Documentation
- [ ] Getting started guide
- [ ] Form builder tutorial
- [ ] Field types reference
- [ ] Integration guides
- [ ] FAQ section
- [ ] Video tutorials

### Developer Documentation
- [ ] API reference
- [ ] Hooks reference
- [ ] Custom field types guide
- [ ] Custom integrations guide
- [ ] Code examples
- [ ] Contributing guide

---

## 🚀 Deployment (PENDING)

Build artifacts (`assets/js/builder.js`, `assets/css/builder.css`) are
gitignored, so any deploy must run `npm run build`.

### Pre-launch
- [ ] Security audit
- [ ] Performance optimization
- [ ] Code review
- [ ] Translation strings review
- [ ] Accessibility audit (WCAG 2.1 AA)
- [ ] Cross-browser testing
- [x] Align `FORMTURA_VERSION` with `Installer::DB_VERSION` (both 1.0.3)
- [x] Trim `readme.txt` to the field types that actually render

### WordPress.org Submission
- [ ] Plugin assets (banner, icon, screenshots)
- [ ] readme.txt finalization
- [ ] SVN repository setup
- [ ] Initial submission
- [ ] Review feedback implementation

### Marketing
- [ ] Website launch
- [ ] Documentation site
- [ ] Demo site
- [ ] Social media presence
- [ ] Email marketing setup

---

## 📊 Progress Summary

**Phase 1 (Scaffolding):** ✅ Complete
**Phase 2 (Builder UI):** ✅ Complete — undo/redo, validation rules UI and
notification/integration settings still open
**Field type coverage:** 🔴 17 of 38 palette types render on the frontend
**Phase 3 (Advanced):** ⏳ File uploads done; payments, marketing and
analytics not started
**Phase 4 (Pro):** ⏳ Not started

### Suggested next steps
1. Templates for the remaining palette types, or remove the ones that are not
   close to shipping from the palette and `readme.txt`.
2. Browser verification of conditional logic and calculations — both are wired
   but have never been exercised against a real form.
3. Delete stored upload files when their entry is deleted.
4. Form submission integration tests against a live WordPress.

---

**Last Updated:** August 7, 2026
**Plugin Version:** 1.0.3 (DB schema 1.0.3)
**Status:** Builder complete; frontend rendering covers 17 of 38 field types
