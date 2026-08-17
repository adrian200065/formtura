<?php
/**
 * Slider Field Template
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

$field_name     = fta_get_field_name( $field );
$field_input_id = fta_get_field_input_id( $field );
$field_required = ! empty( $field['required'] );

$min_value     = isset( $field['minValue'] ) ? $field['minValue'] : 0;
$max_value     = isset( $field['maxValue'] ) ? $field['maxValue'] : 10;
$increment     = ! empty( $field['increment'] ) ? $field['increment'] : 1;
$default_value = isset( $field['defaultValue'] ) ? $field['defaultValue'] : $min_value;
$value_display = isset( $field['valueDisplay'] ) ? $field['valueDisplay'] : __( 'Selected Value: {value}', 'formtura' );

// The {value} token is replaced live by assets/js/frontend.js; render the
// default so the text is correct before scripts run.
$display_text = str_replace( '{value}', $default_value, $value_display );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-slider' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-slider-container">
		<input
			type="range"
			id="<?php echo esc_attr( $field_input_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="fta-field-slider"
			min="<?php echo esc_attr( $min_value ); ?>"
			max="<?php echo esc_attr( $max_value ); ?>"
			step="<?php echo esc_attr( $increment ); ?>"
			value="<?php echo esc_attr( $default_value ); ?>"
			data-value-display="<?php echo esc_attr( $value_display ); ?>"
			<?php echo $field_required ? 'required' : ''; ?>
		/>
		<output
			class="fta-slider-value"
			for="<?php echo esc_attr( $field_input_id ); ?>"
		><?php echo esc_html( $display_text ); ?></output>
	</div>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-slider -->
