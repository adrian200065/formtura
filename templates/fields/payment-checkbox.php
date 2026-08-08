<?php
/**
 * Checkbox Items Payment Field Template
 *
 * Multi-select priced items. Prices on the page are display hints; the
 * server recomputes from the form definition (see Frontend\PaymentTotals).
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
$field_required = ! empty( $field['required'] );
$items          = fta_get_field_items( $field );
$show_price     = ! isset( $field['showPriceAfterLabels'] ) || ! empty( $field['showPriceAfterLabels'] );
$legend         = isset( $field['label'] ) ? $field['label'] : '';
$hide_label     = ! empty( $field['hideLabel'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-payment fta-field-payment-checkbox' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<fieldset class="fta-field-fieldset">
		<?php if ( $legend && ! $hide_label ) : ?>
			<legend class="fta-field-label<?php echo $field_required ? ' required' : ''; ?>">
				<?php echo esc_html( $legend ); ?>
			</legend>
		<?php endif; ?>

		<div class="fta-field-choices">
			<?php foreach ( $items as $index => $item ) : ?>
				<?php $item_id = fta_get_field_input_id( $field, $index ); ?>
				<div class="fta-choice-item">
					<input
						type="checkbox"
						id="<?php echo esc_attr( $item_id ); ?>"
						name="<?php echo esc_attr( $field_name ); ?>[]"
						class="fta-choice-input fta-payment-input"
						value="<?php echo esc_attr( $item['value'] ); ?>"
						data-price="<?php echo esc_attr( number_format( $item['price'], 2, '.', '' ) ); ?>"
						data-item-label="<?php echo esc_attr( $item['label'] ); ?>"
						<?php checked( $item['isDefault'], true ); ?>
					/>
					<label for="<?php echo esc_attr( $item_id ); ?>" class="fta-choice-label">
						<?php echo esc_html( $item['label'] ); ?>
						<?php if ( $show_price ) : ?>
							<span class="fta-choice-price"><?php echo esc_html( fta_format_price( $item['price'] ) ); ?></span>
						<?php endif; ?>
					</label>
				</div>
			<?php endforeach; ?>
		</div>
	</fieldset>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-payment-checkbox -->
