<?php
/**
 * Form Selector Block
 *
 * Gutenberg block for selecting and embedding forms.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Blocks;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form_Selector class.
 */
class Form_Selector {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Register block.
		add_action( 'init', [ $this, 'register_block' ] );
	}

	/**
	 * Register Gutenberg block.
	 *
	 * @since 1.0.0
	 */
	public function register_block() {
		// Check if Gutenberg is available.
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		// Register block.
		register_block_type( 'formtura/form-selector', [
			'attributes'      => [
				'formId' => [
					'type'    => 'number',
					'default' => 0,
				],
			],
			'render_callback' => [ $this, 'render_block' ],
		] );
	}

	/**
	 * Render block on frontend.
	 *
	 * @since 1.0.0
	 * @param array $attributes Block attributes.
	 * @return string Block HTML.
	 */
	public function render_block( $attributes ) {
		$form_id = isset( $attributes['formId'] ) ? absint( $attributes['formId'] ) : 0;

		if ( ! $form_id ) {
			return '<p>' . __( 'Please select a form.', FORMTURA_TEXTDOMAIN ) . '</p>';
		}

		return fta_render_form( $form_id );
	}
}
