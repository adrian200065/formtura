<?php
/**
 * Mailchimp Integration
 *
 * Integration with Mailchimp email marketing service.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Integrations\Providers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailchimp class.
 */
class Mailchimp {

	/**
	 * API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * API endpoint.
	 *
	 * @var string
	 */
	private $api_endpoint;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->api_key = fta_get_setting( 'mailchimp_api_key', '' );
		$this->set_api_endpoint();
		$this->init_hooks();
	}

	/**
	 * Initialize WordPress hooks.
	 *
	 * @since 1.0.0
	 */
	private function init_hooks() {
		// Add subscriber after form submission.
		add_action( 'fta_after_form_submission', [ $this, 'add_subscriber' ], 10, 3 );
	}

	/**
	 * Set API endpoint based on API key.
	 *
	 * @since 1.0.0
	 */
	private function set_api_endpoint() {
		if ( empty( $this->api_key ) ) {
			return;
		}

		// Extract datacenter from API key.
		$parts = explode( '-', $this->api_key );
		$datacenter = isset( $parts[1] ) ? $parts[1] : 'us1';

		$this->api_endpoint = "https://{$datacenter}.api.mailchimp.com/3.0/";
	}

	/**
	 * Add subscriber to Mailchimp list.
	 *
	 * @since 1.0.0
	 * @param int   $entry_id Entry ID.
	 * @param array $form Form data.
	 * @param array $entry_data Entry data.
	 */
	public function add_subscriber( $entry_id, $form, $entry_data ) {
		// Check if Mailchimp integration is enabled for this form.
		if ( ! isset( $form['settings']['mailchimp_enabled'] ) || ! $form['settings']['mailchimp_enabled'] ) {
			return;
		}

		$list_id = isset( $form['settings']['mailchimp_list_id'] ) ? $form['settings']['mailchimp_list_id'] : '';
		$email_field = isset( $form['settings']['mailchimp_email_field'] ) ? $form['settings']['mailchimp_email_field'] : 'email';

		if ( empty( $list_id ) || ! isset( $entry_data[ $email_field ] ) ) {
			return;
		}

		$email = $entry_data[ $email_field ];

		// Prepare subscriber data.
		$subscriber_data = [
			'email_address' => $email,
			'status'        => 'subscribed',
			'merge_fields'  => $this->get_merge_fields( $entry_data, $form ),
		];

		// Add to list.
		$this->api_request( "lists/{$list_id}/members", 'POST', $subscriber_data );
	}

	/**
	 * Get merge fields from entry data.
	 *
	 * @since 1.0.0
	 * @param array $entry_data Entry data.
	 * @param array $form Form data.
	 * @return array Merge fields.
	 */
	private function get_merge_fields( $entry_data, $form ) {
		$merge_fields = [];

		// Map common fields.
		if ( isset( $entry_data['first_name'] ) ) {
			$merge_fields['FNAME'] = $entry_data['first_name'];
		}

		if ( isset( $entry_data['last_name'] ) ) {
			$merge_fields['LNAME'] = $entry_data['last_name'];
		}

		return apply_filters( 'fta_mailchimp_merge_fields', $merge_fields, $entry_data, $form );
	}

	/**
	 * Make API request to Mailchimp.
	 *
	 * @since 1.0.0
	 * @param string $endpoint API endpoint.
	 * @param string $method HTTP method.
	 * @param array  $data Request data.
	 * @return array|WP_Error Response data or WP_Error on failure.
	 */
	private function api_request( $endpoint, $method = 'GET', $data = [] ) {
		if ( empty( $this->api_key ) || empty( $this->api_endpoint ) ) {
			return new \WP_Error( 'no_api_key', __( 'Mailchimp API key not configured.', FORMTURA_TEXTDOMAIN ) );
		}

		$url = $this->api_endpoint . $endpoint;

		$args = [
			'method'  => $method,
			'headers' => [
				'Authorization' => 'Basic ' . base64_encode( 'user:' . $this->api_key ),
				'Content-Type'  => 'application/json',
			],
			'timeout' => 30,
		];

		if ( ! empty( $data ) ) {
			$args['body'] = wp_json_encode( $data );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 400 ) {
			return new \WP_Error(
				'api_error',
				isset( $body['detail'] ) ? $body['detail'] : __( 'Mailchimp API error.', FORMTURA_TEXTDOMAIN )
			);
		}

		return $body;
	}

	/**
	 * Get Mailchimp lists.
	 *
	 * @since 1.0.0
	 * @return array|WP_Error Array of lists or WP_Error on failure.
	 */
	public function get_lists() {
		$response = $this->api_request( 'lists' );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['lists'] ) ? $response['lists'] : [];
	}
}
