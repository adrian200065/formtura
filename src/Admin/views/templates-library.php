<?php
/**
 * Templates Library View
 *
 * Admin page for selecting form templates.
 *
 * @package Formtura
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<div class="wrap fta-admin-page">
	<div class="fta-admin-header">
		<h1><?php esc_html_e( 'Choose a Template', 'formtura' ); ?></h1>
	</div>

	<div class="fta-templates-grid">
		<?php foreach ( $templates as $template_id => $template ) : ?>
			<div class="fta-template-card">
				<div class="fta-template-icon">
					<span class="dashicons dashicons-<?php echo esc_attr( $template['icon'] ); ?>"></span>
				</div>
				<h3 class="fta-template-title"><?php echo esc_html( $template['title'] ); ?></h3>
				<p class="fta-template-description"><?php echo esc_html( $template['description'] ); ?></p>
				<button
					class="fta-button fta-button-primary fta-use-template"
					data-template-id="<?php echo esc_attr( $template_id ); ?>"
				>
					<?php esc_html_e( 'Use Template', 'formtura' ); ?>
				</button>
			</div>
		<?php endforeach; ?>
	</div>
</div>

<style>
.fta-templates-grid {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(17.5rem, 1fr));
	gap: 1.5rem;
	margin-block-start: 2rem;
}

.fta-template-card {
	background-color: hsla(0, 0%, 100%, 1);
	text-align: center;
	padding: 2rem;
	border: 1px solid hsla(0, 0%, 80%, 1);
	border-radius: 8px;
	transition: border-color .2s ease, box-shadow .2s ease, transform .2s ease;
}

.fta-template-card:hover {
	border-color: hsla(240, 100%, 50%, 1);
	box-shadow: 0 4px 12px rgba(0, 0, 0, .1);
	transform: translateY(-.125rem);
}

.fta-template-icon {
	background-color: hsla(240, 100%, 50%, 1);
	inline-size: 4rem;
	block-size: 4rem;
	display: flex;
	align-items: center;
	justify-content: center;
	margin: 0 auto 1rem;
	border-radius: 50%;
}

.fta-template-icon .dashicons {
	color: hsla(240, 100%, 50%, 1);
	font-size: 2rem;
	inline-size: 2rem;
	block-size: 2rem;
}

.fta-template-title {
	color: hsla(0, 0%, 20%, 1);
	font-size: 1.25rem;
	font-weight: 600;
	margin: 0 0 0.5rem;
}

.fta-template-description {
	color: hsla(0, 0%, 60%, 1);
	font-size: 0.875rem;
	line-height: 1.5;
	margin: 0 0 1.5rem;
}
</style>

<script>
jQuery(document).ready(function($) {
	$('.fta-use-template').on('click', function(e) {
		e.preventDefault();
		const templateId = $(this).data('template-id');
		const $button = $(this);

		$button.prop('disabled', true).text('<?php esc_html_e( 'Creating...', 'formtura' ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fta_create_from_template',
				template_id: templateId,
				nonce: '<?php echo wp_create_nonce( 'formtura_admin' ); ?>'
			},
			success: function(response) {
				if (response.success) {
					window.location.href = response.data.redirect_url;
				} else {
					alert(response.data.message);
					$button.prop('disabled', false).text('<?php esc_html_e( 'Use Template', 'formtura' ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e( 'An error occurred. Please try again.', 'formtura' ); ?>');
				$button.prop('disabled', false).text('<?php esc_html_e( 'Use Template', 'formtura' ); ?>');
			}
		});
	});
});
</script>
