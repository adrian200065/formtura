<?php
/**
 * Address Field Template
 *
 * Composite input posting an array keyed by part, e.g. field_123[line1],
 * following the name field's pattern. The scheme option relabels the
 * region and postal parts for US or international audiences.
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
$hide_sublabels = ! empty( $field['hideSublabels'] );
$hide_label     = ! empty( $field['hideLabel'] );
$legend         = isset( $field['label'] ) ? $field['label'] : '';
$scheme         = isset( $field['scheme'] ) && 'international' === $field['scheme'] ? 'international' : 'us';

$sublabels = [
	'line1'   => __( 'Address Line 1', 'formtura' ),
	'line2'   => __( 'Address Line 2', 'formtura' ),
	'city'    => __( 'City', 'formtura' ),
	'state'   => 'international' === $scheme ? __( 'State / Province / Region', 'formtura' ) : __( 'State', 'formtura' ),
	'zip'     => 'international' === $scheme ? __( 'Postal Code', 'formtura' ) : __( 'ZIP Code', 'formtura' ),
	'country' => __( 'Country', 'formtura' ),
];

// line2 and country stay optional even when the field is required.
$optional_parts = [ 'line2', 'country' ];

// Display rows: full-width lines, then city/state, then zip/country.
$rows = [
	[ 'line1' ],
	[ 'line2' ],
	[ 'city', 'state' ],
	[ 'zip', 'country' ],
];
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-address' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<fieldset class="fta-field-fieldset">
		<?php if ( $legend && ! $hide_label ) : ?>
			<legend class="fta-field-label<?php echo $field_required ? ' required' : ''; ?>">
				<?php echo esc_html( $legend ); ?>
			</legend>
		<?php endif; ?>

		<?php foreach ( $rows as $row ) : ?>
			<div class="fta-address-row fta-address-row-<?php echo esc_attr( count( $row ) ); ?>">
				<?php foreach ( $row as $part ) : ?>
					<?php $part_id = fta_get_field_input_id( $field, $part ); ?>
					<div class="fta-address-part fta-address-part-<?php echo esc_attr( $part ); ?>">
						<input
							type="text"
							id="<?php echo esc_attr( $part_id ); ?>"
							name="<?php echo esc_attr( $field_name ); ?>[<?php echo esc_attr( $part ); ?>]"
							class="fta-field-input"
							<?php echo $field_required && ! in_array( $part, $optional_parts, true ) ? 'required' : ''; ?>
						/>
						<?php if ( ! $hide_sublabels ) : ?>
							<label for="<?php echo esc_attr( $part_id ); ?>" class="fta-address-sublabel">
								<?php echo esc_html( $sublabels[ $part ] ); ?>
							</label>
						<?php endif; ?>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>
	</fieldset>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-address -->
