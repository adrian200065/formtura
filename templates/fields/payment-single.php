<?php
/**
 * Single Item Payment Field Template
 *
 * A fixed-price line item, always included in the total. The posted value
 * is an inclusion marker only; the server reads the price from the form
 * definition (see Frontend\PaymentTotals).
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
$price          = isset( $field['price'] ) && is_numeric( $field['price'] ) ? (float) $field['price'] : 0.0;
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-payment fta-field-payment-single' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-payment-single-price"><?php echo esc_html( fta_format_price( $price ) ); ?></div>

	<input
		type="hidden"
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>" value="1"
		class="fta-payment-input"
		data-price="<?php echo esc_attr( number_format( $price, 2, '.', '' ) ); ?>"
		data-item-label="<?php echo esc_attr( isset( $field['label'] ) ? $field['label'] : '' ); ?>"
	/>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-payment-single -->
