<?php
/**
 * Form Templates Class
 *
 * Handles the template library for quick form creation.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Form_Templates class.
 */
class Form_Templates {

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
		// AJAX handlers.
		add_action( 'wp_ajax_fta_create_from_template', [ $this, 'ajax_create_from_template' ] );
	}

	/**
	 * Render templates page.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		$templates = $this->get_templates();
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/templates-library.php';
	}

	/**
	 * Get available templates.
	 *
	 * @since 1.0.0
	 * @return array Array of templates.
	 */
	public function get_templates() {
		$templates = [
			'blank' => [
				'title'       => __( 'Blank Form', FORMTURA_TEXTDOMAIN ),
				'description' => __( 'Start from scratch with a blank canvas.', FORMTURA_TEXTDOMAIN ),
				'icon'        => 'file-plus',
				'fields'      => [],
			],
			'contact' => [
				'title'       => __( 'Contact Form', FORMTURA_TEXTDOMAIN ),
				'description' => __( 'Simple contact form with name, email, and message.', FORMTURA_TEXTDOMAIN ),
				'icon'        => 'mail',
				'fields'      => [
					[
						'type'        => 'name',
						'label'       => __( 'Name', FORMTURA_TEXTDOMAIN ),
						'required'    => true,
					],
					[
						'type'        => 'email',
						'label'       => __( 'Email', FORMTURA_TEXTDOMAIN ),
						'required'    => true,
					],
					[
						'type'        => 'textarea',
						'label'       => __( 'Message', FORMTURA_TEXTDOMAIN ),
						'required'    => true,
						'placeholder' => __( 'Enter your message here...', FORMTURA_TEXTDOMAIN ),
					],
				],
			],
			'quote' => [
				'title'       => __( 'Request a Quote', FORMTURA_TEXTDOMAIN ),
				'description' => __( 'Collect quote requests from potential customers.', FORMTURA_TEXTDOMAIN ),
				'icon'        => 'dollar-sign',
				'fields'      => [
					[
						'type'     => 'name',
						'label'    => __( 'Name', FORMTURA_TEXTDOMAIN ),
						'required' => true,
					],
					[
						'type'     => 'email',
						'label'    => __( 'Email', FORMTURA_TEXTDOMAIN ),
						'required' => true,
					],
					[
						'type'     => 'text',
						'label'    => __( 'Company', FORMTURA_TEXTDOMAIN ),
						'required' => false,
					],
					[
						'type'     => 'select',
						'label'    => __( 'Service Interested In', FORMTURA_TEXTDOMAIN ),
						'required' => true,
						'options'  => [
							__( 'Web Design', FORMTURA_TEXTDOMAIN ),
							__( 'Web Development', FORMTURA_TEXTDOMAIN ),
							__( 'SEO Services', FORMTURA_TEXTDOMAIN ),
							__( 'Marketing', FORMTURA_TEXTDOMAIN ),
							__( 'Other', FORMTURA_TEXTDOMAIN ),
						],
					],
					[
						'type'        => 'textarea',
						'label'       => __( 'Project Details', FORMTURA_TEXTDOMAIN ),
						'required'    => true,
						'placeholder' => __( 'Tell us about your project...', FORMTURA_TEXTDOMAIN ),
					],
				],
			],
			'feedback' => [
				'title'       => __( 'Feedback Survey', FORMTURA_TEXTDOMAIN ),
				'description' => __( 'Gather user feedback and suggestions.', FORMTURA_TEXTDOMAIN ),
				'icon'        => 'message-square',
				'fields'      => [
					[
						'type'     => 'radio',
						'label'    => __( 'How satisfied are you with our service?', FORMTURA_TEXTDOMAIN ),
						'required' => true,
						'options'  => [
							__( 'Very Satisfied', FORMTURA_TEXTDOMAIN ),
							__( 'Satisfied', FORMTURA_TEXTDOMAIN ),
							__( 'Neutral', FORMTURA_TEXTDOMAIN ),
							__( 'Dissatisfied', FORMTURA_TEXTDOMAIN ),
							__( 'Very Dissatisfied', FORMTURA_TEXTDOMAIN ),
						],
					],
					[
						'type'     => 'radio',
						'label'    => __( 'Would you recommend us to others?', FORMTURA_TEXTDOMAIN ),
						'required' => true,
						'options'  => [
							__( 'Definitely', FORMTURA_TEXTDOMAIN ),
							__( 'Probably', FORMTURA_TEXTDOMAIN ),
							__( 'Not Sure', FORMTURA_TEXTDOMAIN ),
							__( 'Probably Not', FORMTURA_TEXTDOMAIN ),
							__( 'Definitely Not', FORMTURA_TEXTDOMAIN ),
						],
					],
					[
						'type'        => 'textarea',
						'label'       => __( 'Additional Comments', FORMTURA_TEXTDOMAIN ),
						'required'    => false,
						'placeholder' => __( 'Share your thoughts...', FORMTURA_TEXTDOMAIN ),
					],
				],
			],
			'registration' => [
				'title'       => __( 'Event Registration', FORMTURA_TEXTDOMAIN ),
				'description' => __( 'Collect registrations for events or webinars.', FORMTURA_TEXTDOMAIN ),
				'icon'        => 'calendar',
				'fields'      => [
					[
						'type'     => 'name',
						'label'    => __( 'Full Name', FORMTURA_TEXTDOMAIN ),
						'required' => true,
					],
					[
						'type'     => 'email',
						'label'    => __( 'Email Address', FORMTURA_TEXTDOMAIN ),
						'required' => true,
					],
					[
						'type'     => 'text',
						'label'    => __( 'Phone Number', FORMTURA_TEXTDOMAIN ),
						'required' => false,
					],
					[
						'type'     => 'select',
						'label'    => __( 'Number of Attendees', FORMTURA_TEXTDOMAIN ),
						'required' => true,
						'options'  => [ '1', '2', '3', '4', '5+' ],
					],
					[
						'type'        => 'textarea',
						'label'       => __( 'Special Requirements', FORMTURA_TEXTDOMAIN ),
						'required'    => false,
						'placeholder' => __( 'Dietary restrictions, accessibility needs, etc.', FORMTURA_TEXTDOMAIN ),
					],
				],
			],
			'job_application' => [
				'title'       => __( 'Job Application', FORMTURA_TEXTDOMAIN ),
				'description' => __( 'Accept job applications online.', FORMTURA_TEXTDOMAIN ),
				'icon'        => 'briefcase',
				'fields'      => [
					[
						'type'     => 'name',
						'label'    => __( 'Full Name', FORMTURA_TEXTDOMAIN ),
						'required' => true,
					],
					[
						'type'     => 'email',
						'label'    => __( 'Email Address', FORMTURA_TEXTDOMAIN ),
						'required' => true,
					],
					[
						'type'     => 'text',
						'label'    => __( 'Phone Number', FORMTURA_TEXTDOMAIN ),
						'required' => true,
					],
					[
						'type'     => 'select',
						'label'    => __( 'Position Applying For', FORMTURA_TEXTDOMAIN ),
						'required' => true,
						'options'  => [
							__( 'Developer', FORMTURA_TEXTDOMAIN ),
							__( 'Designer', FORMTURA_TEXTDOMAIN ),
							__( 'Marketing', FORMTURA_TEXTDOMAIN ),
							__( 'Sales', FORMTURA_TEXTDOMAIN ),
							__( 'Other', FORMTURA_TEXTDOMAIN ),
						],
					],
					[
						'type'        => 'textarea',
						'label'       => __( 'Cover Letter', FORMTURA_TEXTDOMAIN ),
						'required'    => false,
						'placeholder' => __( 'Tell us why you\'re a great fit...', FORMTURA_TEXTDOMAIN ),
					],
				],
			],
		];

		return apply_filters( 'fta_form_templates', $templates );
	}

	/**
	 * AJAX handler to create form from template.
	 *
	 * @since 1.0.0
	 */
	public function ajax_create_from_template() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$template_id = isset( $_POST['template_id'] ) ? sanitize_key( $_POST['template_id'] ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error( [
				'message' => __( 'Invalid template ID.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$templates = $this->get_templates();

		if ( ! isset( $templates[ $template_id ] ) ) {
			wp_send_json_error( [
				'message' => __( 'Template not found.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$template = $templates[ $template_id ];

		// Create form from template.
		$form_data = [
			'title'  => $template['title'],
			'fields' => $template['fields'],
			'status' => 'active',
		];

		$form_id = fta_create_form( $form_data );

		if ( $form_id ) {
			wp_send_json_success( [
				'message'       => __( 'Form created successfully.', FORMTURA_TEXTDOMAIN ),
				'form_id'       => $form_id,
				'redirect_url'  => admin_url( 'admin.php?page=formtura-builder&form_id=' . $form_id ),
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to create form.', FORMTURA_TEXTDOMAIN ),
			] );
		}
	}
}
