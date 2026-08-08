<?php
/**
 * Signature Field Template
 *
 * A drawing canvas backed by a hidden input. frontend.js serializes each
 * stroke into the hidden input as a PNG data URL; the server verifies and
 * stores it (see Frontend\Signature).
 *
 * @package Formtura
 * @since 1.0.4
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
$field_required = ! empty( $field['required'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-signature' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-signature" data-fta-signature>
		<canvas class="fta-signature-canvas" width="600" height="180" aria-label="<?php esc_attr_e( 'Signature area. Draw your signature with mouse or touch.', FORMTURA_TEXTDOMAIN ); ?>"></canvas>

		<input
			type="hidden"
			id="<?php echo esc_attr( $field_input_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="fta-signature-value"
			<?php echo $field_required ? 'data-required="1"' : ''; ?>
		/>

		<button type="button" class="fta-signature-clear">
			<?php esc_html_e( 'Clear', FORMTURA_TEXTDOMAIN ); ?>
		</button>
	</div>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-signature -->
