<?php
/**
 * Field template rendering tests.
 *
 * Guards the contract between the React builder and the frontend templates:
 * every field type offered in the builder must render, and every input must
 * post under the key the submission handler reads.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Templates;

use Formtura\Tests\TestCase;

class FieldTemplateTest extends TestCase {

	/**
	 * Render a field template and return its markup.
	 *
	 * @param array $field Field configuration.
	 * @return string Rendered HTML.
	 */
	private function render( array $field ) {
		ob_start();
		fta_get_template_part( 'fields/' . $field['type'], '', [ 'field' => $field ] );

		return (string) ob_get_clean();
	}

	/**
	 * Build a field definition the way the builder saves one.
	 *
	 * @param string $type  Field type.
	 * @param array  $extra Extra properties.
	 * @return array Field configuration.
	 */
	private function field( $type, array $extra = [] ) {
		return array_merge(
			[
				'id'       => 'field_1699_abc',
				'type'     => $type,
				'label'    => 'Test Label',
				'required' => false,
			],
			$extra
		);
	}

	/**
	 * Every type the builder can produce an input for must have a template.
	 *
	 * @return array[]
	 */
	public function inputFieldTypeProvider() {
		// Choice-based fields are always seeded with options by the builder, so
		// they are exercised the same way here.
		$choices = [
			'choices' => [
				[ 'label' => 'First Choice', 'value' => '1' ],
				[ 'label' => 'Second Choice', 'value' => '2' ],
			],
		];

		$payment_items = [
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00', 'isDefault' => false ],
				[ 'label' => 'Large', 'value' => 'large', 'price' => '25.00', 'isDefault' => false ],
			],
		];

		return [
			'text'          => [ 'text', [] ],
			'email'         => [ 'email', [] ],
			'textarea'      => [ 'textarea', [] ],
			'number'        => [ 'number', [] ],
			'select'        => [ 'select', $choices ],
			'radio'         => [ 'radio', $choices ],
			'checkbox'      => [ 'checkbox', $choices ],
			'checkboxes'    => [ 'checkboxes', $choices ],
			'name'          => [ 'name', [] ],
			'address'       => [ 'address', [] ],
			'phone'         => [ 'phone', [] ],
			'website'       => [ 'website', [] ],
			'password'      => [ 'password', [] ],
			'datetime'      => [ 'datetime', [] ],
			'date'          => [ 'date', [] ],
			'number-slider' => [ 'number-slider', [] ],
			'rating'        => [ 'rating', [] ],
			'hidden'        => [ 'hidden', [] ],
			'file-upload'   => [ 'file-upload', [] ],
			'camera'        => [ 'camera', [] ],
			'rich-text'     => [ 'rich-text', [] ],
			'signature'     => [ 'signature', [] ],
			'payment-single'   => [ 'payment-single', [ 'price' => '10.00' ] ],
			'payment-checkbox' => [ 'payment-checkbox', $payment_items ],
			'payment-multiple' => [ 'payment-multiple', $payment_items ],
			'payment-dropdown' => [ 'payment-dropdown', $payment_items ],
		];
	}

	/**
	 * @dataProvider inputFieldTypeProvider
	 * @param string $type  Field type.
	 * @param array  $extra Extra field properties.
	 */
	public function test_template_renders_markup( $type, array $extra ) {
		$html = $this->render( $this->field( $type, $extra ) );

		$this->assertNotSame( '', trim( $html ), "Field type '{$type}' rendered nothing." );
	}

	/**
	 * @dataProvider inputFieldTypeProvider
	 * @param string $type  Field type.
	 * @param array  $extra Extra field properties.
	 */
	public function test_input_posts_under_the_field_id( $type, array $extra ) {
		$field = $this->field( $type, $extra );
		$html  = $this->render( $field );

		$expected = fta_get_field_name( $field );

		$this->assertSame(
			'field_1699_abc',
			$expected,
			'Field name should fall back to the field id.'
		);

		$this->assertMatchesRegularExpression(
			'/name="' . preg_quote( $expected, '/' ) . '(\[[a-z0-9]*\])?"/',
			$html,
			"Field type '{$type}' does not post under its field id."
		);
	}

	/**
	 * The compact file input is visually hidden by CSS and has no delegated
	 * click handler of its own; only a native <label for="..."> can open it.
	 * That trigger must survive hideLabel, since fta_field_label() renders
	 * nothing in that case.
	 */
	public function test_camera_field_keeps_a_clickable_trigger_when_the_label_is_hidden() {
		$field = $this->field( 'camera', [ 'hideLabel' => true ] );
		$html  = $this->render( $field );

		$input_id = fta_get_field_input_id( $field );

		$this->assertMatchesRegularExpression(
			'/<label[^>]*for="' . preg_quote( $input_id, '/' ) . '"[^>]*>/',
			$html,
			'Camera field lost its clickable trigger when hideLabel was set.'
		);
	}

	public function test_missing_template_is_reported_not_silent() {
		$rendered = fta_get_template_part( 'fields/no-such-field', '', [ 'field' => [] ] );

		$this->assertFalse(
			$rendered,
			'fta_get_template_part() must report a missing template so callers can react.'
		);
	}

	public function test_select_renders_choices_and_marks_default() {
		$html = $this->render( $this->field( 'select', [
			'choices' => [
				[ 'label' => 'Alpha', 'value' => 'a', 'isDefault' => false ],
				[ 'label' => 'Beta', 'value' => 'b', 'isDefault' => true ],
			],
		] ) );

		$this->assertStringContainsString( 'value="a"', $html );
		$this->assertStringContainsString( 'Alpha', $html );
		$this->assertStringContainsString( 'Beta', $html );

		// Only the choice flagged isDefault carries the selected attribute.
		$this->assertMatchesRegularExpression( '/value="b"\s+selected="selected"/', $html );
		$this->assertDoesNotMatchRegularExpression( '/value="a"\s+selected="selected"/', $html );
	}

	public function test_select_supports_legacy_string_options() {
		$html = $this->render( $this->field( 'select', [
			'options' => [ 'Option 1', 'Option 2' ],
		] ) );

		$this->assertStringContainsString( 'Option 1', $html );
		$this->assertStringContainsString( 'value="Option 2"', $html );
	}

	public function test_multiple_select_posts_an_array() {
		$html = $this->render( $this->field( 'select', [
			'multipleSelection' => true,
			'choices'           => [ [ 'label' => 'A', 'value' => 'a' ] ],
		] ) );

		$this->assertStringContainsString( 'name="field_1699_abc[]"', $html );
		$this->assertStringContainsString( 'multiple', $html );
	}

	public function test_checkbox_posts_an_array_but_radio_does_not() {
		$choices = [ [ 'label' => 'A', 'value' => 'a' ], [ 'label' => 'B', 'value' => 'b' ] ];

		$checkbox = $this->render( $this->field( 'checkbox', [ 'choices' => $choices ] ) );
		$radio    = $this->render( $this->field( 'radio', [ 'choices' => $choices ] ) );

		$this->assertStringContainsString( 'name="field_1699_abc[]"', $checkbox );
		$this->assertStringContainsString( 'type="checkbox"', $checkbox );

		$this->assertStringContainsString( 'name="field_1699_abc"', $radio );
		$this->assertStringContainsString( 'type="radio"', $radio );
		$this->assertStringNotContainsString( 'name="field_1699_abc[]"', $radio );
	}

	/**
	 * `radio` is the single-answer field; `checkbox` is multi-answer. Before
	 * 1.0.3 the builder had these inverted.
	 */
	public function test_radio_renders_radio_inputs_only() {
		$html = $this->render( $this->field( 'radio', [
			'choices' => [ [ 'label' => 'A', 'value' => 'a' ] ],
		] ) );

		$this->assertStringContainsString( 'type="radio"', $html );
		$this->assertStringNotContainsString( 'type="checkbox"', $html );
	}

	/**
	 * Forms saved before 1.0.3 may still hold the `checkboxes` slug.
	 */
	public function test_legacy_checkboxes_slug_renders_checkboxes() {
		$html = $this->render( $this->field( 'checkboxes', [
			'choices' => [ [ 'label' => 'A', 'value' => 'a' ] ],
		] ) );

		$this->assertStringContainsString( 'type="checkbox"', $html );
		$this->assertStringContainsString( 'name="field_1699_abc[]"', $html );
	}

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

	public function test_name_field_formats_render_expected_parts() {
		$simple = $this->render( $this->field( 'name', [ 'format' => 'simple' ] ) );
		$this->assertStringContainsString( 'name="field_1699_abc"', $simple );

		$first_last = $this->render( $this->field( 'name', [ 'format' => 'first-last' ] ) );
		$this->assertStringContainsString( 'name="field_1699_abc[first]"', $first_last );
		$this->assertStringContainsString( 'name="field_1699_abc[last]"', $first_last );
		$this->assertStringNotContainsString( '[middle]', $first_last );

		$full = $this->render( $this->field( 'name', [ 'format' => 'first-middle-last' ] ) );
		$this->assertStringContainsString( 'name="field_1699_abc[middle]"', $full );
	}

	public function test_required_name_field_does_not_require_middle_name() {
		$html = $this->render( $this->field( 'name', [
			'format'   => 'first-middle-last',
			'required' => true,
		] ) );

		// Isolate the middle-name input.
		preg_match( '/<input[^>]*\[middle\][^>]*>/', $html, $matches );

		$this->assertNotEmpty( $matches, 'Middle name input not found.' );
		$this->assertStringNotContainsString( 'required', $matches[0] );
	}

	public function test_rating_renders_one_radio_per_star() {
		$html = $this->render( $this->field( 'rating', [ 'maxRating' => 7 ] ) );

		$this->assertSame( 7, substr_count( $html, 'type="radio"' ) );
		$this->assertStringContainsString( 'value="7"', $html );
	}

	public function test_slider_uses_configured_range_and_renders_default_readout() {
		$html = $this->render( $this->field( 'number-slider', [
			'minValue'     => 5,
			'maxValue'     => 50,
			'increment'    => 5,
			'defaultValue' => 25,
			'valueDisplay' => 'Picked: {value}',
		] ) );

		$this->assertStringContainsString( 'type="range"', $html );
		$this->assertStringContainsString( 'min="5"', $html );
		$this->assertStringContainsString( 'max="50"', $html );
		$this->assertStringContainsString( 'step="5"', $html );
		$this->assertStringContainsString( 'Picked: 25', $html );
	}

	public function test_hidden_field_renders_no_label_markup() {
		$html = $this->render( $this->field( 'hidden', [ 'value' => 'tracking-code' ] ) );

		$this->assertStringContainsString( 'type="hidden"', $html );
		$this->assertStringContainsString( 'value="tracking-code"', $html );
		$this->assertStringNotContainsString( '<label', $html );
	}

	public function test_html_field_outputs_content_and_no_input() {
		$html = $this->render( $this->field( 'html', [
			'content' => '<p>Terms of service</p>',
		] ) );

		$this->assertStringContainsString( 'Terms of service', $html );
		$this->assertStringNotContainsString( '<input', $html );
	}

	public function test_html_field_renders_nothing_when_empty() {
		$html = $this->render( $this->field( 'html', [ 'content' => '   ' ] ) );

		$this->assertSame( '', trim( $html ) );
	}

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

	public function test_label_is_omitted_when_hidden() {
		$shown  = $this->render( $this->field( 'text' ) );
		$hidden = $this->render( $this->field( 'text', [ 'hideLabel' => true ] ) );

		$this->assertStringContainsString( 'Test Label', $shown );
		$this->assertStringNotContainsString( '<label', $hidden );
	}

	public function test_required_flag_reaches_the_markup() {
		$html = $this->render( $this->field( 'text', [ 'required' => true ] ) );

		$this->assertStringContainsString( 'required', $html );
		$this->assertStringContainsString( 'fta-field-label required', $html );
	}

	public function test_custom_css_classes_land_on_the_wrapper() {
		$html = $this->render( $this->field( 'text', [ 'cssClasses' => 'my-class other-class' ] ) );

		$this->assertStringContainsString( 'my-class', $html );
		$this->assertStringContainsString( 'other-class', $html );
	}

	public function test_conditional_logic_is_emitted_for_the_frontend_script() {
		$html = $this->render( $this->field( 'text', [
			'conditionalLogic' => [
				'enabled' => true,
				'action'  => 'show',
				'match'   => 'all',
				'rules'   => [ [ 'field' => 'field_x', 'operator' => 'is', 'value' => 'yes' ] ],
			],
		] ) );

		$this->assertStringContainsString( 'data-conditional-logic=', $html );
	}

	public function test_conditional_logic_is_omitted_when_disabled() {
		$html = $this->render( $this->field( 'text', [
			'conditionalLogic' => [ 'enabled' => false ],
		] ) );

		$this->assertStringNotContainsString( 'data-conditional-logic=', $html );
	}

	/**
	 * The payload must survive only as inert text: no tag can be injected and
	 * no attribute can be broken out of. Escaped forms such as
	 * "&lt;img ... onerror=..." are harmless and expected.
	 */
	public function test_markup_escapes_hostile_field_values() {
		$html = $this->render( $this->field( 'text', [
			'label'       => '"><script>alert(1)</script>',
			'placeholder' => '"><img src=x onerror=alert(1)>',
			'cssClasses'  => '" onmouseover="alert(1)',
		] ) );

		// No injected tags.
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img', $html );

		// The angle brackets and quotes were neutralised.
		$this->assertStringContainsString( '&lt;script&gt;', $html );
		$this->assertStringContainsString( '&quot;', $html );

		// No attribute break-out. Parsing rather than pattern matching, because
		// an escaped "onerror=..." sitting inside a placeholder value is inert
		// and must not be mistaken for a live handler.
		$dom = new \DOMDocument();
		$this->assertTrue(
			$dom->loadHTML( '<div>' . $html . '</div>', LIBXML_NOERROR ),
			'Rendered markup should be parseable.'
		);

		foreach ( ( new \DOMXPath( $dom ) )->query( '//*' ) as $node ) {
			foreach ( $node->attributes as $attribute ) {
				$this->assertStringStartsNotWith(
					'on',
					strtolower( $attribute->nodeName ),
					sprintf( 'Injected event handler attribute "%s".', $attribute->nodeName )
				);
			}
		}
	}
}
