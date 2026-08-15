<?php
/**
 * Notifications Class
 *
 * Handles sending email notifications for form submissions.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Notifications class.
 */
class Notifications {

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
		// Send notifications after form submission.
		add_action( 'fta_after_form_submission', [ $this, 'send_notifications' ], 10, 3 );
	}

	/**
	 * Send email notifications.
	 *
	 * @since 1.0.0
	 * @param int   $entry_id Entry ID.
	 * @param array $form Form data.
	 * @param array $entry_data Entry data.
	 */
	public function send_notifications( $entry_id, $form, $entry_data ) {
		if ( ! isset( $form['settings']['notifications'] ) || ! is_array( $form['settings']['notifications'] ) ) {
			return;
		}

		$notifications = $form['settings']['notifications'];

		foreach ( $notifications as $notification ) {
			// Check if notification is enabled.
			if ( empty( $notification['enabled'] ) ) {
				continue;
			}

			$this->send_notification( $notification, $form, $entry_data, $entry_id );
		}
	}

	/**
	 * Send a single notification.
	 *
	 * @since 1.0.0
	 * @param array $notification Notification settings.
	 * @param array $form Form data.
	 * @param array $entry_data Entry data.
	 * @param int   $entry_id Entry ID, needed to build file download links.
	 */
	private function send_notification( $notification, $form, $entry_data, $entry_id = 0 ) {
		// Parse recipient email(s).
		$to = $this->parse_smart_tags( $notification['to'], $entry_data, $entry_id );
		$to = array_map( 'trim', explode( ',', $to ) );

		// Parse subject.
		$subject = $this->parse_smart_tags( $notification['subject'], $entry_data, $entry_id );

		// Parse message.
		$message = $this->parse_smart_tags( $notification['message'], $entry_data, $entry_id );

		// Set headers.
		$headers = [ 'Content-Type: text/html; charset=UTF-8' ];

		// Add reply-to if specified.
		if ( ! empty( $notification['reply_to'] ) ) {
			$reply_to = $this->parse_smart_tags( $notification['reply_to'], $entry_data, $entry_id );
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		// Add CC if specified.
		if ( ! empty( $notification['cc'] ) ) {
			$cc = $this->parse_smart_tags( $notification['cc'], $entry_data, $entry_id );
			$cc_emails = array_map( 'trim', explode( ',', $cc ) );
			foreach ( $cc_emails as $cc_email ) {
				$headers[] = 'Cc: ' . $cc_email;
			}
		}

		// Add BCC if specified.
		if ( ! empty( $notification['bcc'] ) ) {
			$bcc = $this->parse_smart_tags( $notification['bcc'], $entry_data, $entry_id );
			$bcc_emails = array_map( 'trim', explode( ',', $bcc ) );
			foreach ( $bcc_emails as $bcc_email ) {
				$headers[] = 'Bcc: ' . $bcc_email;
			}
		}

		// Wrap message in email template.
		$message = $this->get_email_template( $message, $form );

		// Attach uploads from any File Upload field set to "attach to email".
		$attachments = Uploads::get_email_attachments( $form, $entry_data );

		// Send email.
		$sent = wp_mail( $to, $subject, $message, $headers, $attachments );

		// Log if debug mode is enabled.
		if ( fta_get_setting( 'debug_mode', false ) ) {
			fta_log( sprintf(
				'Email notification %s. To: %s, Subject: %s',
				$sent ? 'sent' : 'failed',
				implode( ', ', $to ),
				$subject
			) );
		}

		do_action( 'fta_after_notification_sent', $sent, $notification, $form, $entry_data );
	}

	/**
	 * Parse smart tags in text.
	 *
	 * @since 1.0.0
	 * @param string $text Text with smart tags.
	 * @param array  $entry_data Entry data.
	 * @param int    $entry_id Entry ID, needed to build file download links.
	 * @return string Parsed text.
	 */
	private function parse_smart_tags( $text, $entry_data, $entry_id = 0 ) {
		// Replace field values.
		foreach ( $entry_data as $field_name => $field_value ) {
			$text = str_replace(
				'{' . $field_name . '}',
				$this->format_value( $field_value, $entry_id, $field_name ),
				$text
			);
		}

		// Replace system tags.
		$text = str_replace( '{site_name}', get_bloginfo( 'name' ), $text );
		$text = str_replace( '{site_url}', get_site_url(), $text );
		$text = str_replace( '{admin_email}', get_option( 'admin_email' ), $text );
		$text = str_replace( '{date}', date_i18n( get_option( 'date_format' ) ), $text );
		$text = str_replace( '{time}', date_i18n( get_option( 'time_format' ) ), $text );

		return apply_filters( 'fta_parse_smart_tags', $text, $entry_data );
	}

	/**
	 * Render a stored field value as text for an email.
	 *
	 * Multi-value fields (checkboxes, multi-selects) hold a list, name fields
	 * hold parts keyed by position, and file fields hold records - none of
	 * which can be concatenated directly.
	 *
	 * @since 1.0.3
	 * @param mixed  $value Stored field value.
	 * @param int    $entry_id Entry ID, needed to build file download links.
	 * @param string $field_name Field the value belongs to.
	 * @return string Human readable value.
	 */
	private function format_value( $value, $entry_id = 0, $field_name = '' ) {
		if ( ! is_array( $value ) ) {
			return (string) $value;
		}

		$parts = [];

		foreach ( $value as $index => $item ) {
			if ( is_array( $item ) ) {
				// A file record renders as a link to the authenticated
				// download controller. The record's own location is never
				// used: a legacy record still carries the public uploads URL
				// that this change exists to stop handing out.
				if ( File_Storage::is_file_record( $item ) ) {
					$parts[] = sprintf(
						'<a href="%s">%s</a>',
						esc_url( File_Download::url( $entry_id, $field_name, (int) $index ) ),
						esc_html( isset( $item['name'] ) ? $item['name'] : basename( (string) $index ) )
					);

					continue;
				}

				$parts[] = $this->format_value( $item, $entry_id, $field_name );

				continue;
			}

			if ( '' !== trim( (string) $item ) ) {
				$parts[] = (string) $item;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Get email template.
	 *
	 * @since 1.0.0
	 * @param string $message Email message content.
	 * @param array  $form Form data.
	 * @return string Complete email HTML.
	 */
	private function get_email_template( $message, $form ) {
		ob_start();

		// Allow themes to override email templates.
		$template = locate_template( 'formtura/email/notification.php' );

		if ( $template ) {
			include $template;
		} else {
			include FORMTURA_PLUGIN_DIR . 'templates/email/notification.php';
		}

		return ob_get_clean();
	}

	/**
	 * Get default notification settings.
	 *
	 * @since 1.0.0
	 * @return array Default notification settings.
	 */
	public function get_default_notification() {
		return [
			'enabled'  => true,
			'to'       => '{admin_email}',
			'subject'  => __( 'New Form Submission from {site_name}', FORMTURA_TEXTDOMAIN ),
			'message'  => __( 'You have received a new form submission.', FORMTURA_TEXTDOMAIN ),
			'reply_to' => '',
			'cc'       => '',
			'bcc'      => '',
		];
	}
}
