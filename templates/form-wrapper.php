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
$submit_text = isset( $form_settings['submit_button_text'] ) ? $form_settings['submit_button_text'] : __( 'Submit', FORMTURA_TEXTDOMAIN );
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
					fta_get_template_part( 'fields/' . $field_type, '', [ 'field' => $field ] );
				}
			}
			?>
		</div><!-- /.fta-form-body -->

		<div class="fta-form-footer">
			<button type="submit" class="fta-submit-button">
				<?php echo esc_html( $submit_text ); ?>
			</button>
		</div><!-- /.fta-form-footer -->

		<?php wp_nonce_field( 'formtura_submit_' . $form_id, 'fta_nonce' ); ?>
	</form>
</div><!-- /.fta-form-container -->
