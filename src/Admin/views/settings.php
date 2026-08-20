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
		<div class="fta-admin-heading">
			<p class="fta-admin-eyebrow"><?php esc_html_e( 'Configuration', 'formtura' ); ?></p>
			<h1><?php esc_html_e( 'Settings', 'formtura' ); ?></h1>
			<p class="fta-admin-subtitle"><?php esc_html_e( 'Manage form security, delivery defaults, and plugin behavior.', 'formtura' ); ?></p>
		</div>
	</div><!-- .fta-admin-header -->

	<div class="fta-card">
		<form id="fta-settings-form" class="fta-settings-form">
			<h2><?php esc_html_e( 'General Settings', 'formtura' ); ?></h2>
			
			<table class="form-table">
				<tbody>
					<?php $recaptcha_version = isset( $settings['recaptcha_version'] ) && 'v3' === $settings['recaptcha_version'] ? 'v3' : 'v2'; ?>

					<tr>
						<th scope="row">
							<label for="fta-recaptcha-version"><?php esc_html_e( 'reCAPTCHA Version', 'formtura' ); ?></label>
						</th>
						<td>
							<select id="fta-recaptcha-version" name="settings[recaptcha_version]">
								<option value="v2" <?php selected( $recaptcha_version, 'v2' ); ?>>
									<?php esc_html_e( 'v2 - "I\'m not a robot" checkbox', 'formtura' ); ?>
								</option>
								<option value="v3" <?php selected( $recaptcha_version, 'v3' ); ?>>
									<?php esc_html_e( 'v3 - invisible score based', 'formtura' ); ?>
								</option>
							</select>
							<p class="description">
								<?php esc_html_e( 'Keys are issued per version. A v2 key will not work with v3, or the other way around.', 'formtura' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-recaptcha-site-key"><?php esc_html_e( 'reCAPTCHA Site Key', 'formtura' ); ?></label>
						</th>
						<td>
							<input type="text"
								id="fta-recaptcha-site-key"
								name="settings[recaptcha_site_key]"
								value="<?php echo esc_attr( isset( $settings['recaptcha_site_key'] ) ? $settings['recaptcha_site_key'] : '' ); ?>"
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Your Google reCAPTCHA site key, for the version selected above.', 'formtura' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-recaptcha-secret-key"><?php esc_html_e( 'reCAPTCHA Secret Key', 'formtura' ); ?></label>
						</th>
						<td>
							<?php
							// Never re-render the saved secret - it is stored encrypted and
							// the form always resubmits this field blank unless the
							// administrator is actively changing it. See
							// Settings::encrypted_secret().
							?>
							<input type="password"
								id="fta-recaptcha-secret-key"
								name="settings[recaptcha_secret_key]"
								value=""
								class="regular-text"
								autocomplete="new-password"
								placeholder="<?php echo ! empty( $settings['recaptcha_secret_key'] ) ? esc_attr__( 'Leave blank to keep the saved secret key', 'formtura' ) : ''; ?>">
							<p class="description">
								<?php if ( ! empty( $settings['recaptcha_secret_key'] ) ) : ?>
									<?php esc_html_e( 'A secret key is already saved, encrypted. Enter a new one to replace it.', 'formtura' ); ?>
								<?php else : ?>
									<?php esc_html_e( 'Your matching secret key. reCAPTCHA is only applied to forms once both keys are saved.', 'formtura' ); ?>
								<?php endif; ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-recaptcha-score-threshold"><?php esc_html_e( 'reCAPTCHA v3 Score Threshold', 'formtura' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="fta-recaptcha-score-threshold"
								name="settings[recaptcha_score_threshold]"
								value="<?php echo esc_attr( isset( $settings['recaptcha_score_threshold'] ) ? $settings['recaptcha_score_threshold'] : 0.5 ); ?>"
								step="0.1"
								min="0"
								max="1"
								class="small-text">
							<p class="description">
								<?php esc_html_e( 'Submissions scoring below this are rejected. 1.0 is very likely human, 0.0 very likely a bot. Google suggests 0.5. Ignored for v2.', 'formtura' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-delete-data"><?php esc_html_e( 'Delete Data on Uninstall', 'formtura' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									id="fta-delete-data" 
									name="settings[delete_data_on_uninstall]" 
									value="1" 
									<?php checked( ! empty( $settings['delete_data_on_uninstall'] ) ); ?>>
								<?php esc_html_e( 'Delete all plugin data when uninstalling', 'formtura' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Warning: This will permanently delete all forms, entries, and settings.', 'formtura' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-entry-retention-days"><?php esc_html_e( 'Automatically Delete Entries After', 'formtura' ); ?></label>
						</th>
						<td>
							<input type="number"
								id="fta-entry-retention-days"
								name="settings[entry_retention_days]"
								value="<?php echo esc_attr( isset( $settings['entry_retention_days'] ) ? $settings['entry_retention_days'] : 0 ); ?>"
								step="1"
								min="0"
								class="small-text">
							<?php esc_html_e( 'days', 'formtura' ); ?>
							<p class="description">
								<?php esc_html_e( 'Entries older than this are deleted automatically, across all forms. Set to 0 to disable automatic deletion.', 'formtura' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-disable-css"><?php esc_html_e( 'Disable Default CSS', 'formtura' ); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox" 
									id="fta-disable-css" 
									name="settings[disable_default_css]" 
									value="1" 
									<?php checked( isset( $settings['disable_default_css'] ) ? $settings['disable_default_css'] : false, 1 ); ?>>
								<?php esc_html_e( 'Disable default form styles', 'formtura' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Check this if you want to use your own custom CSS for forms.', 'formtura' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-from-email"><?php esc_html_e( 'From Email Address', 'formtura' ); ?></label>
						</th>
						<td>
							<input type="email" 
								id="fta-from-email" 
								name="settings[from_email]" 
								value="<?php echo esc_attr( isset( $settings['from_email'] ) ? $settings['from_email'] : get_option( 'admin_email' ) ); ?>" 
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Default email address for form notifications.', 'formtura' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="fta-from-name"><?php esc_html_e( 'From Name', 'formtura' ); ?></label>
						</th>
						<td>
							<input type="text" 
								id="fta-from-name" 
								name="settings[from_name]" 
								value="<?php echo esc_attr( isset( $settings['from_name'] ) ? $settings['from_name'] : get_bloginfo( 'name' ) ); ?>" 
								class="regular-text">
							<p class="description">
								<?php esc_html_e( 'Default sender name for form notifications.', 'formtura' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>

			<?php wp_nonce_field( 'formtura_admin', 'formtura_nonce' ); ?>
			
			<p class="submit">
				<button type="submit" class="fta-button fta-button-primary fta-save-settings">
					<?php esc_html_e( 'Save Settings', 'formtura' ); ?>
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
		
		$button.prop('disabled', true).text('<?php esc_html_e( 'Saving...', 'formtura' ); ?>');
		
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
					window.FormturaAdmin.showNotice('<?php esc_html_e( 'Settings saved successfully.', 'formtura' ); ?>', 'success');
				} else {
					window.FormturaAdmin.showNotice(response.data.message || '<?php esc_html_e( 'Failed to save settings.', 'formtura' ); ?>', 'error');
				}
			},
			error: function() {
				window.FormturaAdmin.showNotice('<?php esc_html_e( 'An error occurred while saving settings.', 'formtura' ); ?>', 'error');
			},
			complete: function() {
				$button.prop('disabled', false).text(buttonText);
			}
		});
	});
});
</script>
