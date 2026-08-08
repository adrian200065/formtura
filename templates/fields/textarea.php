<?php
/**
 * Textarea Field Template
 *
 * Template for multi-line text input.
 *
 * @package Formtura
 * @since 1.0.0
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name        = fta_get_field_name( $field );
$field_input_id    = fta_get_field_input_id( $field );
$field_placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
$field_required    = ! empty( $field['required'] );
$field_readonly    = ! empty( $field['readOnly'] );
$field_value       = isset( $field['value'] ) ? $field['value'] : '';
$field_rows        = ! empty( $field['rows'] ) ? (int) $field['rows'] : 4;
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-textarea' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<textarea
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-textarea"
		rows="<?php echo esc_attr( $field_rows ); ?>"
		placeholder="<?php echo esc_attr( $field_placeholder ); ?>"
		<?php echo $field_readonly ? 'readonly' : ''; ?>
		<?php echo $field_required ? 'required' : ''; ?>
	><?php echo esc_textarea( $field_value ); ?></textarea>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-textarea -->
