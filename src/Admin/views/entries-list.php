<?php
/**
 * Entries List View
 *
 * Admin page for displaying form entries.
 *
 * @package Formtura
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Get entries if form is selected.
$entries = [];
$form = null;
if ( $selected_form_id ) {
	$entries_db = new \Formtura\Database\Entries_DB();
	$entries = $entries_db->get_by_form( $selected_form_id );
	$form = fta_get_form( $selected_form_id );
}
?>

<div class="wrap fta-admin-page">
	<div class="fta-admin-header">
		<h1><?php esc_html_e( 'Form Entries', FORMTURA_TEXTDOMAIN ); ?></h1>
		<?php if ( $selected_form_id && ! empty( $entries ) ) : ?>
			<button class="fta-button fta-button-secondary fta-export-entries" data-form-id="<?php echo esc_attr( $selected_form_id ); ?>">
				<?php esc_html_e( 'Export Entries', FORMTURA_TEXTDOMAIN ); ?>
			</button>
		<?php endif; ?>
	</div><!-- .fta-admin-header -->

	<?php if ( empty( $forms ) ) : ?>
		<div class="fta-card">
			<div class="fta-empty-state">
				<h2><?php esc_html_e( 'No forms yet', FORMTURA_TEXTDOMAIN ); ?></h2>
				<p><?php esc_html_e( 'Create a form first to start receiving entries.', FORMTURA_TEXTDOMAIN ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-new' ) ); ?>" class="fta-button fta-button-primary">
					<?php esc_html_e( 'Create Your First Form', FORMTURA_TEXTDOMAIN ); ?>
				</a>
			</div><!-- .fta-empty-state -->
		</div><!-- .fta-card -->
	<?php else : ?>
		<div class="fta-card">
			<div class="fta-entries-filter">
				<label for="fta-form-select"><?php esc_html_e( 'Select Form:', FORMTURA_TEXTDOMAIN ); ?></label>
				<select id="fta-form-select" class="fta-form-select">
					<option value=""><?php esc_html_e( '-- Select a Form --', FORMTURA_TEXTDOMAIN ); ?></option>
					<?php foreach ( $forms as $form_item ) : ?>
						<option value="<?php echo esc_attr( $form_item['id'] ); ?>" <?php selected( $selected_form_id, $form_item['id'] ); ?>>
							<?php echo esc_html( $form_item['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</div><!-- .fta-entries-filter -->

			<?php if ( $selected_form_id ) : ?>
				<?php if ( empty( $entries ) ) : ?>
					<div class="fta-empty-state">
						<h2><?php esc_html_e( 'No entries yet', FORMTURA_TEXTDOMAIN ); ?></h2>
						<p><?php esc_html_e( 'This form hasn\'t received any submissions yet.', FORMTURA_TEXTDOMAIN ); ?></p>
					</div><!-- .fta-empty-state -->
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped fta-entries-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'ID', FORMTURA_TEXTDOMAIN ); ?></th>
								<th><?php esc_html_e( 'Entry Data', FORMTURA_TEXTDOMAIN ); ?></th>
								<th><?php esc_html_e( 'Status', FORMTURA_TEXTDOMAIN ); ?></th>
								<th><?php esc_html_e( 'Submitted', FORMTURA_TEXTDOMAIN ); ?></th>
								<th><?php esc_html_e( 'Actions', FORMTURA_TEXTDOMAIN ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $entries as $entry ) : ?>
								<?php
								$entry_data = maybe_unserialize( $entry['entry_data'] );
								$status_class = $entry['is_read'] ? 'read' : 'unread';
								?>
								<tr class="fta-entry-row fta-entry-<?php echo esc_attr( $status_class ); ?>">
									<td><?php echo esc_html( $entry['id'] ); ?></td>
									<td>
										<?php if ( is_array( $entry_data ) ) : ?>
											<div class="fta-entry-preview">
												<?php
												$preview_count = 0;
												foreach ( $entry_data as $key => $value ) :
													if ( $preview_count >= 3 ) break;
													?>
													<div class="fta-entry-field">
														<strong><?php echo esc_html( ucfirst( str_replace( '_', ' ', $key ) ) ); ?>:</strong>
														<?php echo esc_html( is_array( $value ) ? implode( ', ', $value ) : $value ); ?>
													</div>
													<?php
													$preview_count++;
												endforeach;
												?>
												<?php if ( count( $entry_data ) > 3 ) : ?>
													<a href="#" class="fta-view-entry" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
														<?php esc_html_e( 'View all fields...', FORMTURA_TEXTDOMAIN ); ?>
													</a>
												<?php endif; ?>
											</div>
										<?php endif; ?>
									</td>
									<td>
										<span class="fta-status fta-status-<?php echo esc_attr( $status_class ); ?>">
											<?php echo esc_html( $entry['is_read'] ? 'Read' : 'Unread' ); ?>
										</span>
									</td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['created_at'] ) ) ); ?></td>
									<td>
										<?php if ( ! $entry['is_read'] ) : ?>
											<a href="#" class="fta-mark-read" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
												<?php esc_html_e( 'Mark as Read', FORMTURA_TEXTDOMAIN ); ?>
											</a> |
										<?php endif; ?>
										<a href="#" class="fta-view-entry" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
											<?php esc_html_e( 'View', FORMTURA_TEXTDOMAIN ); ?>
										</a> |
										<a href="#" class="fta-delete-entry" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
											<?php esc_html_e( 'Delete', FORMTURA_TEXTDOMAIN ); ?>
										</a>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div><!-- .fta-card -->
	<?php endif; ?>
</div><!-- .fta-admin-page -->

<script>
jQuery(document).ready(function($) {
	// Handle form selection change
	$('#fta-form-select').on('change', function() {
		var formId = $(this).val();
		if (formId) {
			window.location.href = '<?php echo admin_url( 'admin.php?page=formtura-entries' ); ?>&form_id=' + formId;
		}
	});
});
</script>
