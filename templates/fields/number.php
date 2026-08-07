<?php
/**
 * Number Field Template
 *
 * Template for number input with calculation support.
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

$field_id          = isset( $field['id'] ) ? $field['id'] : '';
$field_name        = fta_get_field_name( $field );
$field_input_id    = fta_get_field_input_id( $field );
$field_placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
$field_required    = ! empty( $field['required'] );
$field_value       = isset( $field['value'] ) ? $field['value'] : '';
$field_min         = isset( $field['minValue'] ) ? $field['minValue'] : '';
$field_max         = isset( $field['maxValue'] ) ? $field['maxValue'] : '';
$field_step        = isset( $field['increment'] ) ? $field['increment'] : '';
$field_readonly    = isset( $field['readOnly'] ) && $field['readOnly'];
$enable_calc       = isset( $field['enableCalculation'] ) && $field['enableCalculation'];
$calc_formula      = isset( $field['calculationFormula'] ) ? $field['calculationFormula'] : '';

// Build additional attributes
$extra_attrs = '';

if ( $field_min !== '' ) {
	$extra_attrs .= ' min="' . esc_attr( $field_min ) . '"';
}
if ( $field_max !== '' ) {
	$extra_attrs .= ' max="' . esc_attr( $field_max ) . '"';
}
if ( $field_step !== '' ) {
	$extra_attrs .= ' step="' . esc_attr( $field_step ) . '"';
}
if ( $field_readonly || $enable_calc ) {
	$extra_attrs .= ' readonly';
}
if ( $enable_calc && $calc_formula ) {
	$extra_attrs .= ' data-calculation="' . esc_attr( $calc_formula ) . '"';
	$extra_attrs .= ' data-field-id="' . esc_attr( $field_id ) . '"';
}

$wrapper_classes = fta_get_field_wrapper_class( $field, 'fta-field-number' );
if ( $enable_calc ) {
	$wrapper_classes .= ' fta-field-calculated';
}
?>

<div class="<?php echo esc_attr( $wrapper_classes ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<input
		type="number"
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-input"
		placeholder="<?php echo esc_attr( $field_placeholder ); ?>"
		value="<?php echo esc_attr( $field_value ); ?>"
		data-field-id="<?php echo esc_attr( $field_id ); ?>"
		<?php echo $field_required ? 'required' : ''; ?>
		<?php echo $extra_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	/>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-number -->
