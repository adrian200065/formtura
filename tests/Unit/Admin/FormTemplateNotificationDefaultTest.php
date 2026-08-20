<?php
/**
 * Default notification for forms created from the template library.
 *
 * A form created via ajax_create_from_template() previously saved no
 * `settings` at all, so it silently sent no email until someone opened the
 * builder's Form Settings dialog and configured one by hand. New forms are
 * expected to start with one enabled admin notification, matching the
 * builder's own blank-form default (see FormBuilder.jsx's initial
 * formSettings state).
 *
 * @package Formtura
 */

namespace Formtura\Tests\Unit\Admin;

use Formtura\Admin\Form_Templates;
use Formtura\Tests\TestCase;

class FormTemplateNotificationDefaultTest extends TestCase {

	/**
	 * @var object
	 */
	private $recordingWpdb;

	protected function setUp(): void {
		parent::setUp();

		global $wpdb;
		$wpdb                = $this->makeWpdb();
		$this->recordingWpdb = $wpdb;

		$GLOBALS['fta_test_current_user_can'] = true;
		$_POST                                = [];
	}

	protected function tearDown(): void {
		unset( $GLOBALS['fta_test_current_user_can'], $GLOBALS['fta_test_ajax_referer_valid'] );
		$_POST = [];

		parent::tearDown();
	}

	private function makeWpdb() {
		return new class {
			public $prefix    = 'wp_';
			public $insert_id = 42;
			public $inserts   = [];

			public function prepare( $query, ...$args ) {
				return $query;
			}

			public function insert( $table, $data, $format = null ) {
				$this->inserts[] = [ $table, $data ];

				return 1;
			}
		};
	}

	/**
	 * Run the handler and capture the JSON response it terminated with.
	 *
	 * @return \FTA_Test_Ajax_Response
	 */
	private function call() {
		try {
			( new Form_Templates() )->ajax_create_from_template();
		} catch ( \FTA_Test_Ajax_Response $response ) {
			return $response;
		}

		$this->fail( 'ajax_create_from_template() returned without sending a response.' );
	}

	public function test_a_form_created_from_a_template_gets_one_enabled_admin_notification() {
		$_POST = [ 'template_id' => 'contact' ];

		$response = $this->call();

		$this->assertTrue( $response->success );
		$this->assertCount( 1, $this->recordingWpdb->inserts );

		$settings = json_decode( $this->recordingWpdb->inserts[0][1]['settings'], true );

		$this->assertCount( 1, $settings['notifications'] );
		$this->assertTrue( $settings['notifications'][0]['enabled'] );
		$this->assertSame( '{admin_email}', $settings['notifications'][0]['to'] );
	}

	/**
	 * The blank starting-point template is still a "new form" and should
	 * notify its owner by default just like any other.
	 */
	public function test_the_blank_template_also_gets_a_default_notification() {
		$_POST = [ 'template_id' => 'blank' ];

		$this->call();

		$settings = json_decode( $this->recordingWpdb->inserts[0][1]['settings'], true );

		$this->assertTrue( $settings['notifications'][0]['enabled'] );
	}
}
