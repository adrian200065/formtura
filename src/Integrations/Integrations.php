<?php
/**
 * Integrations Class
 *
 * Main class to load and manage third-party integrations.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Integrations;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrations class.
 */
class Integrations {

	/**
	 * Available integrations.
	 *
	 * @var array
	 */
	private $integrations = [];

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->register_integrations();
		$this->load_integrations();
	}

	/**
	 * Register available integrations.
	 *
	 * @since 1.0.0
	 */
	private function register_integrations() {
		// Use plain strings to avoid translation loading before init hook.
		$this->integrations = apply_filters( 'fta_integrations', [
			'mailchimp' => [
				'name'        => 'Mailchimp',
				'description' => 'Add form subscribers to Mailchimp lists.',
				'class'       => 'Formtura\\Integrations\\Providers\\Mailchimp',
				'enabled'     => false,
			],
		] );
	}

	/**
	 * Load enabled integrations.
	 *
	 * @since 1.0.0
	 */
	private function load_integrations() {
		foreach ( $this->integrations as $key => $integration ) {
			if ( ! empty( $integration['enabled'] ) && class_exists( $integration['class'] ) ) {
				new $integration['class']();
			}
		}
	}

	/**
	 * Get all integrations.
	 *
	 * @since 1.0.0
	 * @return array Array of integrations.
	 */
	public function get_integrations() {
		return $this->integrations;
	}

	/**
	 * Get a specific integration.
	 *
	 * @since 1.0.0
	 * @param string $key Integration key.
	 * @return array|null Integration data or null if not found.
	 */
	public function get_integration( $key ) {
		return isset( $this->integrations[ $key ] ) ? $this->integrations[ $key ] : null;
	}
}
