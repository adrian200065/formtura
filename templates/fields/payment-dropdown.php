<?php
/**
 * Dropdown Items Payment Field Template
 *
 * Single-select priced items rendered as a dropdown. Prices on the page
 * are display hints; the server recomputes from the form definition.
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
$field_required = ! empty( $field['required'] );
$items          = fta_get_field_items( $field );
$show_price     = ! isset( $field['showPriceAfterLabels'] ) || ! empty( $field['showPriceAfterLabels'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-payment fta-field-payment-dropdown' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<select
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-select fta-payment-select"
		<?php echo $field_required ? 'required' : ''; ?>
	>
		<option value="" data-price="0.00"><?php esc_html_e( 'Select an item', 'formtura' ); ?></option>

		<?php foreach ( $items as $item ) : ?>
			<option
				value="<?php echo esc_attr( $item['value'] ); ?>"
				data-price="<?php echo esc_attr( number_format( $item['price'], 2, '.', '' ) ); ?>"
				data-item-label="<?php echo esc_attr( $item['label'] ); ?>"
				<?php selected( $item['isDefault'], true ); ?>
			><?php echo esc_html( $item['label'] . ( $show_price ? ' - ' . fta_format_price( $item['price'] ) : '' ) ); ?></option>
		<?php endforeach; ?>
	</select>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-payment-dropdown -->
