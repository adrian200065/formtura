# Missing Field Types — Design

**Date:** 2026-08-07
**Status:** Approved scope, pending implementation
**Baseline:** 17 of the 38 palette types render on the frontend (see
`doc/CHECKLIST.md`, Field Type Coverage). This cycle adds 12, taking coverage
to 29/38.

## Scope

### In this cycle (12 types)

| Tier | Types |
|------|-------|
| Presentational / plain templates | `content`, `section-divider`, `rich-text`, `address` |
| Template + JS + submission handling | `signature`, `camera` |
| Payment fields (no gateways) | `payment-single`, `payment-checkbox`, `payment-multiple`, `payment-dropdown`, `coupon`, `total` |

### Explicitly out

- **`repeater`** — deferred. The builder saves `children: []` but has no
  nesting UI; a frontend template is meaningless until fields can be placed
  inside one. Reclassified in the checklist as blocked on builder nesting
  support.
- **`page-break`, `entry-preview`, `layout`** — blocked on the multi-page
  subsystem, unchanged from before.
- **`paypal`, `stripe`, `square`, `authorize-net`** — payment gateways are
  server-side integrations, not templates. Out of scope. Their palette
  entries already open info dialogs pointing at a payments settings tab,
  so no palette work is needed this cycle.
- **`captcha`** — resolved outside this cycle: protection is form-wide via
  the reCAPTCHA settings (see commit 03baefa), and the palette item opens an
  info dialog pointing there. Comes off the missing list without a template.

### Decisions made during brainstorming

- Repeater: deferred (option 1 of 3).
- Rich text: plain textarea, no editor (option 1 of 3). The builder
  preview's fake toolbar is removed so the builder stops advertising an
  editor the visitor never gets.
- Coupon: codes defined per-field in the builder (option 1 of 2); no
  coupon-management admin UI this cycle.

## Existing patterns this design follows

- Templates live in `templates/fields/<type>.php`, receive `$field`, and
  use the `fta_get_field_*` helpers (`text.php` is the canon).
- Composite fields post subfields as `field_name[part]` (`name.php`).
- `Uploads::process_form_uploads()` discovers file fields by type and owns
  file validation and protected storage.
- `Submission::is_presentational_field()` exempts non-input fields from
  validation and storage.
- Choice fields validate submitted values against the defined choices.
- Frontend JS uses delegated events on `document` (`frontend.js`), so
  dynamically inserted forms work without re-initialization.

## 1. Presentational: `content`, `section-divider`, `rich-text`

**content** — mirrors `html.php`: builder options gain a textarea; the value
is stored through `wp_kses_post()` at save time and echoed by the template
(`wp_kses_post` again on output, as `html.php` does). Joins
`is_presentational_field()`.

**section-divider** — renders the field label as an `<h3
class="fta-section-title">`, the description if set, and an `<hr>`. No
input. Joins `is_presentational_field()`.

**rich-text** — a `<textarea>` using the existing `rows` setting (default
7). Submits under the existing `textarea` sanitizer; no new server code.
Builder preview replaces the fake toolbar with a plain textarea.

## 2. `address`

Composite input following `name.php`:

- Subfields: `line1`, `line2`, `city`, `state`, `zip`, `country`, posted as
  `field_x[line1]` etc., each with a sublabel (hideable via the existing
  `hideSublabels` convention).
- `scheme` option: `us` (default) labels State / ZIP Code; `international`
  labels State/Province/Region / Postal Code.
- `line2` and `country` are always optional. **Required** means `line1`,
  `city`, `state`, `zip` are all non-empty — enforced in
  `validate_field_type()`; `is_empty_value()` already understands arrays.
- Each part sanitized as text. Stored as the array of parts.

## 3. `camera`

`<input type="file" accept="image/*" capture="environment">` rendered in
the file-upload template's visual style (compact variant).

- The `'file-upload' !== $field['type']` check in `Uploads` becomes an
  `is_file_field()` helper covering `file-upload` and `camera`;
  `Submission::validate_submission()` uses the same helper for its skip.
- For camera fields the allowed-types check is **forced to images**
  (jpg/jpeg/png/gif/webp) regardless of field settings.
- Everything downstream (protected storage, entry file records, email
  attachment) is unchanged reuse.

## 4. `signature`

**Frontend** — a canvas pad plus Clear button plus hidden input:

- Pointer events (mouse + touch), drawn in `frontend.js` alongside the
  existing delegated handlers; pads are initialized on document ready and
  re-initializable via the same late-init hook pattern used for reCAPTCHA
  widgets.
- On stroke end the canvas is serialized to a PNG data URL into the hidden
  input; Clear empties both.
- Required = non-empty pad, checked client-side before submit and again
  server-side.

**Server** — in a `Signature` handler invoked from the submission path:

- Reject if the value is not a `data:image/png;base64,` URL.
- Decode; cap decoded size at 1 MB; verify the bytes are really a PNG via
  `wp_check_filetype_and_ext` on a temp file.
- Store through the same protected-storage path `Uploads` uses, producing a
  file record identical in shape to an upload's, so entry display and email
  attachment work unchanged.
- Any failure is a field error on the signature field (fail closed, no
  silent drop).

## 5. Payments

### Price model

- Payment choice fields use a `items` array of
  `{ label, value, price, isDefault }` — this is the shape the builder's
  existing payment-dropdown items editor already saves, so
  payment-checkbox and payment-multiple adopt it rather than inventing a
  parallel `choices`+price shape. `payment-single` has a single `price`
  setting.
- Prices exist only in the form definition. The page shows formatted
  prices and carries `data-price` attributes for display math; none of it
  is trusted on submission.

### The invariant

**The client's total is display-only.** On submission the server recomputes
the amount from the form definition + submitted selections + validated
coupon, and stores `amount`, `currency`, and an item breakdown in the
entry. The posted `total` value is ignored.

### Fields

- **payment-single** — renders label + `fta_format_price( price )`. No
  input; always included in the total.
- **payment-checkbox / payment-multiple / payment-dropdown** — render as
  the existing checkbox / radio / select templates do, with the formatted
  price appended to each choice label and `data-price` on each input.
  Submitted values are validated against the defined choices.
- **coupon** — text input + Apply button. Codes are per-field settings:
  a list of `{ code, type: fixed|percent, value }`. Codes are **never
  rendered to the page**. Apply calls a new nonced AJAX action
  `fta_validate_coupon` (`form_id`, `field_id`, `code`) returning the
  discount spec (`type`, `value`) or an error. Client applies it to the
  displayed total; the entered code is submitted with the form and
  re-validated server-side. Code comparison is case-insensitive.
- **total** — displays the running sum and posts a value that the server
  ignores. With `enableSummary`, renders the items table (item, qty,
  price) matching the builder preview. Recalculated by new payment JS in
  `frontend.js`: sum of `data-price` on checked/selected payment inputs +
  payment-single amounts − coupon discount, floored at 0.

### Server recompute

On submission, when a form contains payment fields:

1. Sum `payment-single` prices and the prices of submitted, validated
   choices.
2. If a coupon field has a code, validate it against the field's list;
   unknown code → field error on the coupon field. Valid → apply
   (fixed: subtract; percent: subtract `value`% of the subtotal), floor
   at 0.
3. Store on the entry: `amount` (final), `currency` (from settings),
   items breakdown, and the applied coupon code if any.

### Formatting

New helper `fta_format_price( $amount )`: symbol-before, two decimals,
symbol resolved from a filterable map keyed by the existing `currency`
setting (fallback: the currency code itself).

## 6. Builder additions

- `createField()` defaults for all 12 types (content text, address scheme,
  prices on payment choices, coupon list, etc.) and `getDefaultLabel()`
  entries.
- Options panel: a price column on the existing choices editor when the
  field is a payment choice type; a coupon-codes list editor; a content
  textarea. Everything else uses the existing generic options.
- `FieldPreview.jsx` cases for the types currently falling through to a
  bare text input (address, signature, camera, content, section-divider,
  payment types), mirroring the frontend markup. The rich-text case is
  simplified to a textarea.

## 7. Error handling and security

- Signature and coupon fail closed: invalid PNG or unknown code is a field
  error, never a silently dropped value.
- `fta_validate_coupon` is nonce-protected (`formtura_frontend` nonce, same
  as submission).
- Nothing secret reaches the page: coupon codes and the authoritative
  price math stay server-side. The page carries only prices the visitor
  already sees.
- Camera and signature bytes go through the same content-vs-extension
  verification uploads already use.

## 8. Testing

- **`FieldTemplateTest`** — provider rows for each new input type
  (existing render + name contract).
- **New PHPUnit** — address required/sanitize rules; signature decode
  (valid PNG, oversize, junk, wrong mime); payment recompute (selection
  math, fixed and percent coupons, tampered client total ignored, unknown
  coupon rejected, floor at 0); coupon AJAX handler.
- **Jest** — payment total updates on selection change; coupon apply flow
  with mocked AJAX; signature pad: stroke populates the hidden input,
  Clear empties it, required blocks submit when empty.

## 9. Documentation

- `doc/CHECKLIST.md`: the 12 types move to the rendering list (29/38);
  repeater reclassified as blocked on builder nesting.
- `readme.txt`: landed types added back to the field list, per the note
  left there in 1.0.3. Payment wording must say amounts are **recorded
  with the entry** — no charging happens, as no gateway is integrated.
