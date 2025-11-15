<?php
/**
 * Text Field Template
 *
 * Template for single line text input.
 *
 * @package Formtura
 * @since 1.0.0
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_name = isset( $field['name'] ) ? $field['name'] : '';
$field_label = isset( $field['label'] ) ? $field['label'] : '';
$field_placeholder = isset( $field['placeholder'] ) ? $field['placeholder'] : '';
$field_description = isset( $field['description'] ) ? $field['description'] : '';
$field_required = isset( $field['required'] ) ? $field['required'] : false;
$field_value = isset( $field['value'] ) ? $field['value'] : '';
?>

<div class="fta-field fta-field-text">
	<?php if ( $field_label ) : ?>
		<label for="fta-field-<?php echo esc_attr( $field_name ); ?>" class="fta-field-label <?php echo $field_required ? 'required' : ''; ?>">
			<?php echo esc_html( $field_label ); ?>
		</label>
	<?php endif; ?>

	<input
		type="text"
		id="fta-field-<?php echo esc_attr( $field_name ); ?>"
		name="<?php echo esc_attr( $field_name ); ?>"
		class="fta-field-input"
		placeholder="<?php echo esc_attr( $field_placeholder ); ?>"
		value="<?php echo esc_attr( $field_value ); ?>"
		<?php echo $field_required ? 'required' : ''; ?>
	/>

	<?php if ( $field_description ) : ?>
		<span class="fta-field-description"><?php echo esc_html( $field_description ); ?></span>
	<?php endif; ?>
</div><!-- /.fta-field-text -->
