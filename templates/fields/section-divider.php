<?php
/**
 * Section Divider Field Template
 *
 * Presentational heading plus horizontal rule. No input.
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

$label      = isset( $field['label'] ) ? $field['label'] : '';
$hide_label = ! empty( $field['hideLabel'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-section-divider' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php if ( $label && ! $hide_label ) : ?>
		<h3 class="fta-section-title"><?php echo esc_html( $label ); ?></h3>
	<?php endif; ?>

	<?php fta_field_description( $field ); ?>

	<hr class="fta-section-rule" />
</div><!-- /.fta-field-section-divider -->
