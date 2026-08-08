<?php
/**
 * Date / Time Field Template
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

// The builder previews this field as a date picker; `dateTimeFormat` lets a
// form opt into a time or combined control.
$format = isset( $field['dateTimeFormat'] ) ? $field['dateTimeFormat'] : 'date';

$input_types = [
	'date'     => 'date',
	'time'     => 'time',
	'datetime' => 'datetime-local',
];

$input_type = isset( $input_types[ $format ] ) ? $input_types[ $format ] : 'date';

// Year range is expressed as an offset from the current year, e.g. "-10".
$min_attr = '';
$max_attr = '';

if ( 'date' === $input_type ) {
	$current_year = (int) current_time( 'Y' );

	if ( isset( $field['yearRangeStart'] ) && '' !== $field['yearRangeStart'] ) {
		$min_attr = ' min="' . esc_attr( ( $current_year + (int) $field['yearRangeStart'] ) . '-01-01' ) . '"';
	}

	if ( isset( $field['yearRangeEnd'] ) && '' !== $field['yearRangeEnd'] ) {
		$max_attr = ' max="' . esc_attr( ( $current_year + (int) $field['yearRangeEnd'] ) . '-12-31' ) . '"';
	}
}
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-datetime' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<input
		type="<?php echo esc_attr( $input_type ); ?>"
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-input"
		<?php echo $min_attr . $max_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		<?php echo $field_required ? 'required' : ''; ?>
	/>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-datetime -->
