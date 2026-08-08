<?php
/**
 * Multiple Choice Field Template
 *
 * Template for a single-answer radio group.
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
$field_required = ! empty( $field['required'] );
$choices        = fta_get_field_choices( $field );
$legend         = isset( $field['label'] ) ? $field['label'] : '';
$hide_label     = ! empty( $field['hideLabel'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-radio' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<fieldset class="fta-field-fieldset">
		<?php if ( $legend && ! $hide_label ) : ?>
			<legend class="fta-field-label<?php echo $field_required ? ' required' : ''; ?>">
				<?php echo esc_html( $legend ); ?>
			</legend>
		<?php endif; ?>

		<div class="fta-field-choices">
			<?php foreach ( $choices as $index => $choice ) : ?>
				<?php $choice_id = fta_get_field_input_id( $field, $index ); ?>
				<div class="fta-choice-item">
					<input
						type="radio"
						id="<?php echo esc_attr( $choice_id ); ?>"
						name="<?php echo esc_attr( $field_name ); ?>"
						class="fta-choice-input"
						value="<?php echo esc_attr( $choice['value'] ); ?>"
						<?php checked( $choice['isDefault'], true ); ?>
						<?php echo $field_required ? 'required' : ''; ?>
					/>
					<label for="<?php echo esc_attr( $choice_id ); ?>" class="fta-choice-label">
						<?php echo esc_html( $choice['label'] ); ?>
					</label>
				</div>
			<?php endforeach; ?>
		</div>
	</fieldset>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-radio -->
