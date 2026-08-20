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
		add_action( 'wp_ajax_fta_create_from_template', array( $this, 'ajax_create_from_template' ) );
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
		$templates = array(
			'blank'           => array(
				'title'       => __( 'Blank Form', 'formtura' ),
				'description' => __( 'Start from scratch with a blank canvas.', 'formtura' ),
				'icon'        => 'file-plus',
				'fields'      => array(),
			),
			'contact'         => array(
				'title'       => __( 'Contact Form', 'formtura' ),
				'description' => __( 'Simple contact form with name, email, and message.', 'formtura' ),
				'icon'        => 'mail',
				'fields'      => array(
					array(
						'id'       => 'field_contact_name',
						'type'     => 'name',
						'label'    => __( 'Name', 'formtura' ),
						'required' => true,
					),
					array(
						'id'       => 'field_contact_email',
						'type'     => 'email',
						'label'    => __( 'Email', 'formtura' ),
						'required' => true,
					),
					array(
						'id'          => 'field_contact_message',
						'type'        => 'textarea',
						'label'       => __( 'Message', 'formtura' ),
						'required'    => true,
						'placeholder' => __( 'Enter your message here...', 'formtura' ),
					),
				),
			),
			'quote'           => array(
				'title'       => __( 'Request a Quote', 'formtura' ),
				'description' => __( 'Collect quote requests from potential customers.', 'formtura' ),
				'icon'        => 'dollar-sign',
				'fields'      => array(
					array(
						'id'       => 'field_quote_name',
						'type'     => 'name',
						'label'    => __( 'Name', 'formtura' ),
						'required' => true,
					),
					array(
						'id'       => 'field_quote_email',
						'type'     => 'email',
						'label'    => __( 'Email', 'formtura' ),
						'required' => true,
					),
					array(
						'id'       => 'field_quote_company',
						'type'     => 'text',
						'label'    => __( 'Company', 'formtura' ),
						'required' => false,
					),
					array(
						'id'       => 'field_quote_service',
						'type'     => 'select',
						'label'    => __( 'Service Interested In', 'formtura' ),
						'required' => true,
						'options'  => array(
							__( 'Web Design', 'formtura' ),
							__( 'Web Development', 'formtura' ),
							__( 'SEO Services', 'formtura' ),
							__( 'Marketing', 'formtura' ),
							__( 'Other', 'formtura' ),
						),
					),
					array(
						'id'          => 'field_quote_details',
						'type'        => 'textarea',
						'label'       => __( 'Project Details', 'formtura' ),
						'required'    => true,
						'placeholder' => __( 'Tell us about your project...', 'formtura' ),
					),
				),
			),
			'feedback'        => array(
				'title'       => __( 'Feedback Survey', 'formtura' ),
				'description' => __( 'Gather user feedback and suggestions.', 'formtura' ),
				'icon'        => 'message-square',
				'fields'      => array(
					array(
						'id'       => 'field_feedback_satisfaction',
						'type'     => 'radio',
						'label'    => __( 'How satisfied are you with our service?', 'formtura' ),
						'required' => true,
						'options'  => array(
							__( 'Very Satisfied', 'formtura' ),
							__( 'Satisfied', 'formtura' ),
							__( 'Neutral', 'formtura' ),
							__( 'Dissatisfied', 'formtura' ),
							__( 'Very Dissatisfied', 'formtura' ),
						),
					),
					array(
						'id'       => 'field_feedback_recommend',
						'type'     => 'radio',
						'label'    => __( 'Would you recommend us to others?', 'formtura' ),
						'required' => true,
						'options'  => array(
							__( 'Definitely', 'formtura' ),
							__( 'Probably', 'formtura' ),
							__( 'Not Sure', 'formtura' ),
							__( 'Probably Not', 'formtura' ),
							__( 'Definitely Not', 'formtura' ),
						),
					),
					array(
						'id'          => 'field_feedback_comments',
						'type'        => 'textarea',
						'label'       => __( 'Additional Comments', 'formtura' ),
						'required'    => false,
						'placeholder' => __( 'Share your thoughts...', 'formtura' ),
					),
				),
			),
			'registration'    => array(
				'title'       => __( 'Event Registration', 'formtura' ),
				'description' => __( 'Collect registrations for events or webinars.', 'formtura' ),
				'icon'        => 'calendar',
				'fields'      => array(
					array(
						'id'       => 'field_registration_name',
						'type'     => 'name',
						'label'    => __( 'Full Name', 'formtura' ),
						'required' => true,
					),
					array(
						'id'       => 'field_registration_email',
						'type'     => 'email',
						'label'    => __( 'Email Address', 'formtura' ),
						'required' => true,
					),
					array(
						'id'       => 'field_registration_phone',
						'type'     => 'text',
						'label'    => __( 'Phone Number', 'formtura' ),
						'required' => false,
					),
					array(
						'id'       => 'field_registration_attendees',
						'type'     => 'select',
						'label'    => __( 'Number of Attendees', 'formtura' ),
						'required' => true,
						'options'  => array( '1', '2', '3', '4', '5+' ),
					),
					array(
						'id'          => 'field_registration_requirements',
						'type'        => 'textarea',
						'label'       => __( 'Special Requirements', 'formtura' ),
						'required'    => false,
						'placeholder' => __( 'Dietary restrictions, accessibility needs, etc.', 'formtura' ),
					),
				),
			),
			'job_application' => array(
				'title'       => __( 'Job Application', 'formtura' ),
				'description' => __( 'Accept job applications online.', 'formtura' ),
				'icon'        => 'briefcase',
				'fields'      => array(
					array(
						'id'       => 'field_job_name',
						'type'     => 'name',
						'label'    => __( 'Full Name', 'formtura' ),
						'required' => true,
					),
					array(
						'id'       => 'field_job_email',
						'type'     => 'email',
						'label'    => __( 'Email Address', 'formtura' ),
						'required' => true,
					),
					array(
						'id'       => 'field_job_phone',
						'type'     => 'text',
						'label'    => __( 'Phone Number', 'formtura' ),
						'required' => true,
					),
					array(
						'id'       => 'field_job_position',
						'type'     => 'select',
						'label'    => __( 'Position Applying For', 'formtura' ),
						'required' => true,
						'options'  => array(
							__( 'Developer', 'formtura' ),
							__( 'Designer', 'formtura' ),
							__( 'Marketing', 'formtura' ),
							__( 'Sales', 'formtura' ),
							__( 'Other', 'formtura' ),
						),
					),
					array(
						'id'          => 'field_job_cover_letter',
						'type'        => 'textarea',
						'label'       => __( 'Cover Letter', 'formtura' ),
						'required'    => false,
						'placeholder' => __( 'Tell us why you\'re a great fit...', 'formtura' ),
					),
				),
			),
		);

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
			wp_send_json_error(
				array(
					'message' => __( 'You do not have permission to perform this action.', 'formtura' ),
				)
			);
		}

		$template_id = isset( $_POST['template_id'] ) ? sanitize_key( $_POST['template_id'] ) : '';

		if ( empty( $template_id ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Invalid template ID.', 'formtura' ),
				)
			);
		}

		$templates = $this->get_templates();

		if ( ! isset( $templates[ $template_id ] ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Template not found.', 'formtura' ),
				)
			);
		}

		$template = $templates[ $template_id ];

		// Create form from template.
		$form_data = array(
			'title'  => $template['title'],
			'fields' => $template['fields'],
			'status' => 'active',
		);

		$form_id = fta_create_form( $form_data );

		if ( $form_id ) {
			wp_send_json_success(
				array(
					'message'      => __( 'Form created successfully.', 'formtura' ),
					'form_id'      => $form_id,
					'redirect_url' => admin_url( 'admin.php?page=formtura-builder&form_id=' . $form_id ),
				)
			);
		} else {
			wp_send_json_error(
				array(
					'message' => __( 'Failed to create form.', 'formtura' ),
				)
			);
		}
	}
}
