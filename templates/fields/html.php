<?php
/**
 * HTML Field Template
 *
 * Presentational block that outputs author-supplied markup. The content is
 * stored through wp_kses_post() when the form is saved, so it is echoed here
 * rather than escaped again.
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

$content = isset( $field['content'] ) ? $field['content'] : '';

if ( '' === trim( $content ) ) {
	return;
}
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-html' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<?php echo wp_kses_post( $content ); ?>
</div><!-- /.fta-field-html -->
