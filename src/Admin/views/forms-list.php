<?php
/**
 * Forms List View
 *
 * Admin page for displaying all forms.
 *
 * @package Formtura
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$forms = fta_get_forms();
?>

<div class="wrap fta-admin-page">
	<div class="fta-admin-header">
		<div class="fta-admin-heading">
			<p class="fta-admin-eyebrow"><?php esc_html_e( 'Formtura', 'formtura' ); ?></p>
			<h1><?php esc_html_e( 'Forms', 'formtura' ); ?></h1>
			<p class="fta-admin-subtitle"><?php esc_html_e( 'Build, publish, and review every form from one workspace.', 'formtura' ); ?></p>
		</div>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-new' ) ); ?>" class="fta-button fta-button-primary">
			<?php esc_html_e( 'Add New Form', 'formtura' ); ?>
		</a>
	</div><!-- .fta-admin-header -->

	<?php if ( empty( $forms ) ) : ?>
		<div class="fta-card">
			<div class="fta-empty-state">
				<h2><?php esc_html_e( 'No forms yet', 'formtura' ); ?></h2>
				<p><?php esc_html_e( 'Create a form to start collecting responses.', 'formtura' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-new' ) ); ?>" class="fta-button fta-button-primary">
					<?php esc_html_e( 'Create Your First Form', 'formtura' ); ?>
				</a>
			</div><!-- .fta-empty-state -->
		</div><!-- .fta-card -->
	<?php else : ?>
		<div class="fta-card">
			<div class="fta-table-shell">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Title', 'formtura' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Entries', 'formtura' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'formtura' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Created', 'formtura' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Actions', 'formtura' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $forms as $form ) : ?>
						<tr>
							<td class="fta-table-primary">
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-builder&form_id=' . $form['id'] ) ); ?>">
										<?php echo esc_html( $form['title'] ); ?>
									</a>
								</strong>
							</td>
							<td>
								<?php
								$entries_db  = new \Formtura\Database\Entries_DB();
								$entry_count = $entries_db->get_count( $form['id'] );
								echo esc_html( $entry_count );
								?>
							</td>
							<td>
								<span class="fta-status fta-status-<?php echo esc_attr( $form['status'] ); ?>">
									<?php echo esc_html( ucfirst( $form['status'] ) ); ?>
								</span>
							</td>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $form['created_at'] ) ) ); ?></td>
							<td>
								<div class="fta-table-actions">
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-builder&form_id=' . $form['id'] ) ); ?>">
										<?php esc_html_e( 'Edit', 'formtura' ); ?>
									</a>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-entries&form_id=' . $form['id'] ) ); ?>">
										<?php esc_html_e( 'Entries', 'formtura' ); ?>
									</a>
									<button type="button" class="fta-link-button fta-link-button-danger fta-delete-form" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
										<?php esc_html_e( 'Delete', 'formtura' ); ?>
									</button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			</div>
		</div><!-- .fta-card -->
	<?php endif; ?>
</div><!-- .fta-admin-page -->
