<?php
/**
 * Dropdown Field Template
 *
 * Template for single and multiple selection dropdowns.
 *
 * @package Formtura
 * @since 1.0.3
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
$is_multiple       = ! empty( $field['multipleSelection'] );
$choices           = fta_get_field_choices( $field );

// Multi-selects post an array, so the name needs the bracket suffix.
$input_name = $is_multiple ? $field_name . '[]' : $field_name;
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-select' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<select
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $input_name ); ?>"
		class="fta-field-select"
		<?php echo $is_multiple ? 'multiple size="' . esc_attr( min( count( $choices ), 5 ) ) . '"' : ''; ?>
		<?php echo $field_required ? 'required' : ''; ?>
	>
		<?php if ( ! $is_multiple ) : ?>
			<option value=""><?php echo esc_html( $field_placeholder ? $field_placeholder : __( 'Select an option', 'formtura' ) ); ?></option>
		<?php endif; ?>

		<?php foreach ( $choices as $choice ) : ?>
			<option
				value="<?php echo esc_attr( $choice['value'] ); ?>"
				<?php selected( $choice['isDefault'], true ); ?>
			><?php echo esc_html( $choice['label'] ); ?></option>
		<?php endforeach; ?>
	</select>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-select -->
