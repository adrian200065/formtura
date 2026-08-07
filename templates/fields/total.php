<?php
/**
 * Total Field Template
 *
 * Displays the running order total. The posted value is display-side
 * convenience only - the server recomputes the amount from the form
 * definition and ignores whatever the browser sent (Frontend\PaymentTotals).
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

$enable_summary = ! empty( $field['enableSummary'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-total' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php // No for-target: this field has no input to label. ?>
	<?php fta_field_label( $field ); ?>

	<?php if ( $enable_summary ) : ?>
		<table class="fta-order-summary">
			<thead>
				<tr>
					<th scope="col"><?php esc_html_e( 'Item', FORMTURA_TEXTDOMAIN ); ?></th>
					<th scope="col"><?php esc_html_e( 'Price', FORMTURA_TEXTDOMAIN ); ?></th>
				</tr>
			</thead>
			<tbody class="fta-order-summary-body"></tbody>
		</table>
	<?php endif; ?>

	<div class="fta-total-display">
		<span class="fta-total-label"><?php esc_html_e( 'Total', FORMTURA_TEXTDOMAIN ); ?></span>
		<span class="fta-total-amount"><?php echo esc_html( fta_format_price( 0 ) ); ?></span>
	</div>
</div><!-- /.fta-field-total -->
