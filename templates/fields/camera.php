<?php
/**
 * Camera Field Template
 *
 * A photo capture input. The capture attribute opens the device camera
 * directly on phones and degrades to a normal image picker on desktop.
 * Uploads flow through the same validated, protected storage as the file
 * upload field, restricted to images.
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

$field_name         = fta_get_field_name( $field );
$field_input_id     = fta_get_field_input_id( $field );
$field_required     = ! empty( $field['required'] );
$compact_upload_text = isset( $field['compactUploadText'] ) && '' !== $field['compactUploadText']
	? $field['compactUploadText']
	: __( 'Take Photo', 'formtura' );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-camera' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<div class="fta-file-upload-compact fta-camera-capture">
		<input
			type="file"
			id="<?php echo esc_attr( $field_input_id ); ?>"
			name="<?php echo esc_attr( $field_name ); ?>"
			class="fta-file-upload-input-compact"
			accept="image/*"
			capture="environment"
			<?php echo $field_required ? 'required' : ''; ?>
		/>
		<label for="<?php echo esc_attr( $field_input_id ); ?>" class="fta-file-upload-compact-label">
			<?php echo esc_html( $compact_upload_text ); ?>
		</label>
		<span class="fta-file-upload-filename"></span>
	</div>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-camera -->
