<?php
/**
 * Tests for the built-in template library's field definitions.
 *
 * Every field the React builder produces carries an `id`, which
 * fta_get_field_name() falls back to as the submission key (see
 * Functions.php). Templates that omit `id` are saved unchanged by
 * ajax_create_from_template(), so their rendered inputs get a blank
 * `name` attribute and validate_submission() silently skips them -
 * every field in every non-blank template becomes optional and
 * unrecoverable, no matter what the template says.
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Form_Templates;
use Formtura\Tests\TestCase;

/**
 * FormTemplatesTest class.
 */
class FormTemplatesTest extends TestCase {

	/**
	 * Templates instance under test.
	 *
	 * @var Form_Templates
	 */
	private $templates;

	/**
	 * Set up the test fixture.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->templates = new Form_Templates();
	}

	/**
	 * Non-blank template ids to check.
	 *
	 * @return array[]
	 */
	public function nonBlankTemplateProvider() {
		return array(
			'contact'         => array( 'contact' ),
			'quote'           => array( 'quote' ),
			'feedback'        => array( 'feedback' ),
			'registration'    => array( 'registration' ),
			'job_application' => array( 'job_application' ),
		);
	}

	/**
	 * Every field in a non-blank template must carry an id.
	 *
	 * @dataProvider nonBlankTemplateProvider
	 * @param string $template_id Template key.
	 */
	public function test_every_field_has_a_non_empty_id( $template_id ) {
		$templates = $this->templates->get_templates();
		$fields    = $templates[ $template_id ]['fields'];

		$this->assertNotEmpty( $fields, "Template '{$template_id}' has no fields to check." );

		foreach ( $fields as $field ) {
			$this->assertNotEmpty(
				fta_get_field_name( $field ),
				"Field '{$field['label']}' in template '{$template_id}' has no id, so it will be saved with a blank name."
			);
		}
	}

	/**
	 * Field ids must be unique within a single template.
	 *
	 * @dataProvider nonBlankTemplateProvider
	 * @param string $template_id Template key.
	 */
	public function test_field_ids_are_unique_within_a_template( $template_id ) {
		$templates = $this->templates->get_templates();
		$fields    = $templates[ $template_id ]['fields'];

		$ids = array_map( 'fta_get_field_name', $fields );

		$this->assertSame(
			count( $ids ),
			count( array_unique( $ids ) ),
			"Template '{$template_id}' has duplicate field ids: " . implode( ', ', $ids )
		);
	}

	/**
	 * Regenerating the templates array (e.g. two calls in the same request,
	 * as ajax_create_from_template() and the library preview both do) must
	 * not change a field's id - otherwise a form created from a template
	 * would not match what the preview page showed.
	 */
	public function test_field_ids_are_stable_across_calls() {
		$first  = $this->templates->get_templates();
		$second = $this->templates->get_templates();

		$first_ids  = array_map( 'fta_get_field_name', $first['contact']['fields'] );
		$second_ids = array_map( 'fta_get_field_name', $second['contact']['fields'] );

		$this->assertSame( $first_ids, $second_ids );
	}
}
