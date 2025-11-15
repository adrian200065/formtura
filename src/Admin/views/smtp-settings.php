<?php
/**
 * SMTP Settings View
 *
 * Admin page for SMTP configuration.
 *
 * @package Formtura
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap fta-admin-page">
	<div class="fta-admin-header">
		<h1><?php esc_html_e( 'SMTP Settings', FORMTURA_TEXTDOMAIN ); ?></h1>
	</div><!-- .fta-admin-header -->

	<div class="fta-card">
		<form id="fta-smtp-form" class="fta-smtp-form">
			<h2><?php esc_html_e( 'SMTP Configuration', FORMTURA_TEXTDOMAIN ); ?></h2>
			<p><?php esc_html_e( 'Configure SMTP settings to send form notification emails reliably.', FORMTURA_TEXTDOMAIN ); ?></p>
			
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="fta-smtp-enable"><?php esc_html_e( 'Enable SMTP', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									id="fta-smtp-enable" 
									name="smtp_settings[enable_smtp]" 
									value="1" 
									<?php checked( isset( $smtp_settings['enable_smtp'] ) ? $smtp_settings['enable_smtp'] : false, 1 ); ?>>
								<?php esc_html_e( 'Use SMTP for sending emails', FORMTURA_TEXTDOMAIN ); ?>
							</label>
						</td>
					</tr>

					<tr class="fta-smtp-field">
						<th scope="row">
							<label for="fta-smtp-host"><?php esc_html_e( 'SMTP Host', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" 
								id="fta-smtp-host" 
								name="smtp_settings[smtp_host]" 
								value="<?php echo esc_attr( isset( $smtp_settings['smtp_host'] ) ? $smtp_settings['smtp_host'] : '' ); ?>" 
								class="regular-text"
								placeholder="smtp.gmail.com">
							<p class="description">
								<?php esc_html_e( 'Your SMTP server hostname.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr class="fta-smtp-field">
						<th scope="row">
							<label for="fta-smtp-port"><?php esc_html_e( 'SMTP Port', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="number" 
								id="fta-smtp-port" 
								name="smtp_settings[smtp_port]" 
								value="<?php echo esc_attr( isset( $smtp_settings['smtp_port'] ) ? $smtp_settings['smtp_port'] : '587' ); ?>" 
								class="small-text">
							<p class="description">
								<?php esc_html_e( 'Common ports: 25, 465 (SSL), 587 (TLS)', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr class="fta-smtp-field">
						<th scope="row">
							<label for="fta-smtp-encryption"><?php esc_html_e( 'Encryption', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<select id="fta-smtp-encryption" name="smtp_settings[smtp_encryption]">
								<option value="none" <?php selected( isset( $smtp_settings['smtp_encryption'] ) ? $smtp_settings['smtp_encryption'] : 'tls', 'none' ); ?>>
									<?php esc_html_e( 'None', FORMTURA_TEXTDOMAIN ); ?>
								</option>
								<option value="ssl" <?php selected( isset( $smtp_settings['smtp_encryption'] ) ? $smtp_settings['smtp_encryption'] : 'tls', 'ssl' ); ?>>
									SSL
								</option>
								<option value="tls" <?php selected( isset( $smtp_settings['smtp_encryption'] ) ? $smtp_settings['smtp_encryption'] : 'tls', 'tls' ); ?>>
									TLS
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Recommended: TLS', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr class="fta-smtp-field">
						<th scope="row">
							<label for="fta-smtp-auth"><?php esc_html_e( 'Authentication', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									id="fta-smtp-auth" 
									name="smtp_settings[smtp_auth]" 
									value="1" 
									<?php checked( isset( $smtp_settings['smtp_auth'] ) ? $smtp_settings['smtp_auth'] : true, 1 ); ?>>
								<?php esc_html_e( 'Use SMTP authentication', FORMTURA_TEXTDOMAIN ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Most SMTP servers require authentication.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr class="fta-smtp-field fta-smtp-auth-field">
						<th scope="row">
							<label for="fta-smtp-username"><?php esc_html_e( 'Username', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" 
								id="fta-smtp-username" 
								name="smtp_settings[smtp_username]" 
								value="<?php echo esc_attr( isset( $smtp_settings['smtp_username'] ) ? $smtp_settings['smtp_username'] : '' ); ?>" 
								class="regular-text"
								autocomplete="off">
						</td>
					</tr>

					<tr class="fta-smtp-field fta-smtp-auth-field">
						<th scope="row">
							<label for="fta-smtp-password"><?php esc_html_e( 'Password', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="password" 
								id="fta-smtp-password" 
								name="smtp_settings[smtp_password]" 
								value="<?php echo esc_attr( isset( $smtp_settings['smtp_password'] ) ? $smtp_settings['smtp_password'] : '' ); ?>" 
								class="regular-text"
								autocomplete="new-password">
							<p class="description">
								<?php esc_html_e( 'Your SMTP password will be stored encrypted.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr class="fta-smtp-field">
						<th scope="row">
							<label for="fta-smtp-from-email"><?php esc_html_e( 'From Email', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="email" 
								id="fta-smtp-from-email" 
								name="smtp_settings[from_email]" 
								value="<?php echo esc_attr( isset( $smtp_settings['from_email'] ) ? $smtp_settings['from_email'] : get_option( 'admin_email' ) ); ?>" 
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Email address to send from.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr class="fta-smtp-field">
						<th scope="row">
							<label for="fta-smtp-from-name"><?php esc_html_e( 'From Name', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" 
								id="fta-smtp-from-name" 
								name="smtp_settings[from_name]" 
								value="<?php echo esc_attr( isset( $smtp_settings['from_name'] ) ? $smtp_settings['from_name'] : get_bloginfo( 'name' ) ); ?>" 
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Name to send from.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php wp_nonce_field( 'formtura_admin', 'formtura_nonce' ); ?>
			
			<p class="submit">
				<button type="submit" class="button button-primary fta-save-smtp">
					<?php esc_html_e( 'Save SMTP Settings', FORMTURA_TEXTDOMAIN ); ?>
				</button>
				<button type="button" class="button fta-test-smtp" style="margin-left: 10px;">
					<?php esc_html_e( 'Send Test Email', FORMTURA_TEXTDOMAIN ); ?>
				</button>
			</p>
		</form>
	</div><!-- .fta-card -->
</div><!-- .fta-admin-page -->

<script>
jQuery(document).ready(function($) {
	// Toggle SMTP fields based on enable checkbox
	function toggleSMTPFields() {
		if ($('#fta-smtp-enable').is(':checked')) {
			$('.fta-smtp-field').show();
		} else {
			$('.fta-smtp-field').hide();
		}
	}
	
	// Toggle auth fields based on auth checkbox
	function toggleAuthFields() {
		if ($('#fta-smtp-auth').is(':checked')) {
			$('.fta-smtp-auth-field').show();
		} else {
			$('.fta-smtp-auth-field').hide();
		}
	}
	
	toggleSMTPFields();
	toggleAuthFields();
	
	$('#fta-smtp-enable').on('change', toggleSMTPFields);
	$('#fta-smtp-auth').on('change', toggleAuthFields);
	
	// Save SMTP settings
	$('#fta-smtp-form').on('submit', function(e) {
		e.preventDefault();
		
		var $form = $(this);
		var $button = $form.find('.fta-save-smtp');
		var buttonText = $button.text();
		
		$button.prop('disabled', true).text('<?php esc_html_e( 'Saving...', FORMTURA_TEXTDOMAIN ); ?>');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fta_save_smtp_settings',
				nonce: $('#formtura_nonce').val(),
				smtp_settings: $form.serializeArray().reduce(function(obj, item) {
					var name = item.name.replace('smtp_settings[', '').replace(']', '');
					obj[name] = item.value;
					return obj;
				}, {})
			},
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'SMTP settings saved successfully!', FORMTURA_TEXTDOMAIN ); ?>');
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Failed to save SMTP settings.', FORMTURA_TEXTDOMAIN ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'An error occurred while saving SMTP settings.', FORMTURA_TEXTDOMAIN ); ?>');
			},
			complete: function() {
				$button.prop('disabled', false).text(buttonText);
			}
		});
	});
	
	// Test SMTP connection
	$('.fta-test-smtp').on('click', function() {
		var $button = $(this);
		var buttonText = $button.text();
		
		$button.prop('disabled', true).text('<?php esc_html_e( 'Sending...', FORMTURA_TEXTDOMAIN ); ?>');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fta_test_smtp',
				nonce: $('#formtura_nonce').val()
			},
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Test email sent successfully! Check your inbox.', FORMTURA_TEXTDOMAIN ); ?>');
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Failed to send test email.', FORMTURA_TEXTDOMAIN ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'An error occurred while sending test email.', FORMTURA_TEXTDOMAIN ); ?>');
			},
			complete: function() {
				$button.prop('disabled', false).text(buttonText);
			}
		});
	});
});
</script>
