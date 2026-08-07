# Missing Field Types Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add frontend rendering, submission handling, and builder support for 12 field types (content, section-divider, rich-text, address, camera, signature, payment-single, payment-checkbox, payment-multiple, payment-dropdown, coupon, total), taking coverage from 17/38 to 29/38.

**Architecture:** Each type gets a PHP template in `templates/fields/` following the existing `$field` + `fta_get_field_*` helper pattern. Camera and signature feed the existing protected-upload storage. Payment fields carry display-only prices; a new `PaymentTotals` class recomputes the authoritative amount server-side on submission and stores it under the entry's `_payment` key. Frontend JS additions live in `assets/js/frontend.js` using the existing delegated-event style.

**Tech Stack:** WordPress plugin PHP (no framework), PHPUnit 9 with `tests/wp-stubs.php` (no live WP), jQuery frontend JS tested with Jest/jsdom (`tests/js/` harness), React builder (`builder/`, Vite).

**Spec:** `docs/superpowers/specs/2026-08-07-missing-field-types-design.md` — read it first.

## Global Constraints

- Payment items shape is `field.items` = `{ label, value, price, isDefault }` — the shape the builder's payment-dropdown editor already saves. Never invent a parallel `choices`+price shape.
- The client's total is display-only; the server recomputes from the form definition. The posted total value is never trusted.
- Coupon codes are never rendered into page markup.
- Signature decoded size cap: 1 MB (1048576 bytes). PNG only.
- Camera allowed extensions are forced to `jpg, jpeg, png, gif, webp` regardless of field settings.
- All user-facing strings use `__( '...', FORMTURA_TEXTDOMAIN )`.
- Templates must render inputs named by `fta_get_field_name( $field )` (the `FieldTemplateTest` contract).
- Run PHP tests with `vendor/bin/phpunit`, JS tests with `npx jest`. Both suites must pass before every commit.
- Commit messages follow the repo style: imperative subject, body explaining why, ending with `Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>`.

**Key existing interfaces (already in the codebase):**

- `fta_get_field_name( $field )` → string submission key (falls back to field id)
- `fta_get_field_input_id( $field, $suffix = '' )` → string DOM id
- `fta_field_label( $field, $for_id = '' )` → echoes the label
- `fta_field_description( $field )` → echoes the description
- `fta_get_field_wrapper_class( $field, $base_class )` → string
- `fta_get_field_wrapper_data( $field )` → string of data-attributes (echo unescaped, phpcs-ignored)
- `fta_get_field_choices( $field )` → normalized `[ [ 'label', 'value', 'isDefault' ] ]`
- `fta_get_setting( $key, $default )` → plugin setting
- `fta_get_template_part( 'fields/<type>', '', [ 'field' => $field ] )` → renders template, returns false when missing
- `Uploads::process_form_uploads( $form )` → `[ field_name => [ file records ] ]` or `WP_Error` with per-field error data; file record = `[ 'name', 'file', 'url', 'type', 'size' ]`
- Test harness: `$GLOBALS['fta_test_options']['fta_settings']` seeds settings; `$GLOBALS['fta_test_http_handler']` intercepts `wp_remote_post` (see `tests/Unit/Frontend/RecaptchaTest.php`)
- Jest harness for frontend.js: see `tests/js/frontend-recaptcha.test.js` (shared jQuery, `loadFrontend()` eval pattern, `jQuery(document).off()` in beforeEach)

---

### Task 1: Price formatting and payment item helpers

**Files:**
- Modify: `src/Functions.php` (add after `fta_get_field_choices`, around line 500)
- Test: `tests/Unit/Utils/PriceTest.php` (create)

**Interfaces:**
- Consumes: `fta_get_setting()` (exists)
- Produces: `fta_get_currency_symbol(): string`, `fta_format_price( $amount ): string`, `fta_get_field_items( $field ): array` of `[ 'label' => string, 'value' => string, 'price' => float, 'isDefault' => bool ]`

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Utils/PriceTest.php`:

```php
<?php
/**
 * Price formatting and payment item normalization tests.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Utils;

use Formtura\Tests\TestCase;

class PriceTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fta_test_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );
		parent::tearDown();
	}

	public function test_formats_with_dollar_symbol_by_default() {
		$this->assertSame( '$10.00', fta_format_price( 10 ) );
	}

	public function test_formats_two_decimals_and_thousands() {
		$this->assertSame( '$1,234.50', fta_format_price( 1234.5 ) );
	}

	public function test_uses_the_configured_currency() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'currency' => 'EUR' ];

		$this->assertSame( '€25.00', fta_format_price( 25 ) );
	}

	public function test_unknown_currency_falls_back_to_its_code() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'currency' => 'XCD' ];

		$this->assertSame( 'XCD25.00', fta_format_price( 25 ) );
	}

	public function test_items_are_normalized() {
		$items = fta_get_field_items( [
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00', 'isDefault' => false ],
				[ 'label' => 'Large', 'value' => '', 'price' => 25 ],
			],
		] );

		$this->assertSame(
			[
				[ 'label' => 'Small', 'value' => 'small', 'price' => 10.0, 'isDefault' => false ],
				// Empty value falls back to the label, like choices do.
				[ 'label' => 'Large', 'value' => 'Large', 'price' => 25.0, 'isDefault' => false ],
			],
			$items
		);
	}

	public function test_junk_items_are_dropped_and_junk_prices_are_zero() {
		$items = fta_get_field_items( [
			'items' => [
				'not-an-array',
				[ 'label' => '', 'value' => '' ],
				[ 'label' => 'Thing', 'value' => 'thing', 'price' => 'abc' ],
			],
		] );

		$this->assertCount( 1, $items );
		$this->assertSame( 0.0, $items[0]['price'] );
	}

	public function test_missing_items_key_gives_empty_list() {
		$this->assertSame( [], fta_get_field_items( [] ) );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter PriceTest`
Expected: FAIL/ERROR with "Call to undefined function fta_format_price()"

- [ ] **Step 3: Implement the helpers**

In `src/Functions.php`, after `fta_get_field_choices()`:

```php
/**
 * Get the symbol for the configured currency.
 *
 * @since 1.0.4
 * @return string Currency symbol, or the currency code when no symbol is known.
 */
function fta_get_currency_symbol() {
	$currency = (string) fta_get_setting( 'currency', 'USD' );

	$symbols = apply_filters( 'fta_currency_symbols', [
		'USD' => '$',
		'EUR' => '€',
		'GBP' => '£',
		'AUD' => '$',
		'CAD' => '$',
		'JPY' => '¥',
	] );

	return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency;
}

/**
 * Format an amount in the configured currency.
 *
 * Display formatting only - payment math happens on unformatted floats.
 *
 * @since 1.0.4
 * @param float|int|string $amount Amount to format.
 * @return string Formatted price, e.g. "$10.00".
 */
function fta_format_price( $amount ) {
	return fta_get_currency_symbol() . number_format( (float) $amount, 2 );
}

/**
 * Get a payment field's items in a predictable shape.
 *
 * Items are the payment counterpart of choices: the builder saves
 * { label, value, price, isDefault } and this helper guarantees that shape
 * whatever is stored. Prices in the definition are the only prices the
 * server trusts.
 *
 * @since 1.0.4
 * @param array $field Field configuration.
 * @return array[] Normalized items.
 */
function fta_get_field_items( $field ) {
	$items      = isset( $field['items'] ) && is_array( $field['items'] ) ? $field['items'] : [];
	$normalized = [];

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$label = isset( $item['label'] ) ? (string) $item['label'] : '';
		$value = isset( $item['value'] ) && '' !== (string) $item['value'] ? (string) $item['value'] : $label;

		if ( '' === $value ) {
			continue;
		}

		$normalized[] = [
			'label'     => $label,
			'value'     => $value,
			'price'     => isset( $item['price'] ) && is_numeric( $item['price'] ) ? (float) $item['price'] : 0.0,
			'isDefault' => ! empty( $item['isDefault'] ),
		];
	}

	return $normalized;
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit --filter PriceTest` then the full suite `vendor/bin/phpunit`
Expected: PASS, no other failures

- [ ] **Step 5: Commit**

```bash
git add src/Functions.php tests/Unit/Utils/PriceTest.php
git commit -m "Add price formatting and payment item helpers

fta_get_field_items mirrors fta_get_field_choices for the { label, value,
price, isDefault } items shape the builder's payment-dropdown editor
already saves. fta_format_price formats from the existing currency
setting with a filterable symbol map.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 2: content and section-divider templates

**Files:**
- Create: `templates/fields/content.php`
- Create: `templates/fields/section-divider.php`
- Test: `tests/Unit/Templates/FieldTemplateTest.php` (add methods)

**Interfaces:**
- Consumes: `fta_get_field_wrapper_class`, `fta_get_field_wrapper_data`, `fta_field_description`
- Produces: two presentational templates. `Submission::is_presentational_field()` already lists both types — no submission change needed.

- [ ] **Step 1: Write the failing tests**

Add to `tests/Unit/Templates/FieldTemplateTest.php` (after `test_missing_template_is_reported_not_silent`):

```php
	public function test_content_field_renders_its_stored_markup() {
		$html = $this->render( $this->field( 'content', [
			'content' => '<p>Welcome to <strong>our</strong> form.</p>',
		] ) );

		$this->assertStringContainsString( '<strong>our</strong>', $html );
		$this->assertStringContainsString( 'fta-field-content', $html );
	}

	public function test_content_field_with_no_content_renders_nothing() {
		$html = $this->render( $this->field( 'content', [ 'content' => '  ' ] ) );

		$this->assertSame( '', trim( $html ) );
	}

	public function test_section_divider_renders_heading_and_rule() {
		$html = $this->render( $this->field( 'section-divider', [
			'label'       => 'Shipping Details',
			'description' => 'Where should we send it?',
		] ) );

		$this->assertStringContainsString( 'Shipping Details', $html );
		$this->assertStringContainsString( '<hr', $html );
		$this->assertStringNotContainsString( '<input', $html );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter FieldTemplateTest`
Expected: the three new tests FAIL (template renders nothing / assertions on empty string)

- [ ] **Step 3: Create the templates**

`templates/fields/content.php`:

```php
<?php
/**
 * Content Field Template
 *
 * Presentational block of author-supplied rich content. Stored through
 * wp_kses_post() when the form is saved; kses'd again on output the same
 * way html.php is.
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$content = isset( $field['content'] ) ? $field['content'] : '';

if ( '' === trim( $content ) ) {
	return;
}
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-content' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo wp_kses_post( $content ); ?>
</div><!-- /.fta-field-content -->
```

`templates/fields/section-divider.php`:

```php
<?php
/**
 * Section Divider Field Template
 *
 * Presentational heading plus horizontal rule. No input.
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$label      = isset( $field['label'] ) ? $field['label'] : '';
$hide_label = ! empty( $field['hideLabel'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-section-divider' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $label && ! $hide_label ) : ?>
		<h3 class="fta-section-title"><?php echo esc_html( $label ); ?></h3>
	<?php endif; ?>

	<?php fta_field_description( $field ); ?>

	<hr class="fta-section-rule" />
</div><!-- /.fta-field-section-divider -->
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit` (full suite)
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add templates/fields/content.php templates/fields/section-divider.php tests/Unit/Templates/FieldTemplateTest.php
git commit -m "Add content and section-divider frontend templates

Both are presentational and already exempt from validation via
is_presentational_field(). Content follows html.php: kses'd on save,
kses'd again on output, and renders nothing when empty.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 3: rich-text template and honest builder preview

**Files:**
- Create: `templates/fields/rich-text.php`
- Modify: `builder/components/FieldPreview.jsx` (the `case 'rich-text':` block, around line 554)
- Test: `tests/Unit/Templates/FieldTemplateTest.php` (provider row)

**Interfaces:**
- Consumes: builder settings `rows` (default 7) and `content` (HTML from the builder's WysiwygEditor, used as the default value)
- Produces: a `<textarea>` posting under the field name; value sanitized by the existing `textarea` path — no server change.

- [ ] **Step 1: Add the provider row (failing test)**

In `tests/Unit/Templates/FieldTemplateTest.php`, add to the array returned by `inputFieldTypeProvider()`:

```php
			'rich-text'     => [ 'rich-text', [] ],
```

- [ ] **Step 2: Run tests to verify the new rows fail**

Run: `vendor/bin/phpunit --filter FieldTemplateTest`
Expected: `test_template_renders_markup` and `test_input_posts_under_the_field_id` FAIL for `rich-text` ("rendered nothing")

- [ ] **Step 3: Create the template**

`templates/fields/rich-text.php`:

```php
<?php
/**
 * Rich Text Field Template
 *
 * Ships as a tall plain textarea (a deliberate decision - see the
 * 2026-08-07 field types spec). The builder's editor content, if any,
 * becomes the default value with markup stripped, since a plain textarea
 * would otherwise display raw tags.
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
$field_required = ! empty( $field['required'] );
$rows           = isset( $field['rows'] ) && absint( $field['rows'] ) > 0 ? absint( $field['rows'] ) : 7;
$default        = isset( $field['content'] ) ? wp_strip_all_tags( $field['content'] ) : '';
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-rich-text' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<textarea
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-textarea fta-rich-text-area"
		rows="<?php echo esc_attr( $rows ); ?>"
		<?php echo $field_required ? 'required' : ''; ?>
	><?php echo esc_textarea( $default ); ?></textarea>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-rich-text -->
```

Note: `wp_strip_all_tags` is not yet in `tests/wp-stubs.php`. Add it there (alphabetical position near the other sanitizers):

```php
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( $text );

		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}

		return trim( $text );
	}
}
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 5: Simplify the builder preview**

In `builder/components/FieldPreview.jsx`, replace the whole `case 'rich-text':` block (the fake toolbar with b/i/link buttons and Visual/Code tabs) with:

```jsx
      case 'rich-text':
        // Renders as a plain textarea on the frontend, so the preview must
        // not advertise an editor toolbar the visitor never gets.
        return (
          <textarea
            rows={field.rows || 7}
            placeholder={field.placeholder}
            readOnly
          />
        );
```

- [ ] **Step 6: Run the JS suite**

Run: `npx jest`
Expected: PASS (FieldPreview tests exist; fix any that asserted the old toolbar markup by updating them to expect a textarea)

- [ ] **Step 7: Commit**

```bash
git add templates/fields/rich-text.php builder/components/FieldPreview.jsx tests/Unit/Templates/FieldTemplateTest.php tests/wp-stubs.php
git commit -m "Render rich-text as a plain textarea, frontend and preview alike

Per the field types spec, rich text ships without an editor. The builder
preview's fake toolbar is removed so it stops advertising formatting the
visitor never gets; the builder's stored content becomes the textarea
default with markup stripped.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 4: address field

**Files:**
- Create: `templates/fields/address.php`
- Modify: `src/Frontend/Submission.php` (`validate_field_type`, around line 227)
- Modify: `builder/components/FormBuilder.jsx` (`createField` + `getDefaultLabel`)
- Modify: `builder/components/FieldLibrary.jsx` (GeneralTab: scheme select, near the name-format selector)
- Modify: `builder/components/FieldPreview.jsx` (add `case 'address':`)
- Test: `tests/Unit/Templates/FieldTemplateTest.php` (provider row + regex fix), `tests/Unit/Frontend/AddressValidationTest.php` (create)

**Interfaces:**
- Consumes: `name.php`'s composite pattern (`field_x[part]` inputs, sublabels, `hideSublabels`)
- Produces: posts `field_x[line1|line2|city|state|zip|country]`; field settings `scheme: 'us'|'international'`; server rule: required ⇒ line1, city, state, zip non-empty.

- [ ] **Step 1: Write the failing tests**

Provider row in `FieldTemplateTest.php`:

```php
			'address'       => [ 'address', [] ],
```

The provider's name regex only allows `[a-z]*` inside brackets and `line1` contains a digit. In `test_input_posts_under_the_field_id`, change the assertion regex to:

```php
		$this->assertMatchesRegularExpression(
			'/name="' . preg_quote( $expected, '/' ) . '(\[[a-z0-9]*\])?"/',
			$html,
			"Field type '{$type}' does not post under its field id."
		);
```

Create `tests/Unit/Frontend/AddressValidationTest.php`:

```php
<?php
/**
 * Address field validation tests.
 *
 * Required means line1, city, state and zip are all present. line2 and
 * country are always optional.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Submission;
use Formtura\Tests\TestCase;

class AddressValidationTest extends TestCase {

	/**
	 * Call the private validate_field_type.
	 *
	 * @param mixed $value Submitted value.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error
	 */
	private function validate( $value, array $field ) {
		$reflection = new \ReflectionMethod( Submission::class, 'validate_field_type' );
		$reflection->setAccessible( true );

		return $reflection->invoke( new Submission(), $value, $field );
	}

	private function field( array $extra = [] ) {
		return array_merge( [ 'id' => 'field_1', 'type' => 'address', 'label' => 'Address' ], $extra );
	}

	public function test_complete_required_address_passes() {
		$result = $this->validate(
			[ 'line1' => '1 Main St', 'city' => 'Springfield', 'state' => 'IL', 'zip' => '62701' ],
			$this->field( [ 'required' => true ] )
		);

		$this->assertTrue( $result );
	}

	public function test_required_address_missing_a_core_part_fails() {
		$result = $this->validate(
			[ 'line1' => '1 Main St', 'city' => 'Springfield', 'state' => '', 'zip' => '62701' ],
			$this->field( [ 'required' => true ] )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_optional_partial_address_passes() {
		$result = $this->validate(
			[ 'city' => 'Springfield' ],
			$this->field( [ 'required' => false ] )
		);

		$this->assertTrue( $result );
	}

	public function test_non_array_value_for_address_fails() {
		$result = $this->validate( 'just a string', $this->field( [ 'required' => true ] ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter 'FieldTemplateTest|AddressValidationTest'`
Expected: address rows FAIL ("rendered nothing"); AddressValidationTest FAILS (arrays currently early-return `true`, and the string case returns `true` too)

- [ ] **Step 3: Add the validation rule**

In `src/Frontend/Submission.php`, `validate_field_type()` currently begins:

```php
		$type = isset( $field['type'] ) ? $field['type'] : 'text';

		// Multi-value fields are validated per selected value.
		if ( is_array( $value ) ) {
			return true;
		}
```

Insert the address branch BETWEEN those two statements (before the array early-return, which would otherwise swallow it):

```php
		// Address posts an array of parts with its own completeness rule.
		if ( 'address' === $type ) {
			return $this->validate_address( $value, $field );
		}
```

Then add the method after `validate_field_type()`:

```php
	/**
	 * Validate an address field's parts.
	 *
	 * Required means street line 1, city, state and ZIP are all present.
	 * Line 2 and country are always optional.
	 *
	 * @since 1.0.4
	 * @param mixed $value Submitted value.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error
	 */
	private function validate_address( $value, $field ) {
		if ( ! is_array( $value ) ) {
			return new \WP_Error( 'invalid_address', __( 'Please enter a valid address.', FORMTURA_TEXTDOMAIN ) );
		}

		if ( empty( $field['required'] ) ) {
			return true;
		}

		foreach ( [ 'line1', 'city', 'state', 'zip' ] as $part ) {
			if ( ! isset( $value[ $part ] ) || '' === trim( (string) $value[ $part ] ) ) {
				return new \WP_Error( 'incomplete_address', __( 'Please complete the address.', FORMTURA_TEXTDOMAIN ) );
			}
		}

		return true;
	}
```

- [ ] **Step 4: Create the template**

`templates/fields/address.php`:

```php
<?php
/**
 * Address Field Template
 *
 * Composite input posting an array keyed by part, e.g. field_123[line1],
 * following the name field's pattern. The scheme option relabels the
 * region and postal parts for US or international audiences.
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_required = ! empty( $field['required'] );
$hide_sublabels = ! empty( $field['hideSublabels'] );
$hide_label     = ! empty( $field['hideLabel'] );
$legend         = isset( $field['label'] ) ? $field['label'] : '';
$scheme         = isset( $field['scheme'] ) && 'international' === $field['scheme'] ? 'international' : 'us';

$sublabels = [
	'line1'   => __( 'Address Line 1', FORMTURA_TEXTDOMAIN ),
	'line2'   => __( 'Address Line 2', FORMTURA_TEXTDOMAIN ),
	'city'    => __( 'City', FORMTURA_TEXTDOMAIN ),
	'state'   => 'international' === $scheme ? __( 'State / Province / Region', FORMTURA_TEXTDOMAIN ) : __( 'State', FORMTURA_TEXTDOMAIN ),
	'zip'     => 'international' === $scheme ? __( 'Postal Code', FORMTURA_TEXTDOMAIN ) : __( 'ZIP Code', FORMTURA_TEXTDOMAIN ),
	'country' => __( 'Country', FORMTURA_TEXTDOMAIN ),
];

// line2 and country stay optional even when the field is required.
$optional_parts = [ 'line2', 'country' ];

// Display rows: full-width lines, then city/state, then zip/country.
$rows = [
	[ 'line1' ],
	[ 'line2' ],
	[ 'city', 'state' ],
	[ 'zip', 'country' ],
];
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-address' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<fieldset class="fta-field-fieldset">
		<?php if ( $legend && ! $hide_label ) : ?>
			<legend class="fta-field-label<?php echo $field_required ? ' required' : ''; ?>">
				<?php echo esc_html( $legend ); ?>
			</legend>
		<?php endif; ?>

		<?php foreach ( $rows as $row ) : ?>
			<div class="fta-address-row fta-address-row-<?php echo esc_attr( count( $row ) ); ?>">
				<?php foreach ( $row as $part ) : ?>
					<?php $part_id = fta_get_field_input_id( $field, $part ); ?>
					<div class="fta-address-part fta-address-part-<?php echo esc_attr( $part ); ?>">
						<input
							type="text"
							id="<?php echo esc_attr( $part_id ); ?>"
							name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $part ); ?>]"
							class="fta-field-input"
							<?php echo $field_required && ! in_array( $part, $optional_parts, true ) ? 'required' : ''; ?>
						/>
						<?php if ( ! $hide_sublabels ) : ?>
							<label for="<?php echo esc_attr( $part_id ); ?>" class="fta-address-sublabel">
								<?php echo esc_html( $sublabels[ $part ] ); ?>
							</label>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</fieldset>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-address -->
```

- [ ] **Step 5: Run PHP tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 6: Builder support**

In `builder/components/FormBuilder.jsx` `createField()` switch, add before `default:`:

```jsx
      case 'address':
        return {
          ...baseField,
          label: 'Address',
          scheme: 'us',
          hideSublabels: false,
        };
```

In `getDefaultLabel()`'s map add: `'address': 'Address',`

In `builder/components/FieldLibrary.jsx` GeneralTab, directly after the name-format selector block (`{field.type === 'name' && ( ... )}`), add:

```jsx
      {/* Address Scheme Selector */}
      {field.type === 'address' && (
        <div className="formtura-form-group">
          <label htmlFor="field-scheme">
            Scheme <Tooltip text="US shows State and ZIP Code; International shows Province/Region and Postal Code." />
          </label>
          <select
            id="field-scheme"
            value={field.scheme || 'us'}
            onChange={(e) => handleChange('scheme', e.target.value)}
          >
            <option value="us">United States</option>
            <option value="international">International</option>
          </select>
        </div>
      )}
```

In `builder/components/FieldPreview.jsx`, add before `default:`:

```jsx
      case 'address':
        return (
          <div className="formtura-address-preview">
            <input type="text" placeholder="Address Line 1" readOnly />
            <input type="text" placeholder="Address Line 2" readOnly />
            <div className="formtura-address-preview-row">
              <input type="text" placeholder="City" readOnly />
              <input type="text" placeholder={field.scheme === 'international' ? 'State / Province / Region' : 'State'} readOnly />
            </div>
            <div className="formtura-address-preview-row">
              <input type="text" placeholder={field.scheme === 'international' ? 'Postal Code' : 'ZIP Code'} readOnly />
              <input type="text" placeholder="Country" readOnly />
            </div>
          </div>
        );
```

- [ ] **Step 7: Run the JS suite**

Run: `npx jest`
Expected: PASS

- [ ] **Step 8: Commit**

```bash
git add templates/fields/address.php src/Frontend/Submission.php builder/components/FormBuilder.jsx builder/components/FieldLibrary.jsx builder/components/FieldPreview.jsx tests/Unit/Templates/FieldTemplateTest.php tests/Unit/Frontend/AddressValidationTest.php
git commit -m "Add the address field end to end

Composite input following the name field's field[part] convention, with a
US/international scheme option. Required means line1, city, state and zip
are each present - checked server-side before the array early-return that
would otherwise wave any array through.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 5: camera field

**Files:**
- Create: `templates/fields/camera.php`
- Modify: `src/Frontend/Uploads.php` (type check at line ~62, `get_allowed_extensions` at ~330)
- Modify: `src/Frontend/Submission.php` (the two `'file-upload' === ...` skips, lines ~172 and in `sanitize_submission`)
- Modify: `builder/components/FormBuilder.jsx`, `builder/components/FieldPreview.jsx`
- Test: `tests/Unit/Frontend/UploadsTest.php` (add cases), `tests/Unit/Templates/FieldTemplateTest.php` (provider row)

**Interfaces:**
- Consumes: the whole existing upload pipeline (`process_form_uploads`, `check_type`, `store`)
- Produces: `Uploads::is_file_field( $field ): bool` (public static, true for `file-upload` and `camera`) — Task 6 also uses it.

- [ ] **Step 1: Write the failing tests**

Provider row in `FieldTemplateTest.php`:

```php
			'camera'        => [ 'camera', [] ],
```

In `tests/Unit/Frontend/UploadsTest.php` add (using the existing `invoke()` helper):

```php
	public function test_camera_counts_as_a_file_field() {
		$this->assertTrue( Uploads::is_file_field( [ 'type' => 'camera' ] ) );
		$this->assertTrue( Uploads::is_file_field( [ 'type' => 'file-upload' ] ) );
		$this->assertFalse( Uploads::is_file_field( [ 'type' => 'text' ] ) );
		$this->assertFalse( Uploads::is_file_field( [] ) );
	}

	public function test_camera_fields_only_allow_images_whatever_the_settings_say() {
		$extensions = $this->invoke( 'get_allowed_extensions', [ [
			'type'             => 'camera',
			'allowedFileTypes' => 'specify',
			'specifiedTypes'   => 'pdf, docx, exe',
		] ] );

		$this->assertSame( [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ], $extensions );
	}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter UploadsTest`
Expected: FAIL — `is_file_field` undefined; extensions test returns the pdf/docx list

- [ ] **Step 3: Implement the Uploads changes**

In `src/Frontend/Uploads.php`, add a public static method (near the top of the class, after the `$blocked_extensions` property):

```php
	/**
	 * Whether a field's value arrives in $_FILES and is handled here.
	 *
	 * @since 1.0.4
	 * @param array $field Field configuration.
	 * @return bool
	 */
	public static function is_file_field( $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';

		return in_array( $type, [ 'file-upload', 'camera' ], true );
	}
```

In `process_form_uploads()`, replace:

```php
			if ( ! isset( $field['type'] ) || 'file-upload' !== $field['type'] ) {
				continue;
			}
```

with:

```php
			if ( ! self::is_file_field( $field ) ) {
				continue;
			}
```

At the very top of `get_allowed_extensions( $field )`, before its existing logic:

```php
		// Camera captures photos; the field's own type settings never widen
		// that to arbitrary files.
		if ( 'camera' === ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
			return [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
		}
```

In `src/Frontend/Submission.php`, both skips (in `validate_submission` and `sanitize_submission`) currently read:

```php
			if ( 'file-upload' === ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
				continue;
			}
```

Replace both with:

```php
			if ( Uploads::is_file_field( $field ) ) {
				continue;
			}
```

(`Submission` and `Uploads` share the `Formtura\Frontend` namespace — no `use` statement needed.)

- [ ] **Step 4: Create the template**

`templates/fields/camera.php`:

```php
<?php
/**
 * Camera Field Template
 *
 * A photo capture input. The capture attribute opens the device camera
 * directly on phones and degrades to a normal image picker on desktop.
 * Uploads flow through the same validated, protected storage as the file
 * upload field, restricted to images.
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
$field_required = ! empty( $field['required'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-camera' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-file-upload-compact fta-camera-capture">
		<input
			type="file"
			id="<?php echo esc_attr( $field_input_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="fta-file-upload-input-compact"
			accept="image/*"
			capture="environment"
			<?php echo $field_required ? 'required' : ''; ?>
		/>
		<span class="fta-file-upload-filename"></span>
	</div>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-camera -->
```

- [ ] **Step 5: Run PHP tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 6: Builder support**

`createField()`:

```jsx
      case 'camera':
        return { ...baseField, label: 'Camera' };
```

`getDefaultLabel()` map: `'camera': 'Camera',`

`FieldPreview.jsx` before `default:`:

```jsx
      case 'camera':
        return (
          <div className="formtura-file-upload-preview">
            <div className="formtura-file-upload-dropzone">
              <div className="formtura-file-upload-text">Take a photo or choose an image</div>
              <div className="formtura-file-upload-size">Images only</div>
            </div>
          </div>
        );
```

- [ ] **Step 7: Run the JS suite, then commit**

Run: `npx jest`
Expected: PASS

```bash
git add templates/fields/camera.php src/Frontend/Uploads.php src/Frontend/Submission.php builder/components/FormBuilder.jsx builder/components/FieldPreview.jsx tests/Unit/Frontend/UploadsTest.php tests/Unit/Templates/FieldTemplateTest.php
git commit -m "Add the camera field on top of the upload pipeline

A capture-enabled image input. Uploads::is_file_field() now decides which
fields the upload handler owns, and camera fields force an image-only
extension list no matter what the field settings claim.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 6: signature server side

**Files:**
- Create: `src/Frontend/Signature.php`
- Modify: `src/Frontend/Submission.php` (`ajax_submit_form` after the uploads block; skips in `validate_submission`/`sanitize_submission`)
- Test: `tests/Unit/Frontend/SignatureTest.php` (create)

**Interfaces:**
- Consumes: `Uploads::UPLOAD_DIR` (check its visibility; if `private`, change to `public const`), `Uploads::protect_upload_dir( $path )` (public static, exists), `wp_upload_dir()`, `wp_generate_password()`
- Produces: `Signature::process_form_signatures( $form ): array|\WP_Error` — map `field_name => file record` (same `[ 'name', 'file', 'url', 'type', 'size' ]` shape uploads produce); `WP_Error( 'signature_failed', 'Please correct the errors below.', [ field_name => message ] )` on failure. Also `Signature::decode_data_url( $value ): string|\WP_Error` (public for tests).

- [ ] **Step 1: Write the failing tests**

A 1×1 transparent PNG for fixtures (base64): `iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==`

Create `tests/Unit/Frontend/SignatureTest.php`:

```php
<?php
/**
 * Signature decoding and storage tests.
 *
 * The pad submits a PNG data URL; the server must verify it really is a
 * small PNG before anything touches disk, and must fail closed on junk.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\Signature;
use Formtura\Tests\TestCase;

class SignatureTest extends TestCase {

	const PNG_BASE64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

	protected function setUp(): void {
		parent::setUp();
		$_POST = [];
	}

	protected function tearDown(): void {
		$_POST = [];
		parent::tearDown();
	}

	private function dataUrl() {
		return 'data:image/png;base64,' . self::PNG_BASE64;
	}

	public function test_valid_png_data_url_decodes() {
		$binary = Signature::decode_data_url( $this->dataUrl() );

		$this->assertIsString( $binary );
		$this->assertStringStartsWith( "\x89PNG", $binary );
	}

	public function test_non_data_url_is_rejected() {
		$this->assertInstanceOf( \WP_Error::class, Signature::decode_data_url( 'hello' ) );
	}

	public function test_jpeg_data_url_is_rejected() {
		$result = Signature::decode_data_url( 'data:image/jpeg;base64,' . self::PNG_BASE64 );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_png_prefix_with_non_png_bytes_is_rejected() {
		$result = Signature::decode_data_url( 'data:image/png;base64,' . base64_encode( '<?php evil(); ?>' ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_invalid_base64_is_rejected() {
		$result = Signature::decode_data_url( 'data:image/png;base64,!!!not-base64!!!' );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_oversize_payload_is_rejected() {
		// Base64 of >1MB of PNG-prefixed data.
		$blob   = "\x89PNG\r\n\x1a\n" . str_repeat( 'A', 1048577 );
		$result = Signature::decode_data_url( 'data:image/png;base64,' . base64_encode( $blob ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	public function test_missing_required_signature_is_a_field_error() {
		$form = [ 'fields' => [ [ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here', 'required' => true ] ] ];

		$result = ( new Signature() )->process_form_signatures( $form );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$errors = $result->get_error_data();
		$this->assertArrayHasKey( 'field_sig', $errors );
	}

	public function test_missing_optional_signature_is_skipped() {
		$form = [ 'fields' => [ [ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here', 'required' => false ] ] ];

		$this->assertSame( [], ( new Signature() )->process_form_signatures( $form ) );
	}

	public function test_valid_signature_is_stored_as_a_file_record() {
		$_POST['field_sig'] = $this->dataUrl();
		$form = [ 'fields' => [ [ 'id' => 'field_sig', 'type' => 'signature', 'label' => 'Sign here' ] ] ];

		$result = ( new Signature() )->process_form_signatures( $form );

		$this->assertIsArray( $result );
		$this->assertArrayHasKey( 'field_sig', $result );

		$record = $result['field_sig'][0];
		$this->assertSame( 'signature.png', $record['name'] );
		$this->assertSame( 'image/png', $record['type'] );
		$this->assertFileExists( $record['file'] );
		$this->assertStringEndsWith( '.png', $record['file'] );

		unlink( $record['file'] );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter SignatureTest`
Expected: ERROR — class `Formtura\Frontend\Signature` not found

- [ ] **Step 3: Implement the class**

First check `Uploads::UPLOAD_DIR`: `grep -n "UPLOAD_DIR" src/Frontend/Uploads.php`. If declared `private const`, change to `public const` (value stays `'formtura'`).

Create `src/Frontend/Signature.php`:

```php
<?php
/**
 * Signature Class
 *
 * Turns the signature pad's PNG data URL into a stored file. Verification
 * happens before anything touches disk: the value must be a PNG data URL,
 * decode cleanly, stay under the size cap, and carry real PNG magic bytes.
 *
 * @package Formtura
 * @since 1.0.4
 */

namespace Formtura\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Signature class.
 */
class Signature {

	/**
	 * Largest accepted decoded payload, in bytes.
	 */
	const MAX_BYTES = 1048576;

	/**
	 * Process every signature field on a form.
	 *
	 * Mirrors Uploads::process_form_uploads(): returns a map of field name
	 * to file records, or a WP_Error carrying per-field messages.
	 *
	 * @since 1.0.4
	 * @param array $form Form data.
	 * @return array|\WP_Error Map of field name => file records, or WP_Error.
	 */
	public function process_form_signatures( $form ) {
		$results = [];
		$errors  = [];

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $results;
		}

		foreach ( $form['fields'] as $field ) {
			if ( 'signature' !== ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
				continue;
			}

			$field_name = fta_get_field_name( $field );

			if ( '' === $field_name ) {
				continue;
			}

			// Deliberately not sanitize_text_field(): a multi-hundred-KB
			// data URL is not text, and decode_data_url() validates it in
			// full before anything is done with it.
			$value = isset( $_POST[ $field_name ] ) ? (string) wp_unslash( $_POST[ $field_name ] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			if ( '' === $value ) {
				if ( ! empty( $field['required'] ) ) {
					$errors[ $field_name ] = sprintf(
						/* translators: %s: field label */
						__( '%s is required.', FORMTURA_TEXTDOMAIN ),
						isset( $field['label'] ) ? $field['label'] : $field_name
					);
				}

				continue;
			}

			$binary = self::decode_data_url( $value );

			if ( is_wp_error( $binary ) ) {
				$errors[ $field_name ] = $binary->get_error_message();
				continue;
			}

			$stored = $this->store_png( $binary );

			if ( is_wp_error( $stored ) ) {
				$errors[ $field_name ] = $stored->get_error_message();
				continue;
			}

			// A list with one record, matching the uploads shape, so entry
			// display and email attachment treat both identically.
			$results[ $field_name ] = [ $stored ];
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'signature_failed',
				__( 'Please correct the errors below.', FORMTURA_TEXTDOMAIN ),
				$errors
			);
		}

		return $results;
	}

	/**
	 * Decode and verify a signature data URL.
	 *
	 * @since 1.0.4
	 * @param string $value Raw submitted value.
	 * @return string|\WP_Error Binary PNG bytes, or WP_Error.
	 */
	public static function decode_data_url( $value ) {
		$invalid = new \WP_Error( 'invalid_signature', __( 'The signature could not be read. Please sign again.', FORMTURA_TEXTDOMAIN ) );

		if ( 0 !== strpos( $value, 'data:image/png;base64,' ) ) {
			return $invalid;
		}

		$base64 = substr( $value, strlen( 'data:image/png;base64,' ) );

		// A 1MB PNG is ~1.37MB of base64; anything larger cannot pass the
		// decoded cap, so reject before spending memory on the decode.
		if ( strlen( $base64 ) > self::MAX_BYTES * 1.4 ) {
			return $invalid;
		}

		$binary = base64_decode( $base64, true );

		if ( false === $binary || strlen( $binary ) > self::MAX_BYTES ) {
			return $invalid;
		}

		// Real PNG bytes, not just a claimed mime type.
		if ( "\x89PNG\r\n\x1a\n" !== substr( $binary, 0, 8 ) ) {
			return $invalid;
		}

		return $binary;
	}

	/**
	 * Write PNG bytes into the plugin's protected upload directory.
	 *
	 * @since 1.0.4
	 * @param string $binary Verified PNG bytes.
	 * @return array|\WP_Error File record matching the uploads shape.
	 */
	private function store_png( $binary ) {
		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['error'] ) ) {
			return new \WP_Error( 'signature_store_failed', __( 'The signature could not be saved. Please try again.', FORMTURA_TEXTDOMAIN ) );
		}

		$dir = $uploads['basedir'] . '/' . Uploads::UPLOAD_DIR . $uploads['subdir'];
		$url = $uploads['baseurl'] . '/' . Uploads::UPLOAD_DIR . $uploads['subdir'];

		Uploads::protect_upload_dir( $uploads['basedir'] . '/' . Uploads::UPLOAD_DIR );

		if ( ! wp_mkdir_p( $dir ) ) {
			return new \WP_Error( 'signature_store_failed', __( 'The signature could not be saved. Please try again.', FORMTURA_TEXTDOMAIN ) );
		}

		// Random filename, matching the uploads convention, so stored
		// signatures cannot be enumerated.
		$filename = wp_generate_password( 24, false, false ) . '.png';
		$path     = $dir . '/' . $filename;

		if ( false === file_put_contents( $path, $binary ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
			return new \WP_Error( 'signature_store_failed', __( 'The signature could not be saved. Please try again.', FORMTURA_TEXTDOMAIN ) );
		}

		return [
			'name' => 'signature.png',
			'file' => $path,
			'url'  => $url . '/' . $filename,
			'type' => 'image/png',
			'size' => strlen( $binary ),
		];
	}
}
```

- [ ] **Step 4: Wire into the submission flow**

In `src/Frontend/Submission.php` `ajax_submit_form()`, directly after the uploads error block (`if ( is_wp_error( $uploads ) ) { ... }`), add:

```php
		// Signatures arrive as data URLs in $_POST but end life as stored
		// files, so they follow the uploads path, not the text path.
		$signatures = ( new Signature() )->process_form_signatures( $form );

		if ( is_wp_error( $signatures ) ) {
			wp_send_json_error( [
				'message' => $signatures->get_error_message(),
				'errors'  => $signatures->get_error_data(),
			] );
		}
```

And after the uploads merge loop (`foreach ( $uploads as $field_name => $files ) { ... }`), add:

```php
		foreach ( $signatures as $field_name => $files ) {
			$entry_data[ $field_name ] = $files;
		}
```

In BOTH `validate_submission()` and `sanitize_submission()`, extend the file-field skip added in Task 5 to:

```php
			$skip_type = isset( $field['type'] ) ? $field['type'] : '';

			if ( Uploads::is_file_field( $field ) || 'signature' === $skip_type ) {
				continue;
			}
```

(Signature runs its own required check, and the raw data URL must never be stored as text.)

- [ ] **Step 5: Run tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 6: Commit**

```bash
git add src/Frontend/Signature.php src/Frontend/Submission.php src/Frontend/Uploads.php tests/Unit/Frontend/SignatureTest.php
git commit -m "Store signature pad output through the protected upload path

The pad's PNG data URL is verified in full before disk is touched: PNG
data URL prefix, clean base64, 1MB decoded cap, and real PNG magic bytes.
Valid signatures become file records identical in shape to uploads, so
entry display and email attachment need no changes.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 7: signature frontend

**Files:**
- Create: `templates/fields/signature.php`
- Modify: `assets/js/frontend.js` (init + pad logic), `assets/css/frontend.css` (pad styles)
- Modify: `builder/components/FormBuilder.jsx`, `builder/components/FieldPreview.jsx`
- Test: `tests/js/frontend-signature.test.js` (create), `tests/Unit/Templates/FieldTemplateTest.php` (provider row)

**Interfaces:**
- Consumes: the jest harness pattern from `tests/js/frontend-recaptcha.test.js`
- Produces: markup contract for the JS — wrapper `.fta-signature`, canvas `.fta-signature-canvas`, hidden input `.fta-signature-value` (named by the field), clear button `.fta-signature-clear`. Exposes `window.formturaInitSignaturePads()` for late-inserted forms.

- [ ] **Step 1: Add the provider row (failing PHP test)**

```php
			'signature'     => [ 'signature', [] ],
```

Run: `vendor/bin/phpunit --filter FieldTemplateTest` — expect the signature rows to FAIL.

- [ ] **Step 2: Create the template**

`templates/fields/signature.php`:

```php
<?php
/**
 * Signature Field Template
 *
 * A drawing canvas backed by a hidden input. frontend.js serializes each
 * stroke into the hidden input as a PNG data URL; the server verifies and
 * stores it (see Frontend\Signature).
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
$field_required = ! empty( $field['required'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-signature' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-signature" data-fta-signature>
		<canvas class="fta-signature-canvas" width="600" height="180" aria-label="<?php esc_attr_e( 'Signature area. Draw your signature with mouse or touch.', FORMTURA_TEXTDOMAIN ); ?>"></canvas>

		<input
			type="hidden"
			id="<?php echo esc_attr( $field_input_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="fta-signature-value"
			<?php echo $field_required ? 'data-required="1"' : ''; ?>
		/>

		<button type="button" class="fta-signature-clear">
			<?php esc_html_e( 'Clear', FORMTURA_TEXTDOMAIN ); ?>
		</button>
	</div>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-signature -->
```

`esc_attr_e` may be missing from `tests/wp-stubs.php`; if so add:

```php
if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
```

Run: `vendor/bin/phpunit` — expect PASS.

- [ ] **Step 3: Write the failing jest tests**

Create `tests/js/frontend-signature.test.js`:

```js
/**
 * Signature pad tests for assets/js/frontend.js.
 *
 * jsdom has no canvas implementation, so 2D context and toDataURL are
 * mocked; the tests cover the wiring, not the pixels: strokes populate the
 * hidden input, Clear empties it, and a required empty pad blocks submit.
 */

const fs = require('fs');
const path = require('path');

const FRONTEND_JS = fs.readFileSync(
	path.join(__dirname, '..', '..', 'assets', 'js', 'frontend.js'),
	'utf8'
);

const jQuery = require('jquery');
window.jQuery = jQuery;
window.$ = jQuery;

const FAKE_DATA_URL = 'data:image/png;base64,AAAA';

function loadFrontend() {
	window.formturaFrontend = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		recaptcha: null,
		strings: {
			signatureMissing: 'Please add your signature.',
		},
	};

	// eslint-disable-next-line no-eval
	window.eval(FRONTEND_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function renderForm({ required = false } = {}) {
	document.body.innerHTML = `
		<form class="fta-form" data-form-id="7">
			<div class="fta-field fta-field-signature">
				<div class="fta-signature" data-fta-signature>
					<canvas class="fta-signature-canvas" width="600" height="180"></canvas>
					<input type="hidden" name="field_sig" class="fta-signature-value" ${required ? 'data-required="1"' : ''}>
					<button type="button" class="fta-signature-clear">Clear</button>
				</div>
			</div>
			<button type="submit" class="fta-submit-button">Submit</button>
		</form>
	`;
}

function drawStroke(canvas) {
	const opts = { bubbles: true, clientX: 10, clientY: 10 };
	canvas.dispatchEvent(new window.PointerEvent('pointerdown', opts));
	canvas.dispatchEvent(new window.PointerEvent('pointermove', { ...opts, clientX: 40, clientY: 30 }));
	canvas.dispatchEvent(new window.PointerEvent('pointerup', opts));
}

describe('signature pad', () => {
	let ajaxMock;

	beforeEach(() => {
		jQuery(document).off();
		document.body.innerHTML = '';

		ajaxMock = jest.fn();
		jQuery.ajax = ajaxMock;

		window.HTMLElement.prototype.scrollIntoView = jest.fn();
		window.HTMLCanvasElement.prototype.getContext = jest.fn(() => ({
			beginPath: jest.fn(),
			moveTo: jest.fn(),
			lineTo: jest.fn(),
			stroke: jest.fn(),
			clearRect: jest.fn(),
		}));
		window.HTMLCanvasElement.prototype.toDataURL = jest.fn(() => FAKE_DATA_URL);
		window.HTMLCanvasElement.prototype.setPointerCapture = jest.fn();
		if (typeof window.PointerEvent === 'undefined') {
			window.PointerEvent = window.MouseEvent;
		}
	});

	test('a stroke serializes the canvas into the hidden input', async () => {
		renderForm();
		await loadFrontend();
		window.formturaInitSignaturePads();

		drawStroke(document.querySelector('.fta-signature-canvas'));

		expect(document.querySelector('.fta-signature-value').value).toBe(FAKE_DATA_URL);
	});

	test('clear empties the hidden input', async () => {
		renderForm();
		await loadFrontend();
		window.formturaInitSignaturePads();

		drawStroke(document.querySelector('.fta-signature-canvas'));
		jQuery('.fta-signature-clear').trigger('click');

		expect(document.querySelector('.fta-signature-value').value).toBe('');
	});

	test('a required empty pad blocks submission with a message', async () => {
		renderForm({ required: true });
		await loadFrontend();
		window.formturaInitSignaturePads();

		jQuery('.fta-form').trigger('submit');
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect(ajaxMock).not.toHaveBeenCalled();
		expect(document.querySelector('.fta-field-error').textContent)
			.toContain('Please add your signature.');
	});

	test('a signed required pad submits', async () => {
		renderForm({ required: true });
		await loadFrontend();
		window.formturaInitSignaturePads();

		drawStroke(document.querySelector('.fta-signature-canvas'));

		jQuery('.fta-form').trigger('submit');
		await Promise.resolve();
		await Promise.resolve();
		await Promise.resolve();

		expect(ajaxMock).toHaveBeenCalledTimes(1);
	});
});
```

Run: `npx jest tests/js/frontend-signature.test.js` — expect FAIL (`formturaInitSignaturePads` is not a function).

- [ ] **Step 4: Implement the pad in frontend.js**

In `assets/js/frontend.js`, add to `init()` after `this.renderRecaptchaWidgets();`:

```js
			this.initSignaturePads();
```

Add these methods to `FormturaFrontend` (after `resetRecaptcha`):

```js
		/**
		 * Wire up every signature pad on the page.
		 *
		 * Canvas drawing cannot be delegated the way form events are, so
		 * pads are initialized directly; window.formturaInitSignaturePads()
		 * re-runs this for markup inserted after page load.
		 */
		initSignaturePads() {
			$('[data-fta-signature]').each(function() {
				const $pad = $(this);

				if ($pad.data('fta-signature-ready')) {
					return;
				}
				$pad.data('fta-signature-ready', true);

				const canvas = $pad.find('.fta-signature-canvas')[0];
				const $value = $pad.find('.fta-signature-value');

				if (!canvas || !canvas.getContext) {
					return;
				}

				const ctx = canvas.getContext('2d');
				let drawing = false;

				const point = (e) => {
					const rect = canvas.getBoundingClientRect();
					// jsdom reports a zero-size rect; guard the scale factors.
					const scaleX = rect.width ? canvas.width / rect.width : 1;
					const scaleY = rect.height ? canvas.height / rect.height : 1;
					return {
						x: (e.clientX - rect.left) * scaleX,
						y: (e.clientY - rect.top) * scaleY,
					};
				};

				canvas.addEventListener('pointerdown', (e) => {
					e.preventDefault();
					drawing = true;
					if (canvas.setPointerCapture && e.pointerId !== undefined) {
						canvas.setPointerCapture(e.pointerId);
					}
					const p = point(e);
					ctx.beginPath();
					ctx.moveTo(p.x, p.y);
				});

				canvas.addEventListener('pointermove', (e) => {
					if (!drawing) return;
					const p = point(e);
					ctx.lineTo(p.x, p.y);
					ctx.stroke();
				});

				canvas.addEventListener('pointerup', () => {
					if (!drawing) return;
					drawing = false;
					// Serialize on stroke end so the value is always current.
					$value.val(canvas.toDataURL('image/png')).trigger('change');
				});

				$pad.find('.fta-signature-clear').on('click', () => {
					ctx.clearRect(0, 0, canvas.width, canvas.height);
					$value.val('').trigger('change');
				});
			});
		},

		/**
		 * Block submission when a required pad is empty.
		 *
		 * @return {boolean} True when all required pads are signed.
		 */
		validateSignatures($form) {
			const strings = (window.formturaFrontend && formturaFrontend.strings) || {};
			let valid = true;

			$form.find('.fta-signature-value[data-required]').each(function() {
				const $value = $(this);

				if (!$value.val()) {
					FormturaFrontend.addFieldError(
						$value.closest('.fta-field'),
						strings.signatureMissing || 'Please add your signature.'
					);
					valid = false;
				}
			});

			return valid;
		},
```

In `handleSubmit`, after the existing `if (!isValid) { ... return; }` block, add:

```js
			if (!FormturaFrontend.validateSignatures($form)) {
				FormturaFrontend.showError($form, 'Please correct the errors below.');
				return;
			}
```

At the bottom of the file (next to `window.formturaRenderRecaptcha`), add:

```js
	// Let integrations initialize pads in markup added after page load.
	window.formturaInitSignaturePads = function() {
		FormturaFrontend.initSignaturePads();
	};
```

In `src/Frontend/Frontend.php`, add to the localized `strings` array:

```php
						'signatureMissing' => __( 'Please add your signature.', FORMTURA_TEXTDOMAIN ),
```

- [ ] **Step 5: Style the pad**

In `assets/css/frontend.css`, after the reCAPTCHA block:

```css
/* Signature */
.fta-signature-canvas {
	width: 100%;
	max-width: 100%;
	border: 1px solid var(--fta-form-border);
	border-radius: var(--fta-form-radius, 4px);
	background: hsl(0, 0%, 100%);
	touch-action: none; /* the pad owns touch gestures */
	cursor: crosshair;
	display: block;
}

.fta-signature-clear {
	margin-block-start: var(--fta-form-space-sm);
	background: none;
	border: none;
	color: var(--fta-form-focus);
	cursor: pointer;
	padding: 0;
	font-size: var(--fta-form-text-sm, 0.875rem);
}
```

(Verify `--fta-form-border`, `--fta-form-radius`, `--fta-form-text-sm` exist in the `:root` block at the top of `frontend.css`; substitute the tokens actually defined there if named differently.)

- [ ] **Step 6: Run both suites**

Run: `npx jest && vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 7: Builder support**

`createField()`:

```jsx
      case 'signature':
        return { ...baseField, label: 'Signature' };
```

`getDefaultLabel()` map: `'signature': 'Signature',`

`FieldPreview.jsx` before `default:`:

```jsx
      case 'signature':
        return (
          <div className="formtura-signature-preview">
            <div className="formtura-signature-preview-canvas">✕ Sign here</div>
            <button type="button" onClick={(e) => e.stopPropagation()}>Clear</button>
          </div>
        );
```

Run `npx jest` again, expect PASS.

- [ ] **Step 8: Commit**

```bash
git add templates/fields/signature.php assets/js/frontend.js assets/css/frontend.css src/Frontend/Frontend.php builder/components/FormBuilder.jsx builder/components/FieldPreview.jsx tests/js/frontend-signature.test.js tests/Unit/Templates/FieldTemplateTest.php tests/wp-stubs.php
git commit -m "Add the signature pad frontend

Pointer-event canvas serialized to a hidden input on every stroke end,
with a Clear control and a required check that blocks submission locally.
Pads expose formturaInitSignaturePads() for markup inserted after load,
since canvas drawing cannot ride the delegated-event model.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 8: payment field templates and builder editors

**Files:**
- Create: `templates/fields/payment-single.php`, `templates/fields/payment-checkbox.php`, `templates/fields/payment-multiple.php`, `templates/fields/payment-dropdown.php`
- Modify: `builder/components/FormBuilder.jsx` (createField, getDefaultLabel), `builder/components/FieldLibrary.jsx` (GeneralTab items/price editors), `builder/components/FieldPreview.jsx` (previews)
- Test: `tests/Unit/Templates/FieldTemplateTest.php` (provider rows + a price-rendering test)

**Interfaces:**
- Consumes: `fta_get_field_items()`, `fta_format_price()` (Task 1)
- Produces: markup contract for Task 9's JS — every price-carrying input has class `fta-payment-input` and `data-price`; payment-dropdown `<option>`s carry `data-price` and the select has class `fta-payment-select`; payment-single renders a hidden always-posted input.

- [ ] **Step 1: Write the failing tests**

Provider rows (payment types share a default items/price setup):

```php
			'payment-single'   => [ 'payment-single', [ 'price' => '10.00' ] ],
			'payment-checkbox' => [ 'payment-checkbox', $payment_items ],
			'payment-multiple' => [ 'payment-multiple', $payment_items ],
			'payment-dropdown' => [ 'payment-dropdown', $payment_items ],
```

with, near the `$choices` definition at the top of `inputFieldTypeProvider()`:

```php
		$payment_items = [
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00', 'isDefault' => false ],
				[ 'label' => 'Large', 'value' => 'large', 'price' => '25.00', 'isDefault' => false ],
			],
		];
```

Note: payment-checkbox posts `name="field_x[]"`; the provider regex from Task 4 (`(\[[a-z0-9]*\])?`) already matches the empty brackets.

Add a specific rendering test after the choice tests:

```php
	public function test_payment_items_render_prices_for_display_only() {
		$html = $this->render( $this->field( 'payment-multiple', [
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00', 'isDefault' => false ],
			],
		] ) );

		$this->assertStringContainsString( 'data-price="10.00"', $html );
		$this->assertStringContainsString( '$10.00', $html );
		$this->assertStringContainsString( 'fta-payment-input', $html );
	}

	public function test_payment_single_posts_a_marker_not_a_price() {
		$field = $this->field( 'payment-single', [ 'price' => '25.00' ] );
		$html  = $this->render( $field );

		// The visitor sees the price...
		$this->assertStringContainsString( '$25.00', $html );
		// ...but the posted value is only an inclusion marker. The server
		// takes prices from the form definition, never from the request.
		$this->assertStringContainsString( 'name="field_1699_abc" value="1"', $html );
	}
```

Run: `vendor/bin/phpunit --filter FieldTemplateTest` — expect the new rows/tests to FAIL.

- [ ] **Step 2: Create the four templates**

`templates/fields/payment-single.php`:

```php
<?php
/**
 * Single Item Payment Field Template
 *
 * A fixed-price line item, always included in the total. The posted value
 * is an inclusion marker only; the server reads the price from the form
 * definition (see Frontend\PaymentTotals).
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
$price          = isset( $field['price'] ) && is_numeric( $field['price'] ) ? (float) $field['price'] : 0.0;
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-payment fta-field-payment-single' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-payment-single-price"><?php echo esc_html( fta_format_price( $price ) ); ?></div>

	<input
		type="hidden"
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>" value="1"
		class="fta-payment-input"
		data-price="<?php echo esc_attr( number_format( $price, 2, '.', '' ) ); ?>"
		data-item-label="<?php echo esc_attr( isset( $field['label'] ) ? $field['label'] : '' ); ?>"
	/>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-payment-single -->
```

`templates/fields/payment-checkbox.php`:

```php
<?php
/**
 * Checkbox Items Payment Field Template
 *
 * Multi-select priced items. Prices on the page are display hints; the
 * server recomputes from the form definition (see Frontend\PaymentTotals).
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_required = ! empty( $field['required'] );
$items          = fta_get_field_items( $field );
$show_price     = ! isset( $field['showPriceAfterLabels'] ) || ! empty( $field['showPriceAfterLabels'] );
$legend         = isset( $field['label'] ) ? $field['label'] : '';
$hide_label     = ! empty( $field['hideLabel'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-payment fta-field-payment-checkbox' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<fieldset class="fta-field-fieldset">
		<?php if ( $legend && ! $hide_label ) : ?>
			<legend class="fta-field-label<?php echo $field_required ? ' required' : ''; ?>">
				<?php echo esc_html( $legend ); ?>
			</legend>
		<?php endif; ?>

		<div class="fta-field-choices">
			<?php foreach ( $items as $index => $item ) : ?>
				<?php $item_id = fta_get_field_input_id( $field, $index ); ?>
				<div class="fta-choice-item">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $item_id ); ?>"
						name="<?php echo esc_attr( $field_name ); ?>[]"
						class="fta-choice-input fta-payment-input"
						value="<?php echo esc_attr( $item['value'] ); ?>"
						data-price="<?php echo esc_attr( number_format( $item['price'], 2, '.', '' ) ); ?>"
						data-item-label="<?php echo esc_attr( $item['label'] ); ?>"
						<?php checked( $item['isDefault'], true ); ?>
					/>
					<label for="<?php echo esc_attr( $item_id ); ?>" class="fta-choice-label">
						<?php echo esc_html( $item['label'] ); ?>
						<?php if ( $show_price ) : ?>
							<span class="fta-choice-price"><?php echo esc_html( fta_format_price( $item['price'] ) ); ?></span>
						<?php endif; ?>
					</label>
				</div>
			<?php endforeach; ?>
		</div>
	</fieldset>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-payment-checkbox -->
```

`templates/fields/payment-multiple.php` — identical to payment-checkbox except: base class `fta-field-payment-multiple`, `type="radio"`, `name="<?php echo esc_attr( $field_name ); ?>"` (no brackets), and `<?php echo $field_required ? 'required' : ''; ?>` on the input. Full file:

```php
<?php
/**
 * Multiple Items Payment Field Template
 *
 * Single-select priced items rendered as radios. Prices on the page are
 * display hints; the server recomputes from the form definition.
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_required = ! empty( $field['required'] );
$items          = fta_get_field_items( $field );
$show_price     = ! isset( $field['showPriceAfterLabels'] ) || ! empty( $field['showPriceAfterLabels'] );
$legend         = isset( $field['label'] ) ? $field['label'] : '';
$hide_label     = ! empty( $field['hideLabel'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-payment fta-field-payment-multiple' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<fieldset class="fta-field-fieldset">
		<?php if ( $legend && ! $hide_label ) : ?>
			<legend class="fta-field-label<?php echo $field_required ? ' required' : ''; ?>">
				<?php echo esc_html( $legend ); ?>
			</legend>
		<?php endif; ?>

		<div class="fta-field-choices">
			<?php foreach ( $items as $index => $item ) : ?>
				<?php $item_id = fta_get_field_input_id( $field, $index ); ?>
				<div class="fta-choice-item">
					<input
						type="radio"
						id="<?php echo esc_attr( $item_id ); ?>"
						name="<?php echo esc_attr( $field_name ); ?>"
						class="fta-choice-input fta-payment-input"
						value="<?php echo esc_attr( $item['value'] ); ?>"
						data-price="<?php echo esc_attr( number_format( $item['price'], 2, '.', '' ) ); ?>"
						data-item-label="<?php echo esc_attr( $item['label'] ); ?>"
						<?php checked( $item['isDefault'], true ); ?>
						<?php echo $field_required ? 'required' : ''; ?>
					/>
					<label for="<?php echo esc_attr( $item_id ); ?>" class="fta-choice-label">
						<?php echo esc_html( $item['label'] ); ?>
						<?php if ( $show_price ) : ?>
							<span class="fta-choice-price"><?php echo esc_html( fta_format_price( $item['price'] ) ); ?></span>
						<?php endif; ?>
					</label>
				</div>
			<?php endforeach; ?>
		</div>
	</fieldset>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-payment-multiple -->
```

`templates/fields/payment-dropdown.php`:

```php
<?php
/**
 * Dropdown Items Payment Field Template
 *
 * Single-select priced items rendered as a dropdown. Prices on the page
 * are display hints; the server recomputes from the form definition.
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
$field_required = ! empty( $field['required'] );
$items          = fta_get_field_items( $field );
$show_price     = ! isset( $field['showPriceAfterLabels'] ) || ! empty( $field['showPriceAfterLabels'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-payment fta-field-payment-dropdown' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<select
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-select fta-payment-select"
		<?php echo $field_required ? 'required' : ''; ?>
	>
		<option value="" data-price="0"><?php esc_html_e( 'Select an item', FORMTURA_TEXTDOMAIN ); ?></option>

		<?php foreach ( $items as $item ) : ?>
			<option
				value="<?php echo esc_attr( $item['value'] ); ?>"
				data-price="<?php echo esc_attr( number_format( $item['price'], 2, '.', '' ) ); ?>"
				data-item-label="<?php echo esc_attr( $item['label'] ); ?>"
				<?php selected( $item['isDefault'], true ); ?>
			><?php echo esc_html( $item['label'] . ( $show_price ? ' - ' . fta_format_price( $item['price'] ) : '' ) ); ?></option>
		<?php endforeach; ?>
	</select>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-payment-dropdown -->
```

- [ ] **Step 3: Run PHP tests to verify they pass**

Run: `vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 4: Builder support**

`createField()` in `FormBuilder.jsx`:

```jsx
      case 'payment-single':
        return { ...baseField, label: 'Single Item', price: '10.00' };
      case 'payment-checkbox':
      case 'payment-multiple':
      case 'payment-dropdown':
        return {
          ...baseField,
          label: type === 'payment-checkbox' ? 'Checkbox Items'
            : type === 'payment-multiple' ? 'Multiple Items' : 'Dropdown Items',
          items: [
            { label: 'First Item', value: 'first-item', price: '10.00', isDefault: false },
            { label: 'Second Item', value: 'second-item', price: '25.00', isDefault: false },
            { label: 'Third Item', value: 'third-item', price: '50.00', isDefault: false },
          ],
          showPriceAfterLabels: true,
        };
```

`getDefaultLabel()` map additions:

```jsx
      'payment-single': 'Single Item',
      'payment-checkbox': 'Checkbox Items',
      'payment-multiple': 'Multiple Items',
      'payment-dropdown': 'Dropdown Items',
```

In `FieldLibrary.jsx` GeneralTab:

1. The items-init `useEffect` condition `field.type === 'payment-dropdown' && !field.items` becomes:

```jsx
    if (['payment-dropdown', 'payment-checkbox', 'payment-multiple'].includes(field.type) && !field.items) {
```

2. The items editor render condition (currently `if (field.type === 'payment-dropdown')` around line 1372) becomes:

```jsx
    if (['payment-dropdown', 'payment-checkbox', 'payment-multiple'].includes(field.type)) {
```

3. Add a payment-single price block in `renderFieldSpecificOptions()`, next to the items editor:

```jsx
    // Single Item price
    if (field.type === 'payment-single') {
      return (
        <div className="formtura-form-group">
          <label htmlFor="field-price">
            Item Price <Tooltip text="The amount this item adds to the order total." />
          </label>
          <input
            id="field-price"
            type="number"
            min="0"
            step="0.01"
            className="formtura-price-input"
            value={field.price || ''}
            onChange={(e) => handleChange('price', e.target.value)}
          />
        </div>
      );
    }
```

`FieldPreview.jsx` cases before `default:`:

```jsx
      case 'payment-single':
        return (
          <div className="formtura-total-display">
            <span>{field.label || 'Single Item'}</span>
            <span>${parseFloat(field.price || 0).toFixed(2)}</span>
          </div>
        );

      case 'payment-checkbox':
      case 'payment-multiple':
        return (
          <div className="formtura-choices-preview">
            {(field.items || []).map((item, i) => (
              <div key={i} className="formtura-choice-preview-item">
                <input type={field.type === 'payment-checkbox' ? 'checkbox' : 'radio'} readOnly disabled />
                <span>
                  {item.label}
                  {field.showPriceAfterLabels !== false && ` — $${parseFloat(item.price || 0).toFixed(2)}`}
                </span>
              </div>
            ))}
          </div>
        );

      case 'payment-dropdown':
        return (
          <select disabled>
            {(field.items || []).map((item, i) => (
              <option key={i}>
                {item.label}
                {field.showPriceAfterLabels !== false && ` — $${parseFloat(item.price || 0).toFixed(2)}`}
              </option>
            ))}
          </select>
        );
```

- [ ] **Step 5: Run the JS suite, then commit**

Run: `npx jest`
Expected: PASS

```bash
git add templates/fields/payment-single.php templates/fields/payment-checkbox.php templates/fields/payment-multiple.php templates/fields/payment-dropdown.php builder/components/FormBuilder.jsx builder/components/FieldLibrary.jsx builder/components/FieldPreview.jsx tests/Unit/Templates/FieldTemplateTest.php
git commit -m "Add the four payment item field templates

Items follow the { label, value, price, isDefault } shape the builder's
payment-dropdown editor already saves; the editor now covers checkbox and
multiple items too. Prices render for display and ride data-price for the
total JS, but the server only ever prices from the form definition.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 9: total field and payment display JS

**Files:**
- Create: `templates/fields/total.php`
- Modify: `assets/js/frontend.js` (payment recalculation), `src/Frontend/Frontend.php` (localize currency symbol), `assets/css/frontend.css`
- Test: `tests/js/frontend-payments.test.js` (create), `tests/Unit/Templates/FieldTemplateTest.php` (provider row)

**Interfaces:**
- Consumes: Task 8's markup contract (`.fta-payment-input[data-price]`, `.fta-payment-select` options with `data-price`, `data-item-label`)
- Produces: `.fta-field-total` containing `.fta-total-amount` display, hidden `.fta-total-input`, optional `.fta-order-summary` table body `.fta-order-summary-body`. JS: totals recalc on any change inside a form; `formturaFrontend.currency = { symbol }`.

- [ ] **Step 1: Add the provider row (failing PHP test)**

```php
			'total'         => [ 'total', [] ],
```

Run: `vendor/bin/phpunit --filter FieldTemplateTest` — expect FAIL, then create the template.

- [ ] **Step 2: Create the template**

`templates/fields/total.php`:

```php
<?php
/**
 * Total Field Template
 *
 * Displays the running order total. The posted value is display-side
 * convenience only - the server recomputes the amount from the form
 * definition and ignores whatever the browser sent (Frontend\PaymentTotals).
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
$enable_summary = ! empty( $field['enableSummary'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-total' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<?php if ( $enable_summary ) : ?>
		<table class="fta-order-summary">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Item', FORMTURA_TEXTDOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Price', FORMTURA_TEXTDOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody class="fta-order-summary-body"></tbody>
		</table>
	<?php endif; ?>

	<div class="fta-total-display">
		<span class="fta-total-label"><?php esc_html_e( 'Total', FORMTURA_TEXTDOMAIN ); ?></span>
		<span class="fta-total-amount"><?php echo esc_html( fta_format_price( 0 ) ); ?></span>
	</div>

	<input
		type="hidden"
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-total-input"
		value="0"
	/>
</div><!-- /.fta-field-total -->
```

`esc_html_e` exists in the stubs. Run `vendor/bin/phpunit` — expect PASS.

- [ ] **Step 3: Write the failing jest tests**

Create `tests/js/frontend-payments.test.js`:

```js
/**
 * Payment total display tests for assets/js/frontend.js.
 *
 * The displayed total is convenience only - the server recomputes - but it
 * must track selections correctly or visitors are misled about what they
 * are agreeing to.
 */

const fs = require('fs');
const path = require('path');

const FRONTEND_JS = fs.readFileSync(
	path.join(__dirname, '..', '..', 'assets', 'js', 'frontend.js'),
	'utf8'
);

const jQuery = require('jquery');
window.jQuery = jQuery;
window.$ = jQuery;

function loadFrontend() {
	window.formturaFrontend = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		recaptcha: null,
		currency: { symbol: '$' },
		strings: {},
	};

	// eslint-disable-next-line no-eval
	window.eval(FRONTEND_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function renderPaymentForm() {
	document.body.innerHTML = `
		<form class="fta-form" data-form-id="7">
			<div class="fta-field fta-field-payment fta-field-payment-single">
				<input type="hidden" name="field_single" value="1" class="fta-payment-input" data-price="5.00" data-item-label="Base fee">
			</div>
			<div class="fta-field fta-field-payment fta-field-payment-checkbox">
				<input type="checkbox" name="field_extras[]" value="gift" class="fta-choice-input fta-payment-input" data-price="10.00" data-item-label="Gift wrap">
				<input type="checkbox" name="field_extras[]" value="rush" class="fta-choice-input fta-payment-input" data-price="20.00" data-item-label="Rush order">
			</div>
			<div class="fta-field fta-field-payment fta-field-payment-dropdown">
				<select name="field_size" class="fta-field-select fta-payment-select">
					<option value="" data-price="0">Select an item</option>
					<option value="small" data-price="10.00" data-item-label="Small">Small - $10.00</option>
					<option value="large" data-price="25.00" data-item-label="Large">Large - $25.00</option>
				</select>
			</div>
			<div class="fta-field fta-field-total">
				<table class="fta-order-summary"><tbody class="fta-order-summary-body"></tbody></table>
				<div class="fta-total-display">
					<span class="fta-total-amount">$0.00</span>
				</div>
				<input type="hidden" name="field_total" class="fta-total-input" value="0">
			</div>
			<button type="submit" class="fta-submit-button">Submit</button>
		</form>
	`;
}

describe('payment totals display', () => {
	beforeEach(() => {
		jQuery(document).off();
		document.body.innerHTML = '';
		jQuery.ajax = jest.fn();
		window.HTMLElement.prototype.scrollIntoView = jest.fn();
	});

	test('initial total includes always-on single items', async () => {
		renderPaymentForm();
		await loadFrontend();

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$5.00');
		expect(document.querySelector('.fta-total-input').value).toBe('5.00');
	});

	test('checking items adds their prices', async () => {
		renderPaymentForm();
		await loadFrontend();

		jQuery('input[value="gift"]').prop('checked', true).trigger('change');
		jQuery('input[value="rush"]').prop('checked', true).trigger('change');

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$35.00');
	});

	test('selecting a dropdown item adds its price and switching replaces it', async () => {
		renderPaymentForm();
		await loadFrontend();

		jQuery('.fta-payment-select').val('small').trigger('change');
		expect(document.querySelector('.fta-total-amount').textContent).toBe('$15.00');

		jQuery('.fta-payment-select').val('large').trigger('change');
		expect(document.querySelector('.fta-total-amount').textContent).toBe('$30.00');
	});

	test('unchecking removes the price', async () => {
		renderPaymentForm();
		await loadFrontend();

		jQuery('input[value="gift"]').prop('checked', true).trigger('change');
		jQuery('input[value="gift"]').prop('checked', false).trigger('change');

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$5.00');
	});

	test('the summary table lists selected items', async () => {
		renderPaymentForm();
		await loadFrontend();

		jQuery('input[value="gift"]').prop('checked', true).trigger('change');

		const rows = document.querySelectorAll('.fta-order-summary-body tr');
		const text = document.querySelector('.fta-order-summary-body').textContent;

		expect(rows.length).toBe(2);
		expect(text).toContain('Base fee');
		expect(text).toContain('Gift wrap');
	});
});
```

Run: `npx jest tests/js/frontend-payments.test.js` — expect FAIL (total stays `$0.00`).

- [ ] **Step 4: Implement the payment JS**

In `assets/js/frontend.js` `init()`, after `this.initSignaturePads();`:

```js
			this.initPayments();
```

Add methods to `FormturaFrontend`:

```js
		/**
		 * Keep every form's total display in step with its selections.
		 *
		 * Display-side convenience only: the server recomputes the amount
		 * from the form definition on submission and ignores this value.
		 */
		initPayments() {
			$(document).on('change', '.fta-payment-input, .fta-payment-select', function() {
				FormturaFrontend.recalculateTotal($(this).closest('.fta-form'));
			});

			// Initial state: single items count before any interaction.
			$('.fta-form').each(function() {
				const $form = $(this);
				if ($form.find('.fta-field-total').length) {
					FormturaFrontend.recalculateTotal($form);
				}
			});
		},

		/**
		 * Collect the currently selected payment items in a form.
		 *
		 * @return {Array<{label: string, price: number}>}
		 */
		selectedPaymentItems($form) {
			const items = [];

			$form.find('.fta-payment-input').each(function() {
				const $input = $(this);
				const type = ($input.attr('type') || '').toLowerCase();

				if ((type === 'checkbox' || type === 'radio') && !$input.prop('checked')) {
					return;
				}

				items.push({
					label: $input.data('item-label') || '',
					price: parseFloat($input.data('price')) || 0,
				});
			});

			$form.find('.fta-payment-select').each(function() {
				const $option = $(this).find('option:selected');
				const price = parseFloat($option.data('price')) || 0;

				if ($option.val()) {
					items.push({ label: $option.data('item-label') || $option.text(), price });
				}
			});

			return items;
		},

		/**
		 * Format an amount with the localized currency symbol.
		 */
		formatPrice(amount) {
			const symbol = (window.formturaFrontend && formturaFrontend.currency && formturaFrontend.currency.symbol) || '$';
			return symbol + amount.toFixed(2);
		},

		/**
		 * Recompute and render a form's total, summary and hidden value.
		 */
		recalculateTotal($form) {
			const $total = $form.find('.fta-field-total');

			if (!$total.length) {
				return;
			}

			const items = FormturaFrontend.selectedPaymentItems($form);
			let amount = items.reduce((sum, item) => sum + item.price, 0);

			// A validated coupon (set by the coupon apply flow) discounts the
			// displayed amount; the server re-validates independently.
			const coupon = $form.data('ftaCoupon');
			if (coupon) {
				amount -= coupon.type === 'percent' ? (amount * coupon.value) / 100 : coupon.value;
			}
			amount = Math.max(0, Math.round(amount * 100) / 100);

			$total.find('.fta-total-amount').text(FormturaFrontend.formatPrice(amount));
			$total.find('.fta-total-input').val(amount.toFixed(2));

			const $summary = $total.find('.fta-order-summary-body');
			if ($summary.length) {
				$summary.empty();
				items.forEach(item => {
					$('<tr>')
						.append($('<td>').text(item.label))
						.append($('<td>').text(FormturaFrontend.formatPrice(item.price)))
						.appendTo($summary);
				});
			}
		},
```

In `src/Frontend/Frontend.php`, add to the localized array (next to `'recaptcha'`):

```php
					'currency'  => [
						'symbol' => fta_get_currency_symbol(),
					],
```

- [ ] **Step 5: Style the total**

In `assets/css/frontend.css` after the signature block:

```css
/* Payments */
.fta-choice-price {
	color: var(--fta-form-muted, inherit);
	margin-inline-start: 0.5em;
}

.fta-total-display {
	display: flex;
	justify-content: space-between;
	font-weight: 600;
	padding-block: var(--fta-form-space-sm);
	border-block-start: 1px solid var(--fta-form-border, currentColor);
}

.fta-order-summary {
	width: 100%;
	border-collapse: collapse;
	margin-block-end: var(--fta-form-space-sm);
}

.fta-order-summary td,
.fta-order-summary th {
	text-align: start;
	padding-block: 0.25em;
}
```

(As in Task 7: verify token names against `frontend.css`'s `:root` and substitute the ones actually defined.)

- [ ] **Step 6: Run both suites**

Run: `npx jest && vendor/bin/phpunit`
Expected: PASS

- [ ] **Step 7: Builder additions for total**

`createField()`:

```jsx
      case 'total':
        return { ...baseField, label: 'Total', enableSummary: false };
```

`getDefaultLabel()` map: `'total': 'Total',` — the FieldPreview `case 'total':` already exists.

Run `npx jest`, expect PASS.

- [ ] **Step 8: Commit**

```bash
git add templates/fields/total.php assets/js/frontend.js assets/css/frontend.css src/Frontend/Frontend.php builder/components/FormBuilder.jsx tests/js/frontend-payments.test.js tests/Unit/Templates/FieldTemplateTest.php
git commit -m "Add the total field with live display-side recalculation

Sums data-price over selected payment inputs, renders the optional order
summary, and writes a hidden value the server deliberately ignores - the
authoritative amount comes from PaymentTotals on submission.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 10: PaymentTotals server recompute

**Files:**
- Create: `src/Frontend/PaymentTotals.php`
- Modify: `src/Frontend/Submission.php` (`ajax_submit_form` after signatures; `sanitize_submission` total skip)
- Test: `tests/Unit/Frontend/PaymentTotalsTest.php` (create)

**Interfaces:**
- Consumes: `fta_get_field_items()`, `fta_get_field_name()`, `fta_get_setting()`
- Produces: `PaymentTotals::form_has_payment_fields( $form ): bool`; `PaymentTotals::compute( $form, $submission ): array|\WP_Error` returning `[ 'amount' => float, 'currency' => string, 'items' => [ [ 'label', 'price' ] ], 'coupon' => string|null ]`, `WP_Error( 'payment_invalid', ..., [ field_name => message ] )` on bad selections/coupons; `PaymentTotals::find_coupon( $field, $code ): ?array` returning `[ 'code', 'type', 'value' ]` (Task 11's AJAX endpoint uses it).

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Frontend/PaymentTotalsTest.php`:

```php
<?php
/**
 * Server-side payment recomputation tests.
 *
 * The core invariant: the amount stored with an entry derives only from
 * the form definition and the visitor's selections - never from any
 * price or total the browser posted.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Frontend;

use Formtura\Frontend\PaymentTotals;
use Formtura\Tests\TestCase;

class PaymentTotalsTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['fta_test_options'] = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_options'] );
		parent::tearDown();
	}

	private function form( array $fields ) {
		return [ 'id' => 7, 'fields' => $fields ];
	}

	private function itemsField( $type, $id = 'field_items' ) {
		return [
			'id'    => $id,
			'type'  => $type,
			'label' => 'Items',
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00' ],
				[ 'label' => 'Large', 'value' => 'large', 'price' => '25.00' ],
			],
		];
	}

	public function test_detects_payment_forms() {
		$totals = new PaymentTotals();

		$this->assertTrue( $totals->form_has_payment_fields( $this->form( [ [ 'id' => 'a', 'type' => 'payment-single' ] ] ) ) );
		$this->assertFalse( $totals->form_has_payment_fields( $this->form( [ [ 'id' => 'a', 'type' => 'text' ] ] ) ) );
	}

	public function test_single_items_always_count() {
		$form = $this->form( [ [ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ] ] );

		$result = ( new PaymentTotals() )->compute( $form, [] );

		$this->assertSame( 5.0, $result['amount'] );
		$this->assertSame( [ [ 'label' => 'Fee', 'price' => 5.0 ] ], $result['items'] );
	}

	public function test_selected_items_price_from_the_definition_not_the_request() {
		$form = $this->form( [ $this->itemsField( 'payment-multiple' ) ] );

		// The browser can claim any total it likes; only the selection matters.
		$result = ( new PaymentTotals() )->compute( $form, [
			'field_items' => 'large',
			'field_total' => '0.01',
		] );

		$this->assertSame( 25.0, $result['amount'] );
	}

	public function test_checkbox_selections_sum() {
		$form = $this->form( [ $this->itemsField( 'payment-checkbox' ) ] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => [ 'small', 'large' ] ] );

		$this->assertSame( 35.0, $result['amount'] );
	}

	public function test_unknown_item_value_is_a_field_error() {
		$form = $this->form( [ $this->itemsField( 'payment-multiple' ) ] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'forged-item' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'field_items', $result->get_error_data() );
	}

	public function test_fixed_coupon_is_applied() {
		$form = $this->form( [
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5.00' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'large', 'field_coupon' => 'save5' ] );

		// Case-insensitive match, fixed discount.
		$this->assertSame( 20.0, $result['amount'] );
		$this->assertSame( 'SAVE5', $result['coupon'] );
	}

	public function test_percent_coupon_is_applied() {
		$form = $this->form( [
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'HALF', 'type' => 'percent', 'value' => '50' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'small', 'field_coupon' => 'HALF' ] );

		$this->assertSame( 5.0, $result['amount'] );
	}

	public function test_unknown_coupon_is_a_field_error() {
		$form = $this->form( [
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'small', 'field_coupon' => 'NOPE' ] );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertArrayHasKey( 'field_coupon', $result->get_error_data() );
	}

	public function test_empty_coupon_field_is_fine() {
		$form = $this->form( [
			$this->itemsField( 'payment-multiple' ),
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_items' => 'small', 'field_coupon' => '' ] );

		$this->assertSame( 10.0, $result['amount'] );
		$this->assertNull( $result['coupon'] );
	}

	public function test_amount_never_goes_negative() {
		$form = $this->form( [
			[ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ],
			[ 'id' => 'field_coupon', 'type' => 'coupon', 'label' => 'Coupon', 'coupons' => [
				[ 'code' => 'BIG', 'type' => 'fixed', 'value' => '100' ],
			] ],
		] );

		$result = ( new PaymentTotals() )->compute( $form, [ 'field_coupon' => 'BIG' ] );

		$this->assertSame( 0.0, $result['amount'] );
	}

	public function test_currency_comes_from_settings() {
		$GLOBALS['fta_test_options']['fta_settings'] = [ 'currency' => 'EUR' ];
		$form = $this->form( [ [ 'id' => 'field_fee', 'type' => 'payment-single', 'label' => 'Fee', 'price' => '5.00' ] ] );

		$result = ( new PaymentTotals() )->compute( $form, [] );

		$this->assertSame( 'EUR', $result['currency'] );
	}

	public function test_find_coupon_matches_case_insensitively() {
		$field = [ 'coupons' => [ [ 'code' => 'Save5', 'type' => 'fixed', 'value' => '5' ] ] ];

		$this->assertSame(
			[ 'code' => 'Save5', 'type' => 'fixed', 'value' => 5.0 ],
			PaymentTotals::find_coupon( $field, 'sAvE5' )
		);
		$this->assertNull( PaymentTotals::find_coupon( $field, 'other' ) );
		$this->assertNull( PaymentTotals::find_coupon( [], 'Save5' ) );
	}
}
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `vendor/bin/phpunit --filter PaymentTotalsTest`
Expected: ERROR — class not found

- [ ] **Step 3: Implement the class**

Create `src/Frontend/PaymentTotals.php`:

```php
<?php
/**
 * PaymentTotals Class
 *
 * Recomputes the authoritative order amount on submission. Prices come
 * exclusively from the form definition; whatever totals or prices the
 * browser posted are never consulted. No gateway is involved - the
 * result is recorded with the entry, nothing is charged.
 *
 * @package Formtura
 * @since 1.0.4
 */

namespace Formtura\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PaymentTotals class.
 */
class PaymentTotals {

	/**
	 * Field types that contribute priced items.
	 *
	 * @var string[]
	 */
	const ITEM_TYPES = [ 'payment-single', 'payment-checkbox', 'payment-multiple', 'payment-dropdown' ];

	/**
	 * Whether a form contains any payment fields.
	 *
	 * @since 1.0.4
	 * @param array $form Form data.
	 * @return bool
	 */
	public function form_has_payment_fields( $form ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['type'] ) && in_array( $field['type'], self::ITEM_TYPES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compute the order from the form definition and the submission.
	 *
	 * @since 1.0.4
	 * @param array $form       Form data.
	 * @param array $submission Raw submission (typically $_POST).
	 * @return array|\WP_Error Order data, or WP_Error with per-field messages.
	 */
	public function compute( $form, $submission ) {
		$items       = [];
		$errors      = [];
		$coupon      = null;
		$coupon_code = null;

		foreach ( $form['fields'] as $field ) {
			$type       = isset( $field['type'] ) ? $field['type'] : '';
			$field_name = fta_get_field_name( $field );

			if ( '' === $field_name ) {
				continue;
			}

			if ( 'payment-single' === $type ) {
				$items[] = [
					'label' => isset( $field['label'] ) ? (string) $field['label'] : '',
					'price' => isset( $field['price'] ) && is_numeric( $field['price'] ) ? (float) $field['price'] : 0.0,
				];

				continue;
			}

			if ( in_array( $type, [ 'payment-checkbox', 'payment-multiple', 'payment-dropdown' ], true ) ) {
				$submitted = isset( $submission[ $field_name ] ) ? $submission[ $field_name ] : [];
				$submitted = is_array( $submitted ) ? $submitted : [ $submitted ];
				$defined   = fta_get_field_items( $field );

				foreach ( $submitted as $value ) {
					$value = trim( (string) $value );

					if ( '' === $value ) {
						continue;
					}

					$match = null;

					foreach ( $defined as $item ) {
						if ( $item['value'] === $value ) {
							$match = $item;
							break;
						}
					}

					// A value outside the definition is a forged request,
					// not a pricing decision.
					if ( null === $match ) {
						$errors[ $field_name ] = __( 'Invalid selection.', FORMTURA_TEXTDOMAIN );
						break;
					}

					$items[] = [
						'label' => $match['label'],
						'price' => $match['price'],
					];
				}

				continue;
			}

			if ( 'coupon' === $type ) {
				$code = isset( $submission[ $field_name ] ) ? trim( (string) $submission[ $field_name ] ) : '';

				if ( '' === $code ) {
					continue;
				}

				$found = self::find_coupon( $field, $code );

				if ( null === $found ) {
					$errors[ $field_name ] = __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN );
					continue;
				}

				$coupon      = $found;
				$coupon_code = $found['code'];
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'payment_invalid',
				__( 'Please correct the errors below.', FORMTURA_TEXTDOMAIN ),
				$errors
			);
		}

		$amount = 0.0;

		foreach ( $items as $item ) {
			$amount += $item['price'];
		}

		if ( null !== $coupon ) {
			$amount -= 'percent' === $coupon['type'] ? $amount * $coupon['value'] / 100 : $coupon['value'];
		}

		return [
			'amount'   => round( max( 0.0, $amount ), 2 ),
			'currency' => (string) fta_get_setting( 'currency', 'USD' ),
			'items'    => $items,
			'coupon'   => $coupon_code,
		];
	}

	/**
	 * Look a code up in a coupon field's definition.
	 *
	 * Case-insensitive. Returns the stored casing so the entry records the
	 * code as the author wrote it.
	 *
	 * @since 1.0.4
	 * @param array  $field Coupon field configuration.
	 * @param string $code  Code as entered by the visitor.
	 * @return array|null [ 'code', 'type', 'value' ] or null when unknown.
	 */
	public static function find_coupon( $field, $code ) {
		$coupons = isset( $field['coupons'] ) && is_array( $field['coupons'] ) ? $field['coupons'] : [];

		foreach ( $coupons as $coupon ) {
			if ( ! is_array( $coupon ) || ! isset( $coupon['code'] ) ) {
				continue;
			}

			if ( 0 !== strcasecmp( trim( (string) $coupon['code'] ), trim( $code ) ) ) {
				continue;
			}

			return [
				'code'  => (string) $coupon['code'],
				'type'  => isset( $coupon['type'] ) && 'percent' === $coupon['type'] ? 'percent' : 'fixed',
				'value' => isset( $coupon['value'] ) && is_numeric( $coupon['value'] ) ? (float) $coupon['value'] : 0.0,
			];
		}

		return null;
	}
}
```

- [ ] **Step 4: Wire into the submission flow**

In `src/Frontend/Submission.php` `ajax_submit_form()`, after the signatures merge loop and before `fta_create_entry`, add:

```php
		// Payment forms carry an authoritative server-side computed order.
		$payments = new PaymentTotals();

		if ( $payments->form_has_payment_fields( $form ) ) {
			$payment = $payments->compute( $form, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

			if ( is_wp_error( $payment ) ) {
				wp_send_json_error( [
					'message' => $payment->get_error_message(),
					'errors'  => $payment->get_error_data(),
				] );
			}

			// Reserved key: field names are field_<timestamp>_<suffix>, so
			// _payment cannot collide with real field data.
			$entry_data['_payment'] = $payment;
		}
```

In `sanitize_submission()` only, extend the skip so the client's total value never lands in entry data (validation still runs required checks on nothing extra since total is never required):

```php
			$skip_type = isset( $field['type'] ) ? $field['type'] : '';

			if ( Uploads::is_file_field( $field ) || in_array( $skip_type, [ 'signature', 'total' ], true ) ) {
				continue;
			}
```

`wp_unslash` on an array needs the stub to recurse; update the `wp_unslash` stub in `tests/wp-stubs.php`:

```php
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}
```

(Replace the existing non-recursive stub added during the reCAPTCHA work.)

- [ ] **Step 5: Run tests, then commit**

Run: `vendor/bin/phpunit && npx jest`
Expected: PASS

```bash
git add src/Frontend/PaymentTotals.php src/Frontend/Submission.php tests/Unit/Frontend/PaymentTotalsTest.php tests/wp-stubs.php
git commit -m "Recompute payment totals server-side and store them with the entry

The amount stored under the entry's _payment key derives only from the
form definition and validated selections: unknown item values and unknown
coupon codes are field errors, discounts floor at zero, and the total the
browser posted is never consulted. Nothing is charged - no gateway exists.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 11: coupon field end to end

**Files:**
- Create: `templates/fields/coupon.php`
- Modify: `src/Frontend/Submission.php` (AJAX action `fta_validate_coupon`), `assets/js/frontend.js` (apply flow), `src/Frontend/Frontend.php` (strings)
- Modify: `builder/components/FormBuilder.jsx`, `builder/components/FieldLibrary.jsx` (coupon codes editor), `builder/components/FieldPreview.jsx`
- Test: `tests/js/frontend-coupon.test.js` (create), `tests/Unit/Templates/FieldTemplateTest.php` (provider row + no-leak test)

**Interfaces:**
- Consumes: `PaymentTotals::find_coupon()` (Task 10), `recalculateTotal($form)` and `$form.data('ftaCoupon', ...)` (Task 9)
- Produces: AJAX action `fta_validate_coupon` (`nonce` = `formtura_frontend`, `form_id`, `field_id`, `code`) → success `{ type, value, code }` / error `{ message }`. Markup: `.fta-coupon-input` text input (named by the field), `.fta-coupon-apply` button, `.fta-coupon-status` span.

- [ ] **Step 1: Write the failing PHP tests**

Provider row:

```php
			'coupon'        => [ 'coupon', [] ],
```

And a leak test after the payment tests:

```php
	public function test_coupon_codes_never_reach_the_page() {
		$html = $this->render( $this->field( 'coupon', [
			'coupons' => [ [ 'code' => 'SECRET50', 'type' => 'percent', 'value' => '50' ] ],
		] ) );

		$this->assertStringNotContainsString( 'SECRET50', $html );
		$this->assertStringContainsString( 'fta-coupon-input', $html );
	}
```

Run: `vendor/bin/phpunit --filter FieldTemplateTest` — expect FAIL.

- [ ] **Step 2: Create the template**

`templates/fields/coupon.php`:

```php
<?php
/**
 * Coupon Field Template
 *
 * Code entry plus an Apply control. The defined codes never render here -
 * Apply asks the server over AJAX, and the submission re-validates the
 * code independently (Frontend\PaymentTotals).
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-coupon' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-coupon" data-field-id="<?php echo esc_attr( isset( $field['id'] ) ? $field['id'] : '' ); ?>">
		<input
			type="text"
			id="<?php echo esc_attr( $field_input_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="fta-field-input fta-coupon-input"
			placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : __( 'Coupon code', FORMTURA_TEXTDOMAIN ) ); ?>"
		/>
		<button type="button" class="fta-coupon-apply">
			<?php esc_html_e( 'Apply', FORMTURA_TEXTDOMAIN ); ?>
		</button>
	</div>

	<span class="fta-coupon-status" role="status"></span>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-coupon -->
```

Run: `vendor/bin/phpunit` — expect PASS.

- [ ] **Step 3: Add the AJAX endpoint**

In `src/Frontend/Submission.php` `init_hooks()`:

```php
		// Coupon validation for display-side totals. The submission path
		// re-validates independently; this endpoint only prevents the page
		// from ever carrying the code list.
		add_action( 'wp_ajax_fta_validate_coupon', [ $this, 'ajax_validate_coupon' ] );
		add_action( 'wp_ajax_nopriv_fta_validate_coupon', [ $this, 'ajax_validate_coupon' ] );
```

New method after `ajax_submit_form()`:

```php
	/**
	 * AJAX: validate a coupon code for display-side totals.
	 *
	 * @since 1.0.4
	 */
	public function ajax_validate_coupon() {
		check_ajax_referer( 'formtura_frontend', 'nonce' );

		$form_id  = isset( $_POST['form_id'] ) ? absint( $_POST['form_id'] ) : 0;
		$field_id = isset( $_POST['field_id'] ) ? sanitize_text_field( wp_unslash( $_POST['field_id'] ) ) : '';
		$code     = isset( $_POST['code'] ) ? sanitize_text_field( wp_unslash( $_POST['code'] ) ) : '';

		$form = $form_id ? fta_get_form( $form_id ) : null;

		if ( ! $form || '' === $field_id || '' === $code ) {
			wp_send_json_error( [
				'message' => __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$coupon = null;

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['id'], $field['type'] ) && 'coupon' === $field['type'] && $field['id'] === $field_id ) {
				$coupon = PaymentTotals::find_coupon( $field, $code );
				break;
			}
		}

		if ( null === $coupon ) {
			wp_send_json_error( [
				'message' => __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		wp_send_json_success( $coupon );
	}
```

- [ ] **Step 4: Write the failing jest tests**

Create `tests/js/frontend-coupon.test.js`:

```js
/**
 * Coupon apply flow tests for assets/js/frontend.js.
 *
 * Codes live server-side only; Apply round-trips through AJAX and the
 * validated discount adjusts the displayed total.
 */

const fs = require('fs');
const path = require('path');

const FRONTEND_JS = fs.readFileSync(
	path.join(__dirname, '..', '..', 'assets', 'js', 'frontend.js'),
	'utf8'
);

const jQuery = require('jquery');
window.jQuery = jQuery;
window.$ = jQuery;

function loadFrontend() {
	window.formturaFrontend = {
		ajaxUrl: '/wp-admin/admin-ajax.php',
		nonce: 'test-nonce',
		recaptcha: null,
		currency: { symbol: '$' },
		strings: {
			couponApplied: 'Coupon applied.',
			couponInvalid: 'This coupon code is not valid.',
		},
	};

	// eslint-disable-next-line no-eval
	window.eval(FRONTEND_JS);

	return new Promise(resolve => jQuery(document).ready(resolve));
}

function renderForm() {
	document.body.innerHTML = `
		<form class="fta-form" data-form-id="7">
			<div class="fta-field fta-field-payment fta-field-payment-single">
				<input type="hidden" name="field_fee" value="1" class="fta-payment-input" data-price="20.00" data-item-label="Fee">
			</div>
			<div class="fta-field fta-field-coupon">
				<div class="fta-coupon" data-field-id="field_coupon">
					<input type="text" name="field_coupon" class="fta-field-input fta-coupon-input">
					<button type="button" class="fta-coupon-apply">Apply</button>
				</div>
				<span class="fta-coupon-status" role="status"></span>
			</div>
			<div class="fta-field fta-field-total">
				<div class="fta-total-display"><span class="fta-total-amount">$0.00</span></div>
				<input type="hidden" name="field_total" class="fta-total-input" value="0">
			</div>
		</form>
	`;
}

describe('coupon apply flow', () => {
	let ajaxMock;

	beforeEach(() => {
		jQuery(document).off();
		document.body.innerHTML = '';
		ajaxMock = jest.fn();
		jQuery.ajax = ajaxMock;
		window.HTMLElement.prototype.scrollIntoView = jest.fn();
	});

	test('a valid code discounts the displayed total', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-input').val('SAVE5');
		jQuery('.fta-coupon-apply').trigger('click');

		expect(ajaxMock).toHaveBeenCalledTimes(1);
		const request = ajaxMock.mock.calls[0][0];
		expect(request.data.action).toBe('fta_validate_coupon');
		expect(request.data.code).toBe('SAVE5');
		expect(request.data.field_id).toBe('field_coupon');

		request.success({ success: true, data: { code: 'SAVE5', type: 'fixed', value: 5 } });

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$15.00');
		expect(document.querySelector('.fta-coupon-status').textContent).toContain('Coupon applied.');
	});

	test('an invalid code shows the error and leaves the total alone', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-input').val('NOPE');
		jQuery('.fta-coupon-apply').trigger('click');

		ajaxMock.mock.calls[0][0].success({ success: false, data: { message: 'This coupon code is not valid.' } });

		expect(document.querySelector('.fta-total-amount').textContent).toBe('$20.00');
		expect(document.querySelector('.fta-coupon-status').textContent).toContain('not valid');
	});

	test('an empty code does not fire a request', async () => {
		renderForm();
		await loadFrontend();

		jQuery('.fta-coupon-apply').trigger('click');

		expect(ajaxMock).not.toHaveBeenCalled();
	});
});
```

Run: `npx jest tests/js/frontend-coupon.test.js` — expect FAIL (no click handler).

- [ ] **Step 5: Implement the apply flow**

In `assets/js/frontend.js` `bindEvents()`, add:

```js
			// Coupon apply
			$(document).on('click', '.fta-coupon-apply', this.handleCouponApply);
```

Add the handler method:

```js
		/**
		 * Validate a coupon code over AJAX and apply it to the display.
		 *
		 * The submission re-validates the code server-side regardless; this
		 * only keeps the displayed total honest without ever shipping the
		 * code list to the page.
		 */
		handleCouponApply() {
			const $button = $(this);
			const $wrap = $button.closest('.fta-coupon');
			const $form = $button.closest('.fta-form');
			const $status = $button.closest('.fta-field').find('.fta-coupon-status');
			const strings = (window.formturaFrontend && formturaFrontend.strings) || {};
			const code = String($wrap.find('.fta-coupon-input').val() || '').trim();

			if (!code) {
				return;
			}

			$button.prop('disabled', true);

			$.ajax({
				url: formturaFrontend.ajaxUrl,
				type: 'POST',
				data: {
					action: 'fta_validate_coupon',
					nonce: formturaFrontend.nonce,
					form_id: $form.data('form-id'),
					field_id: $wrap.data('field-id'),
					code,
				},
				success(response) {
					if (response.success) {
						$form.data('ftaCoupon', {
							type: response.data.type,
							value: parseFloat(response.data.value) || 0,
						});
						$status.text(strings.couponApplied || 'Coupon applied.');
					} else {
						$form.removeData('ftaCoupon');
						$status.text((response.data && response.data.message) || strings.couponInvalid || 'This coupon code is not valid.');
					}
					FormturaFrontend.recalculateTotal($form);
				},
				error() {
					$status.text(strings.couponInvalid || 'This coupon code is not valid.');
				},
				complete() {
					$button.prop('disabled', false);
				}
			});
		},
```

In `src/Frontend/Frontend.php` localized `strings`, add:

```php
						'couponApplied'    => __( 'Coupon applied.', FORMTURA_TEXTDOMAIN ),
						'couponInvalid'    => __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN ),
```

- [ ] **Step 6: Builder support**

`createField()`:

```jsx
      case 'coupon':
        return { ...baseField, label: 'Coupon', coupons: [] };
```

`getDefaultLabel()` map: `'coupon': 'Coupon',`

In `FieldLibrary.jsx` GeneralTab `renderFieldSpecificOptions()`, next to the payment blocks:

```jsx
    // Coupon codes
    if (field.type === 'coupon') {
      const coupons = field.coupons || [];
      const setCoupons = (next) => handleChange('coupons', next);

      return (
        <div className="formtura-form-group">
          <label>
            Coupon Codes <Tooltip text="Codes are validated on the server and are never shown to visitors." />
          </label>

          {coupons.map((coupon, index) => (
            <div key={index} className="formtura-coupon-row">
              <input
                type="text"
                placeholder="CODE"
                value={coupon.code || ''}
                onChange={(e) => {
                  const next = [...coupons];
                  next[index] = { ...next[index], code: e.target.value };
                  setCoupons(next);
                }}
              />
              <select
                value={coupon.type || 'fixed'}
                onChange={(e) => {
                  const next = [...coupons];
                  next[index] = { ...next[index], type: e.target.value };
                  setCoupons(next);
                }}
              >
                <option value="fixed">Fixed amount</option>
                <option value="percent">Percent</option>
              </select>
              <input
                type="number"
                min="0"
                step="0.01"
                placeholder="Value"
                className="formtura-price-input"
                value={coupon.value || ''}
                onChange={(e) => {
                  const next = [...coupons];
                  next[index] = { ...next[index], value: e.target.value };
                  setCoupons(next);
                }}
              />
              <button
                type="button"
                onClick={() => setCoupons(coupons.filter((_, i) => i !== index))}
              >
                ×
              </button>
            </div>
          ))}

          <button
            type="button"
            className="formtura-btn formtura-btn-secondary"
            onClick={() => setCoupons([...coupons, { code: '', type: 'fixed', value: '' }])}
          >
            Add Coupon
          </button>
        </div>
      );
    }
```

`FieldPreview.jsx` before `default:`:

```jsx
      case 'coupon':
        return (
          <div className="formtura-coupon-preview">
            <input type="text" placeholder={field.placeholder || 'Coupon code'} readOnly />
            <button type="button" onClick={(e) => e.stopPropagation()}>Apply</button>
          </div>
        );
```

- [ ] **Step 7: Run both suites, then commit**

Run: `npx jest && vendor/bin/phpunit`
Expected: PASS

```bash
git add templates/fields/coupon.php src/Frontend/Submission.php src/Frontend/Frontend.php assets/js/frontend.js builder/components/FormBuilder.jsx builder/components/FieldLibrary.jsx builder/components/FieldPreview.jsx tests/js/frontend-coupon.test.js tests/Unit/Templates/FieldTemplateTest.php
git commit -m "Add the coupon field with server-held codes

Codes are defined per-field in the builder and never render into the
page. Apply validates over a nonced AJAX endpoint and discounts the
displayed total; the submission path re-validates the code independently
through PaymentTotals, so the display flow grants nothing.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

### Task 12: documentation and final verification

**Files:**
- Modify: `doc/CHECKLIST.md` (Field Type Coverage section), `readme.txt` (field list + changelog)
- Modify: `docs/superpowers/specs/2026-08-07-missing-field-types-design.md` (status line)

- [ ] **Step 1: Update the checklist**

In `doc/CHECKLIST.md`, rewrite the Field Type Coverage section:

- Heading count line becomes: palette offers **38**, **29 render on the frontend**, 9 remaining.
- Move to the rendering list: `content`, `section-divider`, `rich-text`, `address`, `camera`, `signature`, `payment-single`, `payment-checkbox`, `payment-multiple`, `payment-dropdown`, `coupon`, `total` (12 additions; note that payment amounts are recorded with entries, not charged).
- Remaining "no frontend template" list becomes:
  - `repeater` — blocked on builder nesting support (the builder saves `children: []` with no way to fill it)
  - `page-break`, `entry-preview`, `layout` — blocked on the multi-page subsystem
  - `paypal`, `stripe`, `square`, `authorize-net` — payment gateways; whole integrations, not templates
- Remove `captcha` from the missing list with a note: resolved form-wide via reCAPTCHA settings (1.0.4); the palette item opens an info dialog by design.

- [ ] **Step 2: Update readme.txt**

Add the new types to the field list (12 additions matching the checklist move) and a changelog entry:

```
= 1.0.4 =
* Fixed reCAPTCHA: tokens are now generated and verified end to end; v2 and v3 both work, and v3 score/action checking was added
* Added 12 field types to the frontend: Content, Section Divider, Rich Text, Address, Camera, Signature, Single Item, Checkbox Items, Multiple Items, Dropdown Items, Coupon and Total
* Payment fields record order amounts with the entry - totals are computed on the server from the form definition. No payment is collected; gateway integrations are not yet available
* Signature pads store drawn signatures as protected image files, like uploads
```

(Do not bump version numbers in plugin headers here — that is a release step, not part of this cycle.)

- [ ] **Step 3: Mark the spec implemented**

In the spec file, change the Status line to `**Status:** Implemented (see docs/superpowers/plans/2026-08-07-missing-field-types.md)`.

- [ ] **Step 4: Full verification**

Run: `vendor/bin/phpunit && npx jest && for f in templates/fields/*.php src/Frontend/*.php src/Functions.php; do php -l "$f" >/dev/null || echo "LINT FAIL: $f"; done`
Expected: both suites PASS, no lint failures. Also run `ls templates/fields/ | wc -l` — expect 31 files (19 existing + 12 new).

- [ ] **Step 5: Commit**

```bash
git add doc/CHECKLIST.md readme.txt docs/superpowers/specs/2026-08-07-missing-field-types-design.md
git commit -m "Document the 12 field types now rendering end to end

Coverage moves from 17/38 to 29/38. The remaining nine are grouped by
what actually blocks them: repeater on builder nesting, three on the
multi-page subsystem, and four payment gateways that are integrations
rather than templates. Captcha leaves the missing list - protection is
form-wide via the reCAPTCHA settings since 1.0.4.

Co-Authored-By: Claude Fable 5 <noreply@anthropic.com>"
```

---

## Self-Review Notes

- **Spec coverage:** every spec section maps to a task — §1 presentational → Tasks 2–3; §2 address → Task 4; §3 camera → Task 5; §4 signature → Tasks 6–7; §5 payments → Tasks 8–11 (price model Task 8, display JS Task 9, recompute invariant Task 10, coupon Task 11, `fta_format_price` Task 1); §6 builder additions are folded into each field's task; §7 error handling is embedded in Tasks 6, 10, 11; §8 testing is per-task; §9 docs → Task 12.
- **Deviation from spec, documented:** signature PNG verification uses magic-byte checking rather than `wp_check_filetype_and_ext` on a temp file — equivalent guarantee for a fixed single format, and testable without WordPress. The spec's intent (verify content, not claimed mime) is preserved.
- **Type consistency check:** `fta_get_field_items` (Tasks 1/8/10), `.fta-payment-input`/`data-price`/`data-item-label` (Tasks 8/9), `$form.data('ftaCoupon', {type, value})` (Tasks 9/11), `Uploads::is_file_field` (Tasks 5/6), `formturaInitSignaturePads` (Task 7), `PaymentTotals::find_coupon` (Tasks 10/11) — names verified identical across tasks.
