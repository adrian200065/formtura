<?php
/**
 * Star Rating Field Template
 *
 * Rendered as a radio group so the control works without JavaScript and stays
 * reachable by keyboard; assets/css/frontend.css paints the stars.
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
$max_rating     = ! empty( $field['maxRating'] ) ? (int) $field['maxRating'] : 5;
$legend         = isset( $field['label'] ) ? $field['label'] : '';
$hide_label     = ! empty( $field['hideLabel'] );
?>

<div class="<?php echo esc_attr( fta_get_field_wrapper_class( $field, 'fta-field-rating' ) ); ?>"<?php echo fta_get_field_wrapper_data( $field ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
	<fieldset class="fta-field-fieldset">
		<?php if ( $legend && ! $hide_label ) : ?>
			<legend class="fta-field-label<?php echo $field_required ? ' required' : ''; ?>">
				<?php echo esc_html( $legend ); ?>
			</legend>
		<?php endif; ?>

		<div class="fta-rating-stars">
			<?php for ( $star = 1; $star <= $max_rating; $star++ ) : ?>
				<?php $star_id = fta_get_field_input_id( $field, $star ); ?>
				<input
					type="radio"
					id="<?php echo esc_attr( $star_id ); ?>"
					name="<?php echo esc_attr( $field_name ); ?>"
					class="fta-rating-input"
					value="<?php echo esc_attr( $star ); ?>"
					<?php echo $field_required ? 'required' : ''; ?>
				/>
				<label for="<?php echo esc_attr( $star_id ); ?>" class="fta-rating-star">
					<span class="screen-reader-text">
						<?php
						printf(
							/* translators: 1: selected star, 2: maximum rating */
							esc_html__( '%1$s of %2$s stars', 'formtura' ),
							esc_html( $star ),
							esc_html( $max_rating )
						);
						?>
					</span>
					<span aria-hidden="true">&#9733;</span>
				</label>
			<?php endfor; ?>
		</div>
	</fieldset>

	<?php fta_field_description( $field ); ?>
</div><!-- /.fta-field-rating -->
