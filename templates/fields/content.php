<?php
/**
 * Content Field Template
 *
 * Presentational block of author-supplied rich content. Stored through
 * wp_kses_post() when the form is saved; kses'd again on output the same
 * way html.php is.
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

$content = isset( $field['content'] ) ? $field['content'] : '';

if ( '' === trim( $content ) ) {
	return;
}
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-content' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo wp_kses_post( $content ); ?>
</div><!-- /.fta-field-content -->
