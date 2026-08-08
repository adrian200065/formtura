<?php
/**
 * Choice field type migration tests.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Database;

use Formtura\Database\Installer;
use Formtura\Tests\TestCase;

class MigrationTest extends TestCase {

	/**
	 * Build a field list from type slugs.
	 *
	 * @param string[] $types Field types.
	 * @return array[]
	 */
	private function fields( array $types ) {
		return array_map(
			function( $type, $index ) {
				return [ 'id' => 'f' . $index, 'type' => $type, 'label' => 'Field ' . $index ];
			},
			$types,
			array_keys( $types )
		);
	}

	/**
	 * Extract type slugs from a field list.
	 *
	 * @param array[] $fields Field list.
	 * @return string[]
	 */
	private function types( array $fields ) {
		return array_column( $fields, 'type' );
	}

	public function test_legacy_checkbox_becomes_radio() {
		$result = Installer::migrate_field_types( $this->fields( [ 'checkbox' ] ) );

		$this->assertSame( [ 'radio' ], $this->types( $result ) );
	}

	public function test_legacy_checkboxes_becomes_checkbox() {
		$result = Installer::migrate_field_types( $this->fields( [ 'checkboxes' ] ) );

		$this->assertSame( [ 'checkbox' ], $this->types( $result ) );
	}

	/**
	 * The two rules must not cascade: a `checkboxes` field becoming `checkbox`
	 * must not then be caught by the checkbox -> radio rule in the same pass.
	 */
	public function test_rules_do_not_cascade_within_one_pass() {
		$result = Installer::migrate_field_types(
			$this->fields( [ 'checkboxes', 'checkbox' ] )
		);

		$this->assertSame( [ 'checkbox', 'radio' ], $this->types( $result ) );
	}

	public function test_existing_radio_is_untouched() {
		$result = Installer::migrate_field_types( $this->fields( [ 'radio' ] ) );

		$this->assertSame( [ 'radio' ], $this->types( $result ) );
	}

	public function test_unrelated_types_are_untouched() {
		$types  = [ 'text', 'email', 'select', 'name', 'file-upload' ];
		$result = Installer::migrate_field_types( $this->fields( $types ) );

		$this->assertSame( $types, $this->types( $result ) );
	}

	public function test_other_field_properties_are_preserved() {
		$fields = [
			[
				'id'      => 'f1',
				'type'    => 'checkboxes',
				'label'   => 'Interests',
				'choices' => [ [ 'label' => 'News', 'value' => 'n' ] ],
			],
		];

		$result = Installer::migrate_field_types( $fields );

		$this->assertSame( 'Interests', $result[0]['label'] );
		$this->assertSame( [ [ 'label' => 'News', 'value' => 'n' ] ], $result[0]['choices'] );
	}

	public function test_fields_without_a_type_are_skipped() {
		$result = Installer::migrate_field_types( [ [ 'id' => 'f1' ] ] );

		$this->assertSame( [ [ 'id' => 'f1' ] ], $result );
	}

	public function test_empty_field_list_is_returned_unchanged() {
		$this->assertSame( [], Installer::migrate_field_types( [] ) );
	}

	/**
	 * Documents the hazard the caller must respect: applying the rewrite twice
	 * corrupts data, which is why migrated form ids are recorded.
	 */
	public function test_second_pass_would_corrupt_data() {
		$once  = Installer::migrate_field_types( $this->fields( [ 'checkboxes' ] ) );
		$twice = Installer::migrate_field_types( $once );

		$this->assertSame( [ 'checkbox' ], $this->types( $once ) );
		$this->assertSame( [ 'radio' ], $this->types( $twice ) );
	}
}
