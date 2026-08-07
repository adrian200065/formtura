<?php
/**
 * Hidden Field Template
 *
 * Carries a fixed value through the submission. Renders no label, description,
 * or wrapper markup so it cannot affect form layout.
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

$field_name = fta_get_field_name( $field );

if ( isset( $field['value'] ) ) {
	$field_value = $field['value'];
} elseif ( isset( $field['defaultValue'] ) ) {
	$field_value = $field['defaultValue'];
} else {
	$field_value = '';
}
?>

<input
	type="hidden"
	id="<?php echo esc_attr( fta_get_field_input_id( $field ) ); ?>"
	name="<?php echo esc_attr( $field_name ); ?>"
	value="<?php echo esc_attr( $field_value ); ?>"
	data-field-id="<?php echo esc_attr( $field_name ); ?>"
/>
