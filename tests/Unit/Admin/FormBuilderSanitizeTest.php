<?php
/**
 * Round-trip tests for Form_Builder::sanitize_field_data().
 *
 * This is a strict key allowlist and the only save path
 * (ajax_save_form -> sanitize_form_data -> Forms_DB::update). A setting
 * missing its own branch here is silently discarded on every save,
 * regardless of how correct the rest of that feature is - which is exactly
 * how five tasks' worth of field settings (coupons, payment items/price,
 * the total field's summary toggle, the address scheme, and the entire
 * file-upload options panel) went missing without any test catching it.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Form_Builder;
use Formtura\Tests\TestCase;

class FormBuilderSanitizeTest extends TestCase {

	/**
	 * @var Form_Builder
	 */
	private $builder;

	protected function setUp(): void {
		parent::setUp();
		$this->builder = new Form_Builder();
	}

	/**
	 * Call the private sanitize_field_data( $field ).
	 *
	 * @param array $field Raw field data, as posted by the builder.
	 * @return array Sanitized field data.
	 */
	private function sanitize( array $field ) {
		$reflection = new \ReflectionMethod( Form_Builder::class, 'sanitize_field_data' );
		$reflection->setAccessible( true );

		return $reflection->invoke( $this->builder, $field );
	}

	/**
	 * Coupon field: the codes that make this task's whole feature work.
	 */
	public function test_coupons_round_trip_with_correct_types() {
		$result = $this->sanitize( [
			'id'      => 'field_coupon',
			'type'    => 'coupon',
			'coupons' => [
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => '5.5' ],
				[ 'code' => 'HALF', 'type' => 'percent', 'value' => '50' ],
			],
		] );

		$this->assertSame(
			[
				[ 'code' => 'SAVE5', 'type' => 'fixed', 'value' => 5.5 ],
				[ 'code' => 'HALF', 'type' => 'percent', 'value' => 50.0 ],
			],
			$result['coupons']
		);
	}

	/**
	 * An unexpected coupon `type` must fall back to 'fixed', not pass
	 * through verbatim - PaymentTotals::find_coupon() only recognises
	 * 'fixed' and 'percent', and the sanitizer must not invent a third state.
	 */
	public function test_coupon_with_unexpected_type_falls_back_to_fixed() {
		$result = $this->sanitize( [
			'coupons' => [
				[ 'code' => 'X', 'type' => 'surprise', 'value' => '1' ],
			],
		] );

		$this->assertSame( 'fixed', $result['coupons'][0]['type'] );
	}

	/**
	 * A non-array coupon entry (a crafted or corrupted POST) must be dropped,
	 * not coerced into a coupon with empty fields, and must not leave a gap
	 * in the array's keys - wp_json_encode() would turn a gap into a JSON
	 * object instead of an array.
	 */
	public function test_non_array_coupon_entry_is_dropped_without_a_key_gap() {
		$result = $this->sanitize( [
			'coupons' => [
				[ 'code' => 'FIRST', 'type' => 'fixed', 'value' => '1' ],
				'not-an-array',
				[ 'code' => 'THIRD', 'type' => 'fixed', 'value' => '3' ],
			],
		] );

		$this->assertSame( [ 0, 1 ], array_keys( $result['coupons'] ) );
		$this->assertSame( 'FIRST', $result['coupons'][0]['code'] );
		$this->assertSame( 'THIRD', $result['coupons'][1]['code'] );
	}

	/**
	 * The coupon value must not be clamped here - PaymentTotals::find_coupon()
	 * already clamps it, and duplicating that would create two places to
	 * keep in step. A negative value must survive the sanitizer as-is.
	 */
	public function test_coupon_value_is_not_clamped_by_the_sanitizer() {
		$result = $this->sanitize( [
			'coupons' => [ [ 'code' => 'NEG', 'type' => 'fixed', 'value' => '-50' ] ],
		] );

		$this->assertSame( -50.0, $result['coupons'][0]['value'] );
	}

	/**
	 * A nested array where the coupon value scalar belongs must not become
	 * a bogus 1.0 via a bare floatval() cast - the same is_numeric() guard
	 * already used for minFileSize/maxFileSize.
	 */
	public function test_coupon_value_as_a_nested_array_does_not_survive_as_a_number() {
		$result = $this->sanitize( [
			'coupons' => [ [ 'code' => 'X', 'type' => 'fixed', 'value' => [ 'nested' ] ] ],
		] );

		$this->assertSame( 0.0, $result['coupons'][0]['value'] );
	}

	/**
	 * A coupon code that is itself a nested array (a crafted or corrupted
	 * POST) must sanitize to an empty string, not trip a cast warning -
	 * exercises the wp-stubs.php sanitize_text_field() fix that mirrors real
	 * WordPress's array/object early-return.
	 */
	public function test_coupon_code_as_a_nested_array_sanitizes_to_empty_string() {
		$result = $this->sanitize( [
			'coupons' => [ [ 'code' => [ 'nested' => 'array' ], 'type' => 'fixed', 'value' => '5' ] ],
		] );

		$this->assertSame( '', $result['coupons'][0]['code'] );
	}

	/**
	 * Payment items (payment-checkbox, payment-multiple, payment-dropdown):
	 * every price an author sets must survive.
	 */
	public function test_items_round_trip_with_correct_types() {
		$result = $this->sanitize( [
			'items' => [
				[ 'label' => 'Small', 'value' => 'small', 'price' => '10.00', 'isDefault' => false ],
				[ 'label' => 'Large', 'value' => 'large', 'price' => '25.50', 'isDefault' => true ],
			],
		] );

		$this->assertSame(
			[
				[ 'label' => 'Small', 'value' => 'small', 'price' => 10.0, 'isDefault' => false ],
				[ 'label' => 'Large', 'value' => 'large', 'price' => 25.5, 'isDefault' => true ],
			],
			$result['items']
		);
	}

	/**
	 * A non-array item entry must be dropped without leaving a key gap, the
	 * same guarantee as coupons above.
	 */
	public function test_non_array_item_entry_is_dropped_without_a_key_gap() {
		$result = $this->sanitize( [
			'items' => [
				[ 'label' => 'A', 'value' => 'a', 'price' => '1' ],
				123,
				[ 'label' => 'B', 'value' => 'b', 'price' => '2' ],
			],
		] );

		$this->assertSame( [ 0, 1 ], array_keys( $result['items'] ) );
		$this->assertSame( 'a', $result['items'][0]['value'] );
		$this->assertSame( 'b', $result['items'][1]['value'] );
	}

	/**
	 * A nested array where an item's price scalar belongs must not become a
	 * bogus 1.0 via a bare floatval() cast.
	 */
	public function test_item_price_as_a_nested_array_does_not_survive_as_a_number() {
		$result = $this->sanitize( [
			'items' => [ [ 'label' => 'A', 'value' => 'a', 'price' => [ 'nested' ] ] ],
		] );

		$this->assertSame( 0.0, $result['items'][0]['price'] );
	}

	/**
	 * Single item price (payment-single).
	 */
	public function test_single_item_price_round_trips_as_a_float() {
		$result = $this->sanitize( [ 'price' => '19.99' ] );

		$this->assertSame( 19.99, $result['price'] );
	}

	/**
	 * A nested array where the single item price scalar belongs must not
	 * survive as a bogus 1.0.
	 */
	public function test_single_item_price_as_a_nested_array_does_not_survive_as_a_number() {
		$result = $this->sanitize( [ 'price' => [ 'nested' ] ] );

		$this->assertSame( 0.0, $result['price'] );
	}

	/**
	 * showPriceAfterLabels (payment-checkbox/multiple/dropdown display toggle).
	 */
	public function test_show_price_after_labels_round_trips_as_a_bool() {
		$this->assertTrue( $this->sanitize( [ 'showPriceAfterLabels' => true ] )['showPriceAfterLabels'] );
		$this->assertFalse( $this->sanitize( [ 'showPriceAfterLabels' => false ] )['showPriceAfterLabels'] );
	}

	/**
	 * enableSummary (total field's order summary toggle).
	 */
	public function test_enable_summary_round_trips_as_a_bool() {
		$this->assertTrue( $this->sanitize( [ 'enableSummary' => true ] )['enableSummary'] );
		$this->assertFalse( $this->sanitize( [ 'enableSummary' => false ] )['enableSummary'] );
	}

	/**
	 * Address scheme: only 'us' or 'international' are valid; anything else
	 * (a crafted request) falls back to 'us' rather than passing through.
	 */
	public function test_scheme_round_trips_and_rejects_unexpected_values() {
		$this->assertSame( 'international', $this->sanitize( [ 'scheme' => 'international' ] )['scheme'] );
		$this->assertSame( 'us', $this->sanitize( [ 'scheme' => 'us' ] )['scheme'] );
		$this->assertSame( 'us', $this->sanitize( [ 'scheme' => '<script>' ] )['scheme'] );
	}

	/**
	 * File upload options - pre-existing since 1.0.3, not introduced by the
	 * coupon task, but silently discarded by the same missing-branch defect.
	 */
	public function test_file_upload_options_round_trip_with_correct_types() {
		$result = $this->sanitize( [
			'allowMultiple'     => true,
			'attachToEmail'     => true,
			'allowedFileTypes'  => 'all',
			'specifiedTypes'    => 'pdf, doc, docx',
			'minFileSize'       => '0.5',
			'maxFileSize'       => '10',
			'uploadText'        => 'Drop a file here',
			'compactUploadText' => 'Choose File',
		] );

		$this->assertTrue( $result['allowMultiple'] );
		$this->assertTrue( $result['attachToEmail'] );
		$this->assertSame( 'all', $result['allowedFileTypes'] );
		$this->assertSame( 'pdf, doc, docx', $result['specifiedTypes'] );
		$this->assertSame( 0.5, $result['minFileSize'] );
		$this->assertSame( 10.0, $result['maxFileSize'] );
		$this->assertSame( 'Drop a file here', $result['uploadText'] );
		$this->assertSame( 'Choose File', $result['compactUploadText'] );
	}

	/**
	 * allowedFileTypes must be restricted to the values the builder itself
	 * emits (FieldLibrary.jsx: 'all' or 'specify') - an unexpected value
	 * falls back to 'specify', the builder's own default.
	 */
	public function test_allowed_file_types_rejects_unexpected_values() {
		$result = $this->sanitize( [ 'allowedFileTypes' => 'anything-goes' ] );

		$this->assertSame( 'specify', $result['allowedFileTypes'] );
	}

	/**
	 * A non-numeric file size (a nested array where a scalar belongs, or a
	 * hand-crafted string) must not survive as a bogus numeric-looking
	 * string - it is dropped to an empty string rather than cast, since
	 * (float) on an array trips a warning rather than a usable value.
	 */
	public function test_non_numeric_file_size_does_not_survive_as_a_number() {
		$result = $this->sanitize( [
			'minFileSize' => [ 'nested' => 'array' ],
			'maxFileSize' => 'not-a-number',
		] );

		$this->assertSame( '', $result['minFileSize'] );
		$this->assertSame( '', $result['maxFileSize'] );
	}

	/**
	 * Conditional logic round-trips with the fields Submission::
	 * validate_submission() and assets/js/frontend.js both key off of.
	 */
	public function test_conditional_logic_round_trips_with_correct_types() {
		$result = $this->sanitize( [
			'conditionalLogic' => [
				'enabled'    => true,
				'action'     => 'hide',
				'match'      => 'any',
				'conditions' => [
					[ 'field' => 'field_1', 'operator' => 'is', 'value' => 'yes' ],
				],
			],
		] );

		$this->assertSame(
			[
				'enabled'    => true,
				'action'     => 'hide',
				'match'      => 'any',
				'conditions' => [
					[ 'field' => 'field_1', 'operator' => 'is', 'value' => 'yes' ],
				],
			],
			$result['conditionalLogic']
		);
	}

	/**
	 * `action` and `match` are the only two values the frontend and server
	 * evaluators understand; an unexpected value must not pass through and
	 * silently mean something the evaluators don't implement.
	 */
	public function test_conditional_logic_action_and_match_fall_back_to_defaults() {
		$result = $this->sanitize( [
			'conditionalLogic' => [
				'enabled' => true,
				'action'  => 'delete-everything',
				'match'   => 'most',
			],
		] );

		$this->assertSame( 'show', $result['conditionalLogic']['action'] );
		$this->assertSame( 'all', $result['conditionalLogic']['match'] );
	}

	/**
	 * A condition with an operator outside the five the evaluators
	 * implement must be dropped, not stored - it would otherwise always
	 * evaluate to false silently instead of at least being visibly absent.
	 */
	public function test_conditional_logic_condition_with_unknown_operator_is_dropped() {
		$result = $this->sanitize( [
			'conditionalLogic' => [
				'enabled'    => true,
				'conditions' => [
					[ 'field' => 'field_1', 'operator' => 'is', 'value' => 'a' ],
					[ 'field' => 'field_2', 'operator' => 'sql_injection', 'value' => 'b' ],
				],
			],
		] );

		$this->assertCount( 1, $result['conditionalLogic']['conditions'] );
		$this->assertSame( 'field_1', $result['conditionalLogic']['conditions'][0]['field'] );
	}

	/**
	 * A condition missing its trigger field is meaningless - nothing to
	 * evaluate against - and must be dropped without leaving a key gap.
	 */
	public function test_conditional_logic_condition_without_a_field_is_dropped_without_a_key_gap() {
		$result = $this->sanitize( [
			'conditionalLogic' => [
				'enabled'    => true,
				'conditions' => [
					[ 'field' => 'field_1', 'operator' => 'is', 'value' => 'a' ],
					[ 'field' => '', 'operator' => 'is', 'value' => 'b' ],
					[ 'field' => 'field_3', 'operator' => 'is', 'value' => 'c' ],
				],
			],
		] );

		$this->assertSame( [ 0, 1 ], array_keys( $result['conditionalLogic']['conditions'] ) );
		$this->assertSame( 'field_1', $result['conditionalLogic']['conditions'][0]['field'] );
		$this->assertSame( 'field_3', $result['conditionalLogic']['conditions'][1]['field'] );
	}

	/**
	 * A trigger field value is free text sanitized with sanitize_text_field(),
	 * the same treatment every other free-text setting in this sanitizer
	 * gets - it must not survive a script tag verbatim.
	 */
	public function test_conditional_logic_condition_value_is_sanitized() {
		$result = $this->sanitize( [
			'conditionalLogic' => [
				'enabled'    => true,
				'conditions' => [
					[ 'field' => 'field_1', 'operator' => 'is', 'value' => '<script>alert(1)</script>' ],
				],
			],
		] );

		$this->assertSame( 'alert(1)', $result['conditionalLogic']['conditions'][0]['value'] );
	}

	/**
	 * The legacy snake_case key (`conditional_logic`, pre-dating the
	 * builder's camelCase settings) normalises to the same `conditionalLogic`
	 * key new saves use - see the identical submitButtonText/successMessage
	 * normalisation in sanitize_settings_data().
	 */
	public function test_legacy_snake_case_conditional_logic_normalises_to_camel_case() {
		$result = $this->sanitize( [
			'conditional_logic' => [
				'enabled'    => true,
				'action'     => 'show',
				'match'      => 'all',
				'conditions' => [
					[ 'field' => 'field_1', 'operator' => 'is', 'value' => 'yes' ],
				],
			],
		] );

		$this->assertArrayNotHasKey( 'conditional_logic', $result );
		$this->assertTrue( $result['conditionalLogic']['enabled'] );
		$this->assertSame( 'field_1', $result['conditionalLogic']['conditions'][0]['field'] );
	}

	/**
	 * Boolean toggles that sanitize_field_data() silently dropped entirely
	 * (no isset() branch at all) prior to the follow-up audit - confirmed
	 * dead in AUDIT_FINDINGS.md #1: builder writes them, sanitizer drops
	 * them, so they vanish on every save/reopen cycle.
	 */
	public function test_previously_dropped_boolean_toggles_round_trip() {
		$result = $this->sanitize( [
			'enableDisable'    => true,
			'branchingLogic'   => true,
			'autoResize'       => true,
			'collapsible'      => true,
			'deleteOnReplace'  => true,
			'dynamicDefault'   => true,
			'enableQuantity'   => true,
			'unique'           => true,
			'useIconChoices'   => true,
			'useImageChoices'  => true,
			'addOtherChoice'   => true,
		] );

		$this->assertTrue( $result['enableDisable'] );
		$this->assertTrue( $result['branchingLogic'] );
		$this->assertTrue( $result['autoResize'] );
		$this->assertTrue( $result['collapsible'] );
		$this->assertTrue( $result['deleteOnReplace'] );
		$this->assertTrue( $result['dynamicDefault'] );
		$this->assertTrue( $result['enableQuantity'] );
		$this->assertTrue( $result['unique'] );
		$this->assertTrue( $result['useIconChoices'] );
		$this->assertTrue( $result['useImageChoices'] );
		$this->assertTrue( $result['addOtherChoice'] );
	}

	/**
	 * branchTarget (page-navigation/skip-logic target) is free text tied to
	 * a hardcoded builder <select> ('', 'page-2', 'page-3', 'submit') - not
	 * a security boundary since it's only ever read back into that same
	 * select, so it gets the same sanitize_text_field() treatment as every
	 * other free-text setting rather than a brittle enum.
	 */
	public function test_branch_target_round_trips_as_sanitized_text() {
		$this->assertSame( 'page-2', $this->sanitize( [ 'branchTarget' => 'page-2' ] )['branchTarget'] );
		$this->assertSame(
			'alert(1)',
			$this->sanitize( [ 'branchTarget' => '<script>alert(1)</script>' ] )['branchTarget']
		);
	}

	/**
	 * addNewLabel / removeLabel (repeater button text) are free text.
	 */
	public function test_repeater_button_labels_round_trip_as_sanitized_text() {
		$result = $this->sanitize( [
			'addNewLabel' => 'Add Another',
			'removeLabel' => 'Remove This',
		] );

		$this->assertSame( 'Add Another', $result['addNewLabel'] );
		$this->assertSame( 'Remove This', $result['removeLabel'] );
	}

	/**
	 * repeatLayout is restricted to the three values the builder's own
	 * <select> can emit ('default', 'inline', 'grid'); anything else (a
	 * crafted request) falls back to 'default'.
	 */
	public function test_repeat_layout_round_trips_and_rejects_unexpected_values() {
		$this->assertSame( 'inline', $this->sanitize( [ 'repeatLayout' => 'inline' ] )['repeatLayout'] );
		$this->assertSame( 'grid', $this->sanitize( [ 'repeatLayout' => 'grid' ] )['repeatLayout'] );
		$this->assertSame( 'default', $this->sanitize( [ 'repeatLayout' => 'chaos' ] )['repeatLayout'] );
	}

	/**
	 * minRows / maxRows (repeater row limits) are non-negative integers
	 * posted from a number input that allows an empty value (no limit) -
	 * same is_numeric() guard as minFileSize/maxFileSize so an empty or
	 * non-numeric value survives as '' rather than a bogus 0.
	 */
	public function test_repeater_row_limits_round_trip_as_ints_or_empty_string() {
		$result = $this->sanitize( [ 'minRows' => '2', 'maxRows' => '5' ] );
		$this->assertSame( 2, $result['minRows'] );
		$this->assertSame( 5, $result['maxRows'] );

		$result = $this->sanitize( [ 'minRows' => '', 'maxRows' => '' ] );
		$this->assertSame( '', $result['minRows'] );
		$this->assertSame( '', $result['maxRows'] );
	}

	/**
	 * visibility (field-level "who can see this field" role gate) is a
	 * WordPress role slug, or the two builder-defined values 'everyone' /
	 * 'logged_in' - the role list is populated dynamically from
	 * window.formturaBuilderData.userRoles (Admin.php), so it can't be a
	 * fixed enum here; sanitize_key() is the correct treatment for any
	 * WP role slug.
	 */
	public function test_visibility_round_trips_as_a_sanitized_key() {
		$this->assertSame( 'logged_in', $this->sanitize( [ 'visibility' => 'logged_in' ] )['visibility'] );
		$this->assertSame( 'editor', $this->sanitize( [ 'visibility' => 'editor' ] )['visibility'] );
		$this->assertSame(
			'scriptalert1script',
			$this->sanitize( [ 'visibility' => '<script>alert(1)</script>' ] )['visibility']
		);
	}

	/**
	 * Coverage guard for the builder save path (Task 12).
	 *
	 * This cycle's actual defect was never a wrong value - it was a setting
	 * with no branch in sanitize_field_data() at all, so it vanished on save
	 * while every other test for that field type kept passing. This test
	 * builds one full field per new field type, runs it through the real
	 * sanitizer, and asserts each setting survives with the right PHP type. If
	 * a future change drops a branch, the failure message names the field type
	 * and the missing/mangled key directly.
	 *
	 * The field shapes come from tests/fixtures/builder-field-settings.json
	 * rather than being written out here, because a guard is only worth
	 * something if it tests shapes the builder can really produce. The version
	 * this replaced hand-wrote a `content` key for the content field that no
	 * builder control ever set: the guard passed while the field rendered
	 * nothing on the frontend, and it also asserted a `compactUploadText`
	 * setting for camera that the builder has no editor for at all.
	 *
	 * The other half of the guard lives in
	 * builder/components/__tests__/builderFieldSettings.test.jsx, which reads
	 * the same fixture and asserts createField() plus the field's options panel
	 * really produce every key in it. PHP cannot execute the React panel and
	 * Jest cannot run this sanitizer, so the fixture is what ties the two
	 * halves together - do not add a key here without adding it there.
	 */
	public function test_every_new_field_type_full_settings_survive_sanitize_field_data() {
		$cases = $this->builderFieldSettings();

		foreach ( $cases as $type => $settings ) {
			$field = [ 'type' => $type ];

			foreach ( $settings as $key => $values ) {
				$field[ $key ] = $values['posted'];
			}

			$result = $this->sanitize( $field );

			foreach ( $settings as $key => $values ) {
				$this->assertArrayHasKey(
					$key,
					$result,
					"[$type] sanitize_field_data() dropped the '$key' setting entirely - add a branch for it in Form_Builder::sanitize_field_data()."
				);
				$this->assertSame(
					$values['expected'],
					$result[ $key ],
					"[$type] sanitize_field_data() returned the wrong value/type for '$key'."
				);
			}
		}
	}

	/**
	 * The 12 field types the fixture must describe.
	 *
	 * Asserted explicitly so neither half of the guard can quietly shrink to a
	 * subset of the cycle's field types - the Jest half asserts the same list.
	 */
	public function test_the_shared_fixture_covers_every_new_field_type() {
		$types = array_keys( $this->builderFieldSettings() );
		sort( $types );

		$this->assertSame(
			[
				'address',
				'camera',
				'content',
				'coupon',
				'payment-checkbox',
				'payment-dropdown',
				'payment-multiple',
				'payment-single',
				'rich-text',
				'section-divider',
				'signature',
				'total',
			],
			$types
		);
	}

	/**
	 * The settings the builder can save, per field type.
	 *
	 * @return array<string, array<string, array{posted: mixed, expected: mixed}>>
	 */
	private function builderFieldSettings() {
		$path = dirname( __DIR__, 2 ) . '/fixtures/builder-field-settings.json';

		$this->assertFileExists( $path );

		$fixture = json_decode( (string) file_get_contents( $path ), true );

		$this->assertIsArray( $fixture );
		$this->assertArrayHasKey( 'types', $fixture );

		return $fixture['types'];
	}
}
