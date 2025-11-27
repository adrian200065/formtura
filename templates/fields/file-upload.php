<?php
/**
 * File Upload Field Template
 *
 * Template for file upload input.
 *
 * @package Formtura
 * @since 1.0.0
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$field_id          = isset( $field['id'] ) ? $field['id'] : '';
$field_name        = isset( $field['name'] ) ? $field['name'] : $field_id;
$field_label       = isset( $field['label'] ) ? $field['label'] : '';
$field_description = isset( $field['description'] ) ? $field['description'] : '';
$field_required    = isset( $field['required'] ) ? $field['required'] : false;

// File upload specific settings
$allow_multiple     = isset( $field['allowMultiple'] ) ? $field['allowMultiple'] : false;
$allowed_file_types = isset( $field['allowedFileTypes'] ) ? $field['allowedFileTypes'] : 'specify';
$specified_types    = isset( $field['specifiedTypes'] ) ? $field['specifiedTypes'] : 'jpg, jpeg, jpe, png, gif';
$min_file_size      = isset( $field['minFileSize'] ) ? $field['minFileSize'] : '';
$max_file_size      = isset( $field['maxFileSize'] ) ? $field['maxFileSize'] : '';
$upload_text        = isset( $field['uploadText'] ) ? $field['uploadText'] : __( 'Drop a file here or click to upload', FORMTURA_TEXTDOMAIN );
$compact_upload_text = isset( $field['compactUploadText'] ) ? $field['compactUploadText'] : __( 'Choose File', FORMTURA_TEXTDOMAIN );

// Build accept attribute for file input
$accept = '';
if ( $allowed_file_types === 'specify' && ! empty( $specified_types ) ) {
	$types = array_map( 'trim', explode( ',', $specified_types ) );
	$mime_types = [];
	foreach ( $types as $type ) {
		$type = strtolower( $type );
		switch ( $type ) {
			case 'jpg':
			case 'jpeg':
			case 'jpe':
				$mime_types[] = 'image/jpeg';
				break;
			case 'png':
				$mime_types[] = 'image/png';
				break;
			case 'gif':
				$mime_types[] = 'image/gif';
				break;
			case 'pdf':
				$mime_types[] = 'application/pdf';
				break;
			case 'doc':
				$mime_types[] = 'application/msword';
				break;
			case 'docx':
				$mime_types[] = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
				break;
			case 'xls':
				$mime_types[] = 'application/vnd.ms-excel';
				break;
			case 'xlsx':
				$mime_types[] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
				break;
			default:
				$mime_types[] = '.' . $type;
				break;
		}
	}
	$accept = implode( ',', array_unique( $mime_types ) );
}

// CSS classes
$css_classes = isset( $field['cssClasses'] ) ? $field['cssClasses'] : '';
$is_compact  = strpos( $css_classes, 'fta_compact' ) !== false;

// Max file size display
$max_size_display = ! empty( $max_file_size ) ? $max_file_size : '256';
?>

<div class="fta-field fta-field-file-upload <?php echo esc_attr( $css_classes ); ?>">
	<?php if ( $field_label ) : ?>
		<label for="fta-field-<?php echo esc_attr( $field_name ); ?>" class="fta-field-label <?php echo $field_required ? 'required' : ''; ?>">
			<?php echo esc_html( $field_label ); ?>
		</label>
	<?php endif; ?>

	<?php if ( $is_compact ) : ?>
		<!-- Compact file upload style -->
		<div class="fta-file-upload-compact">
			<input
				type="file"
				id="fta-field-<?php echo esc_attr( $field_name ); ?>"
				name="<?php echo esc_attr( $field_name ); ?><?php echo $allow_multiple ? '[]' : ''; ?>"
				class="fta-file-upload-input-compact"
				data-field-id="<?php echo esc_attr( $field_id ); ?>"
				data-min-size="<?php echo esc_attr( $min_file_size ); ?>"
				data-max-size="<?php echo esc_attr( $max_file_size ); ?>"
				<?php echo ! empty( $accept ) ? 'accept="' . esc_attr( $accept ) . '"' : ''; ?>
				<?php echo $allow_multiple ? 'multiple' : ''; ?>
				<?php echo $field_required ? 'required' : ''; ?>
			/>
			<label for="fta-field-<?php echo esc_attr( $field_name ); ?>" class="fta-file-upload-compact-label">
				<?php echo esc_html( $compact_upload_text ); ?>
			</label>
			<span class="fta-file-upload-filename"></span>
		</div>
	<?php else : ?>
		<!-- Dropzone file upload style -->
		<label for="fta-field-<?php echo esc_attr( $field_name ); ?>" class="fta-file-upload">
			<input
				type="file"
				id="fta-field-<?php echo esc_attr( $field_name ); ?>"
				name="<?php echo esc_attr( $field_name ); ?><?php echo $allow_multiple ? '[]' : ''; ?>"
				class="fta-file-upload-input"
				data-field-id="<?php echo esc_attr( $field_id ); ?>"
				data-min-size="<?php echo esc_attr( $min_file_size ); ?>"
				data-max-size="<?php echo esc_attr( $max_file_size ); ?>"
				<?php echo ! empty( $accept ) ? 'accept="' . esc_attr( $accept ) . '"' : ''; ?>
				<?php echo $allow_multiple ? 'multiple' : ''; ?>
				<?php echo $field_required ? 'required' : ''; ?>
			/>
			<div class="fta-file-upload-icon">
				<svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" />
					<polyline points="17 8 12 3 7 8" />
					<line x1="12" y1="3" x2="12" y2="15" />
				</svg>
			</div>
			<div class="fta-file-upload-text"><?php echo esc_html( $upload_text ); ?></div>
			<div class="fta-file-upload-info">
				<?php
				printf(
					/* translators: %s: Maximum file size in MB */
					esc_html__( 'Maximum file size: %sMB', FORMTURA_TEXTDOMAIN ),
					esc_html( $max_size_display )
				);
				?>
			</div>
		</label>
		<div class="fta-file-upload-preview"></div>
	<?php endif; ?>

	<?php if ( $field_description ) : ?>
		<span class="fta-field-description"><?php echo esc_html( $field_description ); ?></span>
	<?php endif; ?>
</div><!-- /.fta-field-file-upload -->
