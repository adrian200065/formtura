<?php
/**
 * Entries List View
 *
 * Admin page for displaying form entries.
 *
 * @package Formtura
 * @since 1.0.0
 */

use Formtura\Admin\Entry_Values;
use Formtura\Frontend\File_Download;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// How many answers a row previews before the detail panel is the way to see
// the rest.
$preview_limit    = 3;
$entries_per_page = 20;

// Get entries if form is selected.
$entries      = array();
$form         = null;
$labels       = array();
$total        = 0;
$total_pages  = 1;
$current_page = 1;

if ( $selected_form_id ) {
	$entries_db   = new \Formtura\Database\Entries_DB();
	$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification
	$total        = $entries_db->get_count( $selected_form_id );
	$total_pages  = max( 1, (int) ceil( $total / $entries_per_page ) );
	$current_page = min( $current_page, $total_pages );

	$entries = $entries_db->get_by_form(
		$selected_form_id,
		array(
			'page'     => $current_page,
			'per_page' => $entries_per_page,
		)
	);

	$form = fta_get_form( $selected_form_id );

	// Field names are `field_<timestamp>_<suffix>`; the form definition is the
	// only place their human labels exist.
	$labels = Entry_Values::labels( $form );
}

/**
 * Flatten one entry's stored data into label/value pairs.
 *
 * Values are not flat - lists, address parts, file records, a computed
 * payment order - so each is rendered through Entry_Values rather than
 * concatenated here.
 *
 * @param array $entry_data Stored entry data.
 * @param array $labels     Field name => label map.
 * @return array[]
 */
$fta_entry_pairs = static function ( array $entry_data, array $labels ) {
	$pairs = array();

	foreach ( $entry_data as $key => $value ) {
		$pairs[] = array(
			'key'   => (string) $key,
			'label' => Entry_Values::label( $key, $labels ),
			'text'  => Entry_Values::text_for( $key, $value ),
			'files' => Entry_Values::file_records( $value ),
		);
	}

	return $pairs;
};
?>

<div class="wrap fta-admin-page">
	<div class="fta-admin-header">
		<div class="fta-admin-heading">
			<p class="fta-admin-eyebrow"><?php esc_html_e( 'Responses', 'formtura' ); ?></p>
			<h1><?php esc_html_e( 'Form entries', 'formtura' ); ?></h1>
			<p class="fta-admin-subtitle"><?php esc_html_e( 'Review and export submissions without leaving WordPress.', 'formtura' ); ?></p>
		</div>
		<?php if ( $selected_form_id && ! empty( $entries ) ) : ?>
			<button class="fta-button fta-button-secondary fta-export-entries" data-form-id="<?php echo esc_attr( $selected_form_id ); ?>">
				<?php esc_html_e( 'Export Entries', 'formtura' ); ?>
			</button>
		<?php endif; ?>
	</div><!-- .fta-admin-header -->

	<?php if ( empty( $forms ) ) : ?>
		<div class="fta-card">
			<div class="fta-empty-state">
				<h2><?php esc_html_e( 'No forms yet', 'formtura' ); ?></h2>
				<p><?php esc_html_e( 'Create a form first to start receiving entries.', 'formtura' ); ?></p>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=formtura-new' ) ); ?>" class="fta-button fta-button-primary">
					<?php esc_html_e( 'Create Your First Form', 'formtura' ); ?>
				</a>
			</div><!-- .fta-empty-state -->
		</div><!-- .fta-card -->
	<?php else : ?>
		<div class="fta-card">
			<div class="fta-entries-filter">
				<label for="fta-form-select"><?php esc_html_e( 'Select Form:', 'formtura' ); ?></label>
				<select id="fta-form-select" class="fta-form-select">
					<option value=""><?php esc_html_e( '-- Select a Form --', 'formtura' ); ?></option>
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
						<h2><?php esc_html_e( 'No entries yet', 'formtura' ); ?></h2>
						<p><?php esc_html_e( 'This form hasn\'t received any submissions yet.', 'formtura' ); ?></p>
					</div><!-- .fta-empty-state -->
				<?php else : ?>
					<div class="fta-table-shell">
					<table class="wp-list-table widefat fixed striped fta-entries-table">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'ID', 'formtura' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Entry Data', 'formtura' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Status', 'formtura' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Submitted', 'formtura' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actions', 'formtura' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $entries as $entry ) : ?>
								<?php
								// Entries_DB returns the unserialized answers
								// under 'data'. Reading 'entry_data' - a key it
								// has never produced - left every preview blank.
								$entry_data   = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : array();
								$pairs        = $fta_entry_pairs( $entry_data, $labels );
								$status_class = $entry['is_read'] ? 'read' : 'unread';
								?>
								<tr class="fta-entry-row fta-entry-<?php echo esc_attr( $status_class ); ?>" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
									<td><?php echo esc_html( $entry['id'] ); ?></td>
									<td>
										<div class="fta-entry-preview">
											<?php foreach ( array_slice( $pairs, 0, $preview_limit ) as $pair ) : ?>
												<div class="fta-entry-field">
													<strong><?php echo esc_html( $pair['label'] ); ?>:</strong>
													<?php echo esc_html( $pair['text'] ); ?>
												</div>
											<?php endforeach; ?>

											<?php if ( count( $pairs ) > $preview_limit ) : ?>
												<a href="#" class="fta-view-entry" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
													<?php esc_html_e( 'View all fields...', 'formtura' ); ?>
												</a>
											<?php endif; ?>
										</div>

										<?php
										// Rendered with the row rather than fetched
										// on demand: the values are already loaded,
										// and escaping them here keeps the detail
										// view off a JSON round trip that would have
										// to re-escape everything client side.
										?>
										<div class="fta-entry-details" id="fta-entry-details-<?php echo esc_attr( $entry['id'] ); ?>" hidden>
											<?php if ( empty( $pairs ) ) : ?>
												<p class="fta-entry-empty"><?php esc_html_e( 'This entry has no stored field data.', 'formtura' ); ?></p>
											<?php endif; ?>

											<?php foreach ( $pairs as $pair ) : ?>
												<div class="fta-entry-field">
													<strong><?php echo esc_html( $pair['label'] ); ?>:</strong>
													<?php if ( ! empty( $pair['files'] ) ) : ?>
														<span class="fta-entry-files">
															<?php foreach ( $pair['files'] as $index => $record ) : ?>
																<?php
																// The record's stored path is a private
																// vault location; the download controller
																// is the only route to the bytes, and it
																// checks manage_options on every request.
																?>
																<a href="<?php echo esc_url( File_Download::url( $entry['id'], $pair['key'], (int) $index ) ); ?>">
																	<?php echo esc_html( isset( $record['name'] ) ? $record['name'] : __( 'Download', 'formtura' ) ); ?>
																</a>
															<?php endforeach; ?>
														</span>
													<?php else : ?>
														<?php echo esc_html( $pair['text'] ); ?>
													<?php endif; ?>
												</div>
											<?php endforeach; ?>
										</div>
									</td>
									<td>
										<span class="fta-status fta-status-<?php echo esc_attr( $status_class ); ?>">
											<?php echo esc_html( $entry['is_read'] ? __( 'Read', 'formtura' ) : __( 'Unread', 'formtura' ) ); ?>
										</span>
									</td>
									<td><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $entry['created_at'] ) ) ); ?></td>
									<td>
										<div class="fta-table-actions">
										<a href="#" class="fta-mark-read" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>" data-is-read="<?php echo $entry['is_read'] ? '1' : '0'; ?>">
											<?php echo esc_html( $entry['is_read'] ? __( 'Mark as Unread', 'formtura' ) : __( 'Mark as Read', 'formtura' ) ); ?>
										</a>
										<a href="#" class="fta-view-entry" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
											<?php esc_html_e( 'View', 'formtura' ); ?>
										</a>
										<a href="#" class="fta-delete-entry fta-link-button-danger" data-entry-id="<?php echo esc_attr( $entry['id'] ); ?>">
											<?php esc_html_e( 'Delete', 'formtura' ); ?>
										</a>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					</div>

					<?php if ( $total_pages > 1 ) : ?>
						<?php
						$page_url = static function ( $page ) use ( $selected_form_id ) {
							return add_query_arg(
								array(
									'page'    => 'formtura-entries',
									'form_id' => $selected_form_id,
									'paged'   => $page,
								),
								admin_url( 'admin.php' )
							);
						};
	?>
						<nav class="fta-entries-pagination" aria-label="<?php esc_attr_e( 'Entries pages', 'formtura' ); ?>">
							<?php if ( $current_page > 1 ) : ?>
								<a class="fta-button fta-button-ghost" href="<?php echo esc_url( $page_url( $current_page - 1 ) ); ?>">
									<?php esc_html_e( 'Previous', 'formtura' ); ?>
								</a>
							<?php endif; ?>

							<span class="fta-entries-pagination-status">
								<?php
								printf(
									/* translators: 1: current page, 2: total pages, 3: total entries */
									esc_html__( 'Page %1$s of %2$s (%3$s entries)', 'formtura' ),
									esc_html( number_format_i18n( $current_page ) ),
									esc_html( number_format_i18n( $total_pages ) ),
									esc_html( number_format_i18n( $total ) )
								);
								?>
							</span>

							<?php if ( $current_page < $total_pages ) : ?>
								<a class="fta-button fta-button-ghost" href="<?php echo esc_url( $page_url( $current_page + 1 ) ); ?>">
									<?php esc_html_e( 'Next', 'formtura' ); ?>
								</a>
							<?php endif; ?>
						</nav>
					<?php endif; ?>
				<?php endif; ?>
			<?php endif; ?>
		</div><!-- .fta-card -->
	<?php endif; ?>
</div><!-- .fta-admin-page -->
