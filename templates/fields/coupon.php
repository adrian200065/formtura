<?php
/**
 * Coupon Field Template
 *
 * Code entry plus an Apply control. The defined codes never render here -
 * Apply asks the server over AJAX, and the submission re-validates the
 * code independently (Frontend\PaymentTotals).
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
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-coupon' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-coupon" data-field-id="<?php echo esc_attr( isset( $field['id'] ) ? $field['id'] : '' ); ?>">
		<input
			type="text"
			id="<?php echo esc_attr( $field_input_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="fta-field-input fta-coupon-input"
			placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : __( 'Coupon code', 'formtura' ) ); ?>"
		/>
		<button type="button" class="fta-coupon-apply">
			<?php esc_html_e( 'Apply', 'formtura' ); ?>
		</button>
	</div>

	<span class="fta-coupon-status" role="status"></span>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-coupon -->
