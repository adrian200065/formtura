<?php
/**
 * Settings View
 *
 * Admin page for plugin settings.
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
		<h1><?php esc_html_e( 'Settings', FORMTURA_TEXTDOMAIN ); ?></h1>
	</div><!-- .fta-admin-header -->

	<div class="fta-card">
		<form id="fta-settings-form" class="fta-settings-form">
			<h2><?php esc_html_e( 'General Settings', FORMTURA_TEXTDOMAIN ); ?></h2>
			
			<table class="form-table">
				<tbody>
					<tr>
						<th scope="row">
							<label for="fta-recaptcha-site-key"><?php esc_html_e( 'reCAPTCHA Site Key', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" 
								id="fta-recaptcha-site-key" 
								name="settings[recaptcha_site_key]" 
								value="<?php echo esc_attr( isset( $settings['recaptcha_site_key'] ) ? $settings['recaptcha_site_key'] : '' ); ?>" 
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Enter your Google reCAPTCHA v3 site key.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="fta-recaptcha-secret-key"><?php esc_html_e( 'reCAPTCHA Secret Key', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="password" 
								id="fta-recaptcha-secret-key" 
								name="settings[recaptcha_secret_key]" 
								value="<?php echo esc_attr( isset( $settings['recaptcha_secret_key'] ) ? $settings['recaptcha_secret_key'] : '' ); ?>" 
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Enter your Google reCAPTCHA v3 secret key.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-delete-data"><?php esc_html_e( 'Delete Data on Uninstall', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									id="fta-delete-data" 
									name="settings[delete_data_on_uninstall]" 
									value="1" 
									<?php checked( isset( $settings['delete_data_on_uninstall'] ) ? $settings['delete_data_on_uninstall'] : false, 1 ); ?>>
								<?php esc_html_e( 'Delete all plugin data when uninstalling', FORMTURA_TEXTDOMAIN ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Warning: This will permanently delete all forms, entries, and settings.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-disable-css"><?php esc_html_e( 'Disable Default CSS', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									id="fta-disable-css" 
									name="settings[disable_default_css]" 
									value="1" 
									<?php checked( isset( $settings['disable_default_css'] ) ? $settings['disable_default_css'] : false, 1 ); ?>>
								<?php esc_html_e( 'Disable default form styles', FORMTURA_TEXTDOMAIN ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Check this if you want to use your own custom CSS for forms.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-from-email"><?php esc_html_e( 'From Email Address', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="email" 
								id="fta-from-email" 
								name="settings[from_email]" 
								value="<?php echo esc_attr( isset( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' ) ); ?>" 
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Default email address for form notifications.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-from-name"><?php esc_html_e( 'From Name', FORMTURA_TEXTDOMAIN ); ?></label>
						</th>
						<td>
							<input type="text" 
								id="fta-from-name" 
								name="settings[from_name]" 
								value="<?php echo esc_attr( isset( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' ) ); ?>" 
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Default sender name for form notifications.', FORMTURA_TEXTDOMAIN ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php wp_nonce_field( 'formtura_admin', 'formtura_nonce' ); ?>
			
			<p class="submit">
				<button type="submit" class="button button-primary fta-save-settings">
					<?php esc_html_e( 'Save Settings', FORMTURA_TEXTDOMAIN ); ?>
				</button>
			</p>
		</form>
	</div><!-- .fta-card -->
</div><!-- .fta-admin-page -->

<script>
jQuery(document).ready(function($) {
	$('#fta-settings-form').on('submit', function(e) {
		e.preventDefault();
		
		var $form = $(this);
		var $button = $form.find('.fta-save-settings');
		var buttonText = $button.text();
		
		$button.prop('disabled', true).text('<?php esc_html_e( 'Saving...', FORMTURA_TEXTDOMAIN ); ?>');
		
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fta_save_settings',
				nonce: $('#formtura_nonce').val(),
				settings: $form.serializeArray().reduce(function(obj, item) {
					var name = item.name.replace('settings[', '').replace(']', '');
					obj[name] = item.value;
					return obj;
				}, {})
			},
			success: function(response) {
				if (response.success) {
					alert('<?php esc_html_e( 'Settings saved successfully!', FORMTURA_TEXTDOMAIN ); ?>');
				} else {
					alert(response.data.message || '<?php esc_html_e( 'Failed to save settings.', FORMTURA_TEXTDOMAIN ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'An error occurred while saving settings.', FORMTURA_TEXTDOMAIN ); ?>');
			},
			complete: function() {
				$button.prop('disabled', false).text(buttonText);
			}
		});
	});
});
</script>
