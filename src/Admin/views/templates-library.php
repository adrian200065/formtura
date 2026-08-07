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

$template_categories = [
	'all'         => [
		'label' => __( 'All templates', FORMTURA_TEXTDOMAIN ),
		'ids'   => array_keys( $templates ),
	],
	'starting'    => [
		'label' => __( 'Starting points', FORMTURA_TEXTDOMAIN ),
		'ids'   => [ 'blank' ],
	],
	'customer'    => [
		'label' => __( 'Customer forms', FORMTURA_TEXTDOMAIN ),
		'ids'   => [ 'contact', 'quote' ],
	],
	'engagement'  => [
		'label' => __( 'Engagement', FORMTURA_TEXTDOMAIN ),
		'ids'   => [ 'feedback', 'registration' ],
	],
	'hiring'      => [
		'label' => __( 'Hiring', FORMTURA_TEXTDOMAIN ),
		'ids'   => [ 'job_application' ],
	],
];

$template_category_for_id = static function( $template_id ) use ( $template_categories ) {
	foreach ( $template_categories as $category_id => $category ) {
		if ( 'all' !== $category_id && in_array( $template_id, $category['ids'], true ) ) {
			return $category_id;
		}
	}

	return 'all';
};
?>

<div class="wrap fta-admin-page fta-template-library">
	<header class="fta-template-hero">
		<div class="fta-template-hero-content">
			<p class="fta-admin-eyebrow"><?php esc_html_e( 'Formtura templates', FORMTURA_TEXTDOMAIN ); ?></p>
			<h1>
				<?php
				printf(
					/* translators: %d: number of available templates. */
					esc_html__( '%d ready-to-use form templates', FORMTURA_TEXTDOMAIN ),
					count( $templates )
				);
				?>
			</h1>
			<p><?php esc_html_e( 'Choose a starting point, preview its fields, and customize everything in the builder.', FORMTURA_TEXTDOMAIN ); ?></p>

			<div class="fta-template-search">
				<label class="screen-reader-text" for="fta-template-search">
					<?php esc_html_e( 'Search form templates', FORMTURA_TEXTDOMAIN ); ?>
				</label>
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<input
					id="fta-template-search"
					type="search"
					placeholder="<?php esc_attr_e( 'Search templates', FORMTURA_TEXTDOMAIN ); ?>"
					autocomplete="off"
				>
				<span class="fta-template-search-shortcut" aria-hidden="true">/</span>
			</div>
		</div>
	</header>

	<div class="fta-template-catalog">
		<aside class="fta-template-filters" aria-label="<?php esc_attr_e( 'Template filters', FORMTURA_TEXTDOMAIN ); ?>">
			<div class="fta-template-filter-heading">
				<p class="fta-template-filter-label"><?php esc_html_e( 'Browse by', FORMTURA_TEXTDOMAIN ); ?></p>
				<p><?php esc_html_e( 'Template type', FORMTURA_TEXTDOMAIN ); ?></p>
			</div>

			<nav class="fta-template-filter-list" aria-label="<?php esc_attr_e( 'Template categories', FORMTURA_TEXTDOMAIN ); ?>">
				<?php foreach ( $template_categories as $category_id => $category ) : ?>
					<button
						type="button"
						class="fta-template-filter<?php echo 'all' === $category_id ? ' is-active' : ''; ?>"
						data-template-filter="<?php echo esc_attr( $category_id ); ?>"
						aria-pressed="<?php echo 'all' === $category_id ? 'true' : 'false'; ?>"
					>
						<span><?php echo esc_html( $category['label'] ); ?></span>
						<span class="fta-template-filter-count"><?php echo esc_html( count( $category['ids'] ) ); ?></span>
					</button>
				<?php endforeach; ?>
			</nav>

			<div class="fta-template-filter-summary">
				<span class="dashicons dashicons-layout" aria-hidden="true"></span>
				<div>
					<strong><?php esc_html_e( 'Form layout', FORMTURA_TEXTDOMAIN ); ?></strong>
					<span><?php esc_html_e( 'Classic · one page', FORMTURA_TEXTDOMAIN ); ?></span>
				</div>
			</div>
		</aside>

		<main class="fta-template-results" aria-labelledby="fta-template-results-title">
			<div class="fta-template-results-header">
				<div>
					<p class="fta-template-filter-label"><?php esc_html_e( 'Template library', FORMTURA_TEXTDOMAIN ); ?></p>
					<h2 id="fta-template-results-title"><?php esc_html_e( 'Pick a form to start with', FORMTURA_TEXTDOMAIN ); ?></h2>
				</div>
				<p class="fta-template-results-count" role="status" aria-live="polite">
					<span data-template-visible-count><?php echo esc_html( count( $templates ) ); ?></span>
					<?php esc_html_e( 'templates', FORMTURA_TEXTDOMAIN ); ?>
				</p>
			</div>

			<div class="fta-templates-grid">
				<?php foreach ( $templates as $template_id => $template ) : ?>
					<?php
					$category_id     = $template_category_for_id( $template_id );
					$category        = $template_categories[ $category_id ];
					$fields          = isset( $template['fields'] ) && is_array( $template['fields'] ) ? $template['fields'] : [];
					$preview_fields  = array_slice( $fields, 0, 4 );
					$preview_variant = sanitize_html_class( $template_id );
					$search_text     = strtolower( $template['title'] . ' ' . $template['description'] . ' ' . $category['label'] );
					?>
					<article
						class="fta-template-card"
						data-template-card
						data-template-category="<?php echo esc_attr( $category_id ); ?>"
						data-template-search="<?php echo esc_attr( $search_text ); ?>"
					>
						<div class="fta-template-preview fta-template-preview--<?php echo esc_attr( $preview_variant ); ?>" aria-hidden="true">
							<div class="fta-template-preview-paper fta-template-preview-paper--<?php echo esc_attr( $preview_variant ); ?>">
								<div class="fta-template-preview-brand">
									<span class="fta-template-preview-mark"></span>
									<span><?php esc_html_e( 'FORMTURA', FORMTURA_TEXTDOMAIN ); ?></span>
								</div>

								<?php if ( 'contact' === $template_id ) : ?>
									<div class="fta-template-preview-context fta-template-preview-context--contact">
										<span class="fta-template-preview-context-label"><?php esc_html_e( 'Get in touch', FORMTURA_TEXTDOMAIN ); ?></span>
										<span class="fta-template-preview-context-meta"><?php esc_html_e( 'We usually reply within one business day', FORMTURA_TEXTDOMAIN ); ?></span>
									</div>
								<?php elseif ( 'quote' === $template_id ) : ?>
									<div class="fta-template-preview-context fta-template-preview-context--quote">
										<span class="fta-template-preview-context-label"><?php esc_html_e( 'Project brief', FORMTURA_TEXTDOMAIN ); ?></span>
										<span class="fta-template-preview-context-meta"><?php esc_html_e( 'Step 1 of 2', FORMTURA_TEXTDOMAIN ); ?></span>
									</div>
								<?php elseif ( 'registration' === $template_id ) : ?>
									<div class="fta-template-preview-context fta-template-preview-context--registration">
										<span class="fta-template-preview-date">
											<strong>24</strong>
											<small><?php esc_html_e( 'Oct', FORMTURA_TEXTDOMAIN ); ?></small>
										</span>
										<span class="fta-template-preview-context-label"><?php esc_html_e( 'Reserve your place', FORMTURA_TEXTDOMAIN ); ?></span>
										<span class="fta-template-preview-context-meta"><?php esc_html_e( 'Online event', FORMTURA_TEXTDOMAIN ); ?></span>
									</div>
								<?php elseif ( 'job_application' === $template_id ) : ?>
									<div class="fta-template-preview-context fta-template-preview-context--job_application">
										<span class="fta-template-preview-context-label"><?php esc_html_e( 'Join our team', FORMTURA_TEXTDOMAIN ); ?></span>
										<span class="fta-template-preview-context-meta"><?php esc_html_e( 'Candidate application', FORMTURA_TEXTDOMAIN ); ?></span>
									</div>
								<?php endif; ?>

								<div class="fta-template-preview-heading">
									<strong><?php echo esc_html( $template['title'] ); ?></strong>
									<span></span>
								</div>

								<?php if ( empty( $preview_fields ) ) : ?>
									<div class="fta-template-preview-empty">
										<span class="dashicons dashicons-plus-alt2"></span>
										<strong><?php esc_html_e( 'Blank canvas', FORMTURA_TEXTDOMAIN ); ?></strong>
										<small><?php esc_html_e( 'Add your first field', FORMTURA_TEXTDOMAIN ); ?></small>
									</div>
								<?php else : ?>
									<div class="fta-template-preview-fields fta-template-preview-fields--<?php echo esc_attr( $preview_variant ); ?>">
										<?php foreach ( $preview_fields as $field ) : ?>
											<?php
											$field_type = isset( $field['type'] ) ? sanitize_html_class( $field['type'] ) : 'text';
											$field_label = isset( $field['label'] ) ? $field['label'] : __( 'Field', FORMTURA_TEXTDOMAIN );
											?>
											<div class="fta-template-preview-field fta-template-preview-field-<?php echo esc_attr( $field_type ); ?>">
												<span class="fta-template-preview-label">
													<?php echo esc_html( $field_label ); ?>
													<?php if ( ! empty( $field['required'] ) ) : ?>
														<em>*</em>
													<?php endif; ?>
												</span>

												<?php if ( 'name' === $field_type ) : ?>
													<span class="fta-template-preview-name">
														<i></i>
														<i></i>
													</span>
												<?php elseif ( 'textarea' === $field_type ) : ?>
													<span class="fta-template-preview-control fta-template-preview-control-textarea"></span>
												<?php elseif ( in_array( $field_type, [ 'radio', 'checkbox', 'checkboxes' ], true ) ) : ?>
													<span class="fta-template-preview-options">
														<i></i><i></i><i></i>
													</span>
												<?php else : ?>
													<span class="fta-template-preview-control">
														<?php if ( 'select' === $field_type ) : ?>
															<i class="dashicons dashicons-arrow-down-alt2"></i>
														<?php endif; ?>
													</span>
												<?php endif; ?>
											</div>
										<?php endforeach; ?>
									</div>
								<?php endif; ?>
							</div>
						</div>

						<div class="fta-template-card-body">
							<div class="fta-template-card-heading">
								<h3 class="fta-template-title"><?php echo esc_html( $template['title'] ); ?></h3>
								<span class="fta-template-field-count">
									<?php
									printf(
										/* translators: %d: number of fields in a form template. */
										esc_html( _n( '%d field', '%d fields', count( $fields ), FORMTURA_TEXTDOMAIN ) ),
										count( $fields )
									);
									?>
								</span>
							</div>
							<p class="fta-template-description"><?php echo esc_html( $template['description'] ); ?></p>
							<span class="fta-template-category"><?php echo esc_html( $category['label'] ); ?></span>
						</div>

						<button
							type="button"
							class="fta-button fta-use-template"
							data-template-id="<?php echo esc_attr( $template_id ); ?>"
						>
							<?php esc_html_e( 'Use template', FORMTURA_TEXTDOMAIN ); ?>
						</button>
					</article>
				<?php endforeach; ?>
			</div>

			<div class="fta-template-no-results" data-template-no-results hidden>
				<span class="dashicons dashicons-search" aria-hidden="true"></span>
				<h3><?php esc_html_e( 'No matching templates', FORMTURA_TEXTDOMAIN ); ?></h3>
				<p><?php esc_html_e( 'Try a different search or choose another category.', FORMTURA_TEXTDOMAIN ); ?></p>
			</div>
		</main>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	const $search = $('#fta-template-search');
	const $cards = $('[data-template-card]');
	const $filters = $('[data-template-filter]');
	const $count = $('[data-template-visible-count]');
	const $noResults = $('[data-template-no-results]');
	let activeFilter = 'all';

	function updateTemplates() {
		const query = $search.val().trim().toLowerCase();
		let visibleCount = 0;

		$cards.each(function() {
			const $card = $(this);
			const matchesCategory = activeFilter === 'all' || $card.data('template-category') === activeFilter;
			const matchesSearch = !query || String($card.data('template-search')).includes(query);
			const isVisible = matchesCategory && matchesSearch;

			$card.prop('hidden', !isVisible);
			if (isVisible) {
				visibleCount += 1;
			}
		});

		$count.text(visibleCount);
		$noResults.prop('hidden', visibleCount !== 0);
	}

	$search.on('input', updateTemplates);
	$(document).on('keydown', function(event) {
		if (event.key === '/' && !$(event.target).is('input, textarea, select')) {
			event.preventDefault();
			$search.trigger('focus');
		}
	});

	$filters.on('click', function() {
		activeFilter = $(this).data('template-filter');
		$filters.removeClass('is-active').attr('aria-pressed', 'false');
		$(this).addClass('is-active').attr('aria-pressed', 'true');
		updateTemplates();
	});

	$('.fta-use-template').on('click', function() {
		const templateId = $(this).data('template-id');
		const $button = $(this);

		$button.prop('disabled', true).text('<?php echo esc_js( __( 'Creating…', FORMTURA_TEXTDOMAIN ) ); ?>');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'fta_create_from_template',
				template_id: templateId,
				nonce: '<?php echo esc_js( wp_create_nonce( 'formtura_admin' ) ); ?>'
			},
			success: function(response) {
				if (response.success) {
					window.location.href = response.data.redirect_url;
					return;
				}

				window.FormturaAdmin.showNotice(response.data.message, 'error');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Use template', FORMTURA_TEXTDOMAIN ) ); ?>');
			},
			error: function() {
				window.FormturaAdmin.showNotice('<?php echo esc_js( __( 'An error occurred. Please try again.', FORMTURA_TEXTDOMAIN ) ); ?>', 'error');
				$button.prop('disabled', false).text('<?php echo esc_js( __( 'Use template', FORMTURA_TEXTDOMAIN ) ); ?>');
			}
		});
	});
});
</script>
