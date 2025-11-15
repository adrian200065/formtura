<?php
/**
 * SMTP Class
 *
 * Handles SMTP configuration for reliable email delivery.
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
 * SMTP class.
 */
class SMTP {

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
		// Configure PHPMailer if SMTP is enabled.
		add_action( 'phpmailer_init', [ $this, 'configure_phpmailer' ] );

		// AJAX handlers.
		add_action( 'wp_ajax_fta_save_smtp_settings', [ $this, 'ajax_save_smtp_settings' ] );
		add_action( 'wp_ajax_fta_send_test_email', [ $this, 'ajax_send_test_email' ] );
	}

	/**
	 * Render SMTP settings page.
	 *
	 * @since 1.0.0
	 */
	public function render() {
		$smtp_settings = fta_get_smtp_setting();
		include FORMTURA_PLUGIN_DIR . 'src/Admin/views/smtp-settings.php';
	}

	/**
	 * Configure PHPMailer with SMTP settings.
	 *
	 * @since 1.0.0
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	public function configure_phpmailer( $phpmailer ) {
		$smtp_enabled = fta_get_smtp_setting( 'enabled', false );

		if ( ! $smtp_enabled ) {
			return;
		}

		$mailer = fta_get_smtp_setting( 'mailer', 'smtp' );

		// Configure based on mailer type.
		switch ( $mailer ) {
			case 'sendgrid':
				$this->configure_sendgrid( $phpmailer );
				break;
			case 'mailgun':
				$this->configure_mailgun( $phpmailer );
				break;
			case 'ses':
				$this->configure_ses( $phpmailer );
				break;
			case 'smtp':
			default:
				$this->configure_smtp( $phpmailer );
				break;
		}

		// Set from name and email.
		$from_email = fta_get_smtp_setting( 'from_email', get_option( 'admin_email' ) );
		$from_name = fta_get_smtp_setting( 'from_name', get_option( 'blogname' ) );

		$phpmailer->setFrom( $from_email, $from_name );
	}

	/**
	 * Configure SMTP mailer.
	 *
	 * @since 1.0.0
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	private function configure_smtp( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host = fta_get_smtp_setting( 'smtp_host', '' );
		$phpmailer->Port = fta_get_smtp_setting( 'smtp_port', 587 );
		$phpmailer->SMTPAuth = fta_get_smtp_setting( 'smtp_auth', true );
		$phpmailer->Username = fta_get_smtp_setting( 'smtp_username', '' );
		$phpmailer->Password = fta_get_smtp_setting( 'smtp_password', '' );
		$phpmailer->SMTPSecure = fta_get_smtp_setting( 'smtp_encryption', 'tls' );
	}

	/**
	 * Configure SendGrid.
	 *
	 * @since 1.0.0
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	private function configure_sendgrid( $phpmailer ) {
		$phpmailer->isSMTP();
		$phpmailer->Host = 'smtp.sendgrid.net';
		$phpmailer->Port = 587;
		$phpmailer->SMTPAuth = true;
		$phpmailer->Username = 'apikey';
		$phpmailer->Password = fta_get_smtp_setting( 'sendgrid_api_key', '' );
		$phpmailer->SMTPSecure = 'tls';
	}

	/**
	 * Configure Mailgun.
	 *
	 * @since 1.0.0
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	private function configure_mailgun( $phpmailer ) {
		$region = fta_get_smtp_setting( 'mailgun_region', 'us' );
		$host = $region === 'eu' ? 'smtp.eu.mailgun.org' : 'smtp.mailgun.org';

		$phpmailer->isSMTP();
		$phpmailer->Host = $host;
		$phpmailer->Port = 587;
		$phpmailer->SMTPAuth = true;
		$phpmailer->Username = fta_get_smtp_setting( 'mailgun_username', '' );
		$phpmailer->Password = fta_get_smtp_setting( 'mailgun_password', '' );
		$phpmailer->SMTPSecure = 'tls';
	}

	/**
	 * Configure Amazon SES.
	 *
	 * @since 1.0.0
	 * @param \PHPMailer\PHPMailer\PHPMailer $phpmailer PHPMailer instance.
	 */
	private function configure_ses( $phpmailer ) {
		$region = fta_get_smtp_setting( 'ses_region', 'us-east-1' );
		$host = "email-smtp.{$region}.amazonaws.com";

		$phpmailer->isSMTP();
		$phpmailer->Host = $host;
		$phpmailer->Port = 587;
		$phpmailer->SMTPAuth = true;
		$phpmailer->Username = fta_get_smtp_setting( 'ses_access_key', '' );
		$phpmailer->Password = fta_get_smtp_setting( 'ses_secret_key', '' );
		$phpmailer->SMTPSecure = 'tls';
	}

	/**
	 * AJAX handler to save SMTP settings.
	 *
	 * @since 1.0.0
	 */
	public function ajax_save_smtp_settings() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Get SMTP settings.
		$smtp_settings = isset( $_POST['smtp_settings'] ) ? $_POST['smtp_settings'] : [];

		// Sanitize settings.
		$sanitized_settings = $this->sanitize_smtp_settings( $smtp_settings );

		// Save settings.
		$result = update_option( 'fta_smtp_settings', $sanitized_settings );

		if ( $result ) {
			wp_send_json_success( [
				'message' => __( 'SMTP settings saved successfully.', FORMTURA_TEXTDOMAIN ),
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to save SMTP settings.', FORMTURA_TEXTDOMAIN ),
			] );
		}
	}

	/**
	 * AJAX handler to send test email.
	 *
	 * @since 1.0.0
	 */
	public function ajax_send_test_email() {
		// Verify nonce.
		check_ajax_referer( 'formtura_admin', 'nonce' );

		// Check permissions.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [
				'message' => __( 'You do not have permission to perform this action.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		$email = isset( $_POST['email'] ) ? sanitize_email( $_POST['email'] ) : '';

		if ( ! is_email( $email ) ) {
			wp_send_json_error( [
				'message' => __( 'Please enter a valid email address.', FORMTURA_TEXTDOMAIN ),
			] );
		}

		// Send test email.
		$subject = __( 'Formtura Test Email', FORMTURA_TEXTDOMAIN );
		$message = __( 'This is a test email from Formtura. If you received this, your SMTP configuration is working correctly!', FORMTURA_TEXTDOMAIN );

		$result = wp_mail( $email, $subject, $message );

		if ( $result ) {
			wp_send_json_success( [
				'message' => __( 'Test email sent successfully! Please check your inbox.', FORMTURA_TEXTDOMAIN ),
			] );
		} else {
			wp_send_json_error( [
				'message' => __( 'Failed to send test email. Please check your SMTP settings.', FORMTURA_TEXTDOMAIN ),
			] );
		}
	}

	/**
	 * Sanitize SMTP settings.
	 *
	 * @since 1.0.0
	 * @param array $settings SMTP settings.
	 * @return array Sanitized settings.
	 */
	private function sanitize_smtp_settings( $settings ) {
		$sanitized = [];

		// General settings.
		if ( isset( $settings['enabled'] ) ) {
			$sanitized['enabled'] = (bool) $settings['enabled'];
		}

		if ( isset( $settings['mailer'] ) ) {
			$sanitized['mailer'] = in_array( $settings['mailer'], [ 'smtp', 'sendgrid', 'mailgun', 'ses' ], true ) ? $settings['mailer'] : 'smtp';
		}

		if ( isset( $settings['from_email'] ) ) {
			$sanitized['from_email'] = sanitize_email( $settings['from_email'] );
		}

		if ( isset( $settings['from_name'] ) ) {
			$sanitized['from_name'] = sanitize_text_field( $settings['from_name'] );
		}

		// SMTP settings.
		if ( isset( $settings['smtp_host'] ) ) {
			$sanitized['smtp_host'] = sanitize_text_field( $settings['smtp_host'] );
		}

		if ( isset( $settings['smtp_port'] ) ) {
			$sanitized['smtp_port'] = absint( $settings['smtp_port'] );
		}

		if ( isset( $settings['smtp_auth'] ) ) {
			$sanitized['smtp_auth'] = (bool) $settings['smtp_auth'];
		}

		if ( isset( $settings['smtp_username'] ) ) {
			$sanitized['smtp_username'] = sanitize_text_field( $settings['smtp_username'] );
		}

		if ( isset( $settings['smtp_password'] ) ) {
			$sanitized['smtp_password'] = $settings['smtp_password']; // Don't sanitize password.
		}

		if ( isset( $settings['smtp_encryption'] ) ) {
			$sanitized['smtp_encryption'] = in_array( $settings['smtp_encryption'], [ 'tls', 'ssl', 'none' ], true ) ? $settings['smtp_encryption'] : 'tls';
		}

		// SendGrid settings.
		if ( isset( $settings['sendgrid_api_key'] ) ) {
			$sanitized['sendgrid_api_key'] = sanitize_text_field( $settings['sendgrid_api_key'] );
		}

		// Mailgun settings.
		if ( isset( $settings['mailgun_username'] ) ) {
			$sanitized['mailgun_username'] = sanitize_text_field( $settings['mailgun_username'] );
		}

		if ( isset( $settings['mailgun_password'] ) ) {
			$sanitized['mailgun_password'] = $settings['mailgun_password'];
		}

		if ( isset( $settings['mailgun_region'] ) ) {
			$sanitized['mailgun_region'] = in_array( $settings['mailgun_region'], [ 'us', 'eu' ], true ) ? $settings['mailgun_region'] : 'us';
		}

		// SES settings.
		if ( isset( $settings['ses_access_key'] ) ) {
			$sanitized['ses_access_key'] = sanitize_text_field( $settings['ses_access_key'] );
		}

		if ( isset( $settings['ses_secret_key'] ) ) {
			$sanitized['ses_secret_key'] = $settings['ses_secret_key'];
		}

		if ( isset( $settings['ses_region'] ) ) {
			$sanitized['ses_region'] = sanitize_text_field( $settings['ses_region'] );
		}

		return $sanitized;
	}
}
