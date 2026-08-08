<?php
/**
 * Text Field Template
 *
 * Template for single line text input.
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
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-text' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<input
		type="text"
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-input"
		placeholder="<?php echo esc_attr( $field_placeholder ); ?>"
		value="<?php echo esc_attr( $field_value ); ?>"
		<?php echo ! empty( $field['enableAutocomplete'] ) ? 'autocomplete="on"' : ''; ?>
		<?php echo $field_readonly ? 'readonly' : ''; ?>
		<?php echo $field_required ? 'required' : ''; ?>
	/>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-text -->
