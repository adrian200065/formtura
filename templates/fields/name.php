<?php
/**
 * Name Field Template
 *
 * Renders a simple, first/last, or first/middle/last name control. The
 * multi-part formats post an array keyed by part, e.g. field_123[first].
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
$format         = isset( $field['format'] ) ? $field['format'] : 'first-last';
$hide_sublabels = ! empty( $field['hideSublabels'] );
$hide_label     = ! empty( $field['hideLabel'] );
$legend         = isset( $field['label'] ) ? $field['label'] : '';

// Which parts each format renders, in display order.
$formats = [
	'first-middle-last' => [ 'first', 'middle', 'last' ],
	'first-last'        => [ 'first', 'last' ],
];

$parts = isset( $formats[ $format ] ) ? $formats[ $format ] : [];

$sublabels = [
	'first'  => __( 'First Name', 'formtura' ),
	'middle' => __( 'Middle Name', 'formtura' ),
	'last'   => __( 'Last Name', 'formtura' ),
];

$placeholders = [
	'first'  => isset( $field['firstNamePlaceholder'] ) ? $field['firstNamePlaceholder'] : '',
	'middle' => isset( $field['middleNamePlaceholder'] ) ? $field['middleNamePlaceholder'] : '',
	'last'   => isset( $field['lastNamePlaceholder'] ) ? $field['lastNamePlaceholder'] : '',
];

$defaults = [
	'first'  => isset( $field['firstNameDefault'] ) ? $field['firstNameDefault'] : '',
	'middle' => isset( $field['middleNameDefault'] ) ? $field['middleNameDefault'] : '',
	'last'   => isset( $field['lastNameDefault'] ) ? $field['lastNameDefault'] : '',
];
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-name' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>

	<?php if ( empty( $parts ) ) : // Simple format: one input. ?>

		<?php fta_field_label( $field ); ?>

		<input
			type="text"
			id="<?php echo esc_attr( fta_get_field_input_id( $field ) ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="fta-field-input"
			placeholder="<?php echo esc_attr( isset( $field['placeholder'] ) ? $field['placeholder'] : '' ); ?>"
			value="<?php echo esc_attr( $defaults['first'] ); ?>"
			<?php echo $field_required ? 'required' : ''; ?>
		/>

	<?php else : ?>

		<fieldset class="fta-field-fieldset">
			<?php if ( $legend && ! $hide_label ) : ?>
				<legend class="fta-field-label<?php echo $field_required ? ' required' : ''; ?>">
					<?php echo esc_html( $legend ); ?>
				</legend>
			<?php endif; ?>

			<div class="fta-name-group fta-name-group-<?php echo esc_attr( count( $parts ) ); ?>">
				<?php foreach ( $parts as $part ) : ?>
					<?php $part_id = fta_get_field_input_id( $field, $part ); ?>
					<div class="fta-name-part">
						<input
							type="text"
							id="<?php echo esc_attr( $part_id ); ?>"
							name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $part ); ?>]"
							class="fta-field-input"
							placeholder="<?php echo esc_attr( $placeholders[ $part ] ); ?>"
							value="<?php echo esc_attr( $defaults[ $part ] ); ?>"
							<?php // The middle name is optional even on a required field. ?>
							<?php echo $field_required && 'middle' !== $part ? 'required' : ''; ?>
						/>
						<?php if ( ! $hide_sublabels ) : ?>
							<label for="<?php echo esc_attr( $part_id ); ?>" class="fta-name-sublabel">
								<?php echo esc_html( $sublabels[ $part ] ); ?>
							</label>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</fieldset>

	<?php endif; ?>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-name -->
