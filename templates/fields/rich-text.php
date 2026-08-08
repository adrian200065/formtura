<?php
/**
 * Rich Text Field Template
 *
 * Ships as a tall plain textarea (a deliberate decision - see the
 * 2026-08-07 field types spec). The builder's editor content, if any,
 * becomes the default value with markup stripped, since a plain textarea
 * would otherwise display raw tags.
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
$rows           = isset( $field['rows'] ) && absint( $field['rows'] ) > 0 ? absint( $field['rows'] ) : 7;
$default        = isset( $field['content'] ) ? wp_strip_all_tags( $field['content'] ) : '';
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-rich-text' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php fta_field_label( $field, $field_input_id ); ?>

	<textarea
		id="<?php echo esc_attr( $field_input_id ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-textarea fta-rich-text-area"
		rows="<?php echo esc_attr( $rows ); ?>"
		<?php echo $field_required ? 'required' : ''; ?>
	><?php echo esc_textarea( $default ); ?></textarea>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-rich-text -->
