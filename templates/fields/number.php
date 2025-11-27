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
$field_name        = isset( $field['name'] ) ? $field['name'] : $field_id;
$field_label       = isset( $field['label'] ) ? $field['label'] : '';
$field_placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
$field_description = isset( $field['description'] ) ? $field['description'] : '';
$field_required    = isset( $field['required'] ) ? $field['required'] : false;
$field_value       = isset( $field['value'] ) ? $field['value'] : '';
$field_min         = isset( $field['minValue'] ) ? $field['minValue'] : '';
$field_max         = isset( $field['maxValue'] ) ? $field['maxValue'] : '';
$field_step        = isset( $field['increment'] ) ? $field['increment'] : '';
$field_readonly    = isset( $field['readOnly'] ) && $field['readOnly'];
$enable_calc       = isset( $field['enableCalculation'] ) && $field['enableCalculation'];
$calc_formula      = isset( $field['calculationFormula'] ) ? $field['calculationFormula'] : '';
$css_classes       = isset( $field['cssClasses'] ) ? $field['cssClasses'] : '';
$hide_label        = isset( $field['hideLabel'] ) && $field['hideLabel'];

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

$wrapper_classes = 'fta-field fta-field-number';
if ( $css_classes ) {
	$wrapper_classes .= ' ' . esc_attr( $css_classes );
}
if ( $enable_calc ) {
	$wrapper_classes .= ' fta-field-calculated';
}
?>

<div class="<?php echo esc_attr( $wrapper_classes ); ?>">
	<?php if ( $field_label && ! $hide_label ) : ?>
		<label for="fta-field-<?php echo esc_attr( $field_name ); ?>" class="fta-field-label <?php echo $field_required ? 'required' : ''; ?>">
			<?php echo esc_html( $field_label ); ?>
		</label>
	<?php endif; ?>

	<input
		type="number"
		id="fta-field-<?php echo esc_attr( $field_name ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-input"
		placeholder="<?php echo esc_attr( $field_placeholder ); ?>"
		value="<?php echo esc_attr( $field_value ); ?>"
		data-field-id="<?php echo esc_attr( $field_id ); ?>"
		<?php echo $field_required ? 'required' : ''; ?>
		<?php echo $extra_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	/>

	<?php if ( $field_description ) : ?>
		<span class="fta-field-description"><?php echo esc_html( $field_description ); ?></span>
	<?php endif; ?>
</div><!-- /.fta-field-number -->
