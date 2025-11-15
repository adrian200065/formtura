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
		<h1><?php esc_html_e( 'All Forms', FORMTURA_TEXTDOMAIN ); ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-new' ) ); ?>" class="fta-button fta-button-primary">
			<?php esc_html_e( 'Add New Form', FORMTURA_TEXTDOMAIN ); ?>
		</a>
	</div><!-- .fta-admin-header -->

	<?php if ( empty( $forms ) ) : ?>
		<div class="fta-card">
			<div class="fta-empty-state">
				<h2><?php esc_html_e( 'No forms yet', FORMTURA_TEXTDOMAIN ); ?></h2>
				<p><?php esc_html_e( 'Create your first form to get started!', FORMTURA_TEXTDOMAIN ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-new' ) ); ?>" class="fta-button fta-button-primary">
					<?php esc_html_e( 'Create Your First Form', FORMTURA_TEXTDOMAIN ); ?>
				</a>
			</div><!-- .fta-empty-state -->
		</div><!-- .fta-card -->
	<?php else : ?>
		<div class="fta-card">
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', FORMTURA_TEXTDOMAIN ); ?></th>
						<th><?php esc_html_e( 'Entries', FORMTURA_TEXTDOMAIN ); ?></th>
						<th><?php esc_html_e( 'Status', FORMTURA_TEXTDOMAIN ); ?></th>
						<th><?php esc_html_e( 'Created', FORMTURA_TEXTDOMAIN ); ?></th>
						<th><?php esc_html_e( 'Actions', FORMTURA_TEXTDOMAIN ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $forms as $form ) : ?>
						<tr>
							<td>
								<strong>
									<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-builder&form_id=' . $form['id'] ) ); ?>">
										<?php echo esc_html( $form['title'] ); ?>
									</a>
								</strong>
							</td>
							<td>
								<?php
								$entries_db = new \Formtura\Database\Entries_DB();
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
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-builder&form_id=' . $form['id'] ) ); ?>">
									<?php esc_html_e( 'Edit', FORMTURA_TEXTDOMAIN ); ?>
								</a> |
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-entries&form_id=' . $form['id'] ) ); ?>">
									<?php esc_html_e( 'Entries', FORMTURA_TEXTDOMAIN ); ?>
								</a> |
								<a href="#" class="fta-delete-form" data-form-id="<?php echo esc_attr( $form['id'] ); ?>">
									<?php esc_html_e( 'Delete', FORMTURA_TEXTDOMAIN ); ?>
								</a>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div><!-- .fta-card -->
	<?php endif; ?>
</div><!-- .fta-admin-page -->
