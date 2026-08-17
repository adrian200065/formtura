<?php
/**
 * Form Wrapper Template
 *
 * Main form container template.
 *
 * @package Formtura
 * @since 1.0.0
 *
 * @var array $form Form data.
 * @var array $args Additional arguments.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$form_id = isset( $form['id'] ) ? $form['id'] : 0;
$form_title = isset( $form['title'] ) ? $form['title'] : '';
$form_description = isset( $form['description'] ) ? $form['description'] : '';
$form_fields = isset( $form['fields'] ) ? $form['fields'] : [];
$form_settings = isset( $form['settings'] ) ? $form['settings'] : [];
// The builder stores this under the camelCase key it posts (see
// Form_Builder::sanitize_settings_data()); snake_case here never matched a
// saved setting, so a custom label silently fell back to "Submit".
$submit_text = isset( $form_settings['submitButtonText'] ) ? $form_settings['submitButtonText'] : __( 'Submit', FORMTURA_TEXTDOMAIN );
$recaptcha = fta_get_recaptcha_config();
?>

<div class="fta-form-container" id="fta-form-<?php echo esc_attr( $form_id ); ?>">
	<form class="fta-form" data-form-id="<?php echo esc_attr( $form_id ); ?>" method="post" enctype="multipart/form-data">

		<?php if ( $form_title || $form_description ) : ?>
			<div class="fta-form-header">
				<?php if ( $form_title ) : ?>
					<h2 class="fta-form-title"><?php echo esc_html( $form_title ); ?></h2>
				<?php endif; ?>

				<?php if ( $form_description ) : ?>
					<div class="fta-form-description"><?php echo wp_kses_post( $form_description ); ?></div>
				<?php endif; ?>
			</div><!-- /.fta-form-header -->
		<?php endif; ?>

		<div class="fta-form-body">
			<?php
			if ( ! empty( $form_fields ) ) {
				foreach ( $form_fields as $field ) {
					$field_type = isset( $field['type'] ) ? $field['type'] : 'text';

					// Load field template.
					$rendered = fta_get_template_part( 'fields/' . $field_type, '', [ 'field' => $field ] );

					// A field type offered by the builder but missing a template
					// would otherwise vanish from the page without a trace.
					if ( ! $rendered ) {
						fta_log(
							sprintf( 'No frontend template for field type "%s" (form %d).', $field_type, $form_id ),
							'warning'
						);

						if ( defined( 'WP_DEBUG' ) && WP_DEBUG && current_user_can( 'manage_options' ) ) {
							printf(
								'<div class="fta-field fta-field-missing"><strong>%s</strong></div>',
								esc_html( sprintf(
									/* translators: %s: field type slug */
									__( 'Formtura: no frontend template for field type "%s". This notice is only visible to administrators with WP_DEBUG enabled.', FORMTURA_TEXTDOMAIN ),
									$field_type
								) )
							);
						}
					}
				}
			}
			?>
		</div><!-- /.fta-form-body -->

		<?php if ( $recaptcha['enabled'] && 'v2' === $recaptcha['version'] ) : ?>
			<?php // Google's API renders the checkbox into this container, and the
			// token lands in a textarea inside it - inside the form, so the
			// submission picks it up with the rest of the fields. ?>
			<div class="fta-field fta-field-recaptcha">
				<div class="fta-recaptcha"
					data-fta-recaptcha
					data-sitekey="<?php echo esc_attr( $recaptcha['site_key'] ); ?>"></div>
			</div><!-- /.fta-field-recaptcha -->
		<?php endif; ?>

		<div class="fta-form-footer">
			<button type="submit" class="fta-submit-button">
				<?php echo esc_html( $submit_text ); ?>
			</button>
		</div><!-- /.fta-form-footer -->

		<?php wp_nonce_field( 'formtura_submit_' . $form_id, 'fta_nonce' ); ?>
	</form>
</div><!-- /.fta-form-container -->
