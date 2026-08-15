<?php
/**
 * File Upload Handler
 *
 * Validates and stores files submitted through File Upload fields.
 *
 * @package Formtura
 * @since 1.0.3
 */

namespace Formtura\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uploads class.
 */
class Uploads {

	/**
	 * Directory name the plugin used inside wp-content/uploads.
	 *
	 * Retained for the legacy tree only: new files go to the private vault.
	 */
	const UPLOAD_DIR = 'formtura';

	/**
	 * Private storage service.
	 *
	 * @var File_Storage
	 */
	private $storage;

	/**
	 * Directory new files are being written to during a single store() call.
	 *
	 * @var string
	 */
	private $current_dir = '';

	/**
	 * Constructor.
	 *
	 * @since 1.0.5
	 * @param File_Storage|null $storage Optional storage service. Injected by
	 *                                   tests so they write to a temporary
	 *                                   vault instead of the real one.
	 */
	public function __construct( $storage = null ) {
		$this->storage = $storage instanceof File_Storage ? $storage : new File_Storage();
	}

	/**
	 * The storage service backing this instance.
	 *
	 * @since 1.0.5
	 * @return File_Storage
	 */
	protected function storage() {
		return $this->storage;
	}

	/**
	 * Extensions that are never accepted, whatever the form allows.
	 *
	 * Server-side scripts would run if the webserver is misconfigured, and
	 * browser-executable documents (html, svg) can carry script that runs on
	 * the site's own origin.
	 *
	 * @var string[]
	 */
	private static $blocked_extensions = [
		'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'phtml', 'phar',
		'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'zsh',
		'jsp', 'jspx', 'asp', 'aspx', 'ashx', 'asmx',
		'exe', 'com', 'bat', 'cmd', 'msi', 'scr', 'dll', 'so', 'jar',
		'htaccess', 'htpasswd', 'ini', 'conf',
		'html', 'htm', 'shtml', 'svg', 'svgz', 'xhtml',
	];

	/**
	 * Whether a field's value arrives in $_FILES and is handled here.
	 *
	 * @since 1.0.4
	 * @param array $field Field configuration.
	 * @return bool
	 */
	public static function is_file_field( $field ) {
		$type = isset( $field['type'] ) ? $field['type'] : '';

		return in_array( $type, [ 'file-upload', 'camera' ], true );
	}

	/**
	 * Process every File Upload field on a form.
	 *
	 * @since 1.0.3
	 * @param array $form Form data.
	 * @return array|\WP_Error Map of field name => file records, or WP_Error.
	 */
	public function process_form_uploads( $form ) {
		$results  = [];
		$errors   = [];
		$orphaned = [];

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $results;
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! self::is_file_field( $field ) ) {
				continue;
			}

			$field_name = fta_get_field_name( $field );

			if ( '' === $field_name ) {
				continue;
			}

			$files = $this->collect_field_files( $field_name );

			if ( empty( $files ) ) {
				if ( ! empty( $field['required'] ) ) {
					$errors[ $field_name ] = sprintf(
						/* translators: %s: field label */
						__( '%s is required.', FORMTURA_TEXTDOMAIN ),
						isset( $field['label'] ) ? $field['label'] : $field_name
					);
				}

				continue;
			}

			// A single-file field must not accept a multi-file post.
			if ( empty( $field['allowMultiple'] ) && count( $files ) > 1 ) {
				$files = array_slice( $files, 0, 1 );
			}

			$stored = [];

			foreach ( $files as $file ) {
				$result = $this->handle_single_file( $file, $field );

				if ( is_wp_error( $result ) ) {
					$errors[ $field_name ] = $result->get_error_message();
					break;
				}

				$stored[] = $result;
			}

			if ( isset( $errors[ $field_name ] ) ) {
				// Files stored before this field failed are orphaned too, and
				// are not in $results because the field never completed. Keep
				// them separately so cleanup below can still reach them.
				if ( ! empty( $stored ) ) {
					$orphaned[] = $stored;
				}

				continue;
			}

			if ( ! empty( $stored ) ) {
				$results[ $field_name ] = $stored;
			}
		}

		if ( ! empty( $errors ) ) {
			// Files already moved in this request are orphaned; remove them.
			$this->cleanup( $results );
			$this->cleanup( $orphaned );

			return new \WP_Error(
				'upload_failed',
				__( 'Please correct the errors below.', FORMTURA_TEXTDOMAIN ),
				$errors
			);
		}

		return $results;
	}

	/**
	 * Normalize $_FILES for a field into a flat list of file arrays.
	 *
	 * PHP nests multi-file inputs by property rather than by file, so a
	 * `name[]` input arrives as [ 'name' => [...], 'size' => [...] ].
	 *
	 * @since 1.0.3
	 * @param string $field_name Field submission key.
	 * @return array[] List of individual file arrays.
	 */
	private function collect_field_files( $field_name ) {
		if ( ! isset( $_FILES[ $field_name ] ) ) {
			return [];
		}

		$entry = $_FILES[ $field_name ];
		$files = [];

		if ( is_array( $entry['name'] ) ) {
			foreach ( array_keys( $entry['name'] ) as $index ) {
				$files[] = [
					'name'     => $entry['name'][ $index ],
					'type'     => $entry['type'][ $index ],
					'tmp_name' => $entry['tmp_name'][ $index ],
					'error'    => $entry['error'][ $index ],
					'size'     => $entry['size'][ $index ],
				];
			}
		} else {
			$files[] = $entry;
		}

		// Drop empty file slots left by unused inputs.
		return array_values( array_filter( $files, function( $file ) {
			return UPLOAD_ERR_NO_FILE !== (int) $file['error'];
		} ) );
	}

	/**
	 * Validate and store a single uploaded file.
	 *
	 * @since 1.0.3
	 * @param array $file  Single $_FILES entry.
	 * @param array $field Field configuration.
	 * @return array|\WP_Error File record or error.
	 */
	protected function handle_single_file( $file, $field ) {
		$error = $this->check_php_upload_error( $file );

		if ( is_wp_error( $error ) ) {
			return $error;
		}

		// Guard against a forged path pointing outside PHP's upload area.
		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new \WP_Error( 'upload_invalid', __( 'The uploaded file could not be verified.', FORMTURA_TEXTDOMAIN ) );
		}

		$size_error = $this->check_size( $file, $field );

		if ( is_wp_error( $size_error ) ) {
			return $size_error;
		}

		$type_error = $this->check_type( $file, $field );

		if ( is_wp_error( $type_error ) ) {
			return $type_error;
		}

		return $this->store( $file );
	}

	/**
	 * Translate a PHP upload error code into a message.
	 *
	 * @since 1.0.3
	 * @param array $file Single $_FILES entry.
	 * @return true|\WP_Error
	 */
	private function check_php_upload_error( $file ) {
		switch ( (int) $file['error'] ) {
			case UPLOAD_ERR_OK:
				return true;

			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return new \WP_Error( 'upload_too_large', __( 'The file is larger than this server allows.', FORMTURA_TEXTDOMAIN ) );

			case UPLOAD_ERR_PARTIAL:
				return new \WP_Error( 'upload_partial', __( 'The file was only partially uploaded. Please try again.', FORMTURA_TEXTDOMAIN ) );

			case UPLOAD_ERR_NO_TMP_DIR:
			case UPLOAD_ERR_CANT_WRITE:
			case UPLOAD_ERR_EXTENSION:
				return new \WP_Error( 'upload_server_error', __( 'The file could not be saved. Please contact the site administrator.', FORMTURA_TEXTDOMAIN ) );

			default:
				return new \WP_Error( 'upload_failed', __( 'The file could not be uploaded.', FORMTURA_TEXTDOMAIN ) );
		}
	}

	/**
	 * Enforce the field's size limits.
	 *
	 * Field limits are expressed in megabytes.
	 *
	 * @since 1.0.3
	 * @param array $file  Single $_FILES entry.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error
	 */
	private function check_size( $file, $field ) {
		$size  = (int) $file['size'];
		$bytes = 1024 * 1024;

		if ( $size <= 0 ) {
			return new \WP_Error( 'upload_empty', __( 'The file is empty.', FORMTURA_TEXTDOMAIN ) );
		}

		$server_max = wp_max_upload_size();

		if ( $server_max > 0 && $size > $server_max ) {
			return new \WP_Error( 'upload_too_large', __( 'The file is larger than this server allows.', FORMTURA_TEXTDOMAIN ) );
		}

		if ( ! empty( $field['maxFileSize'] ) && $size > (float) $field['maxFileSize'] * $bytes ) {
			return new \WP_Error(
				'upload_too_large',
				sprintf(
					/* translators: %s: maximum size in megabytes */
					__( 'The file must be smaller than %sMB.', FORMTURA_TEXTDOMAIN ),
					$field['maxFileSize']
				)
			);
		}

		if ( ! empty( $field['minFileSize'] ) && $size < (float) $field['minFileSize'] * $bytes ) {
			return new \WP_Error(
				'upload_too_small',
				sprintf(
					/* translators: %s: minimum size in megabytes */
					__( 'The file must be larger than %sMB.', FORMTURA_TEXTDOMAIN ),
					$field['minFileSize']
				)
			);
		}

		return true;
	}

	/**
	 * Enforce the field's allowed file types.
	 *
	 * @since 1.0.3
	 * @param array $file  Single $_FILES entry.
	 * @param array $field Field configuration.
	 * @return true|\WP_Error
	 */
	private function check_type( $file, $field ) {
		$extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );

		if ( '' === $extension ) {
			return new \WP_Error( 'upload_no_extension', __( 'The file type could not be determined.', FORMTURA_TEXTDOMAIN ) );
		}

		if ( in_array( $extension, self::$blocked_extensions, true ) ) {
			return new \WP_Error( 'upload_blocked_type', __( 'This file type is not permitted.', FORMTURA_TEXTDOMAIN ) );
		}

		// The declared extension must match the actual contents, and be a type
		// WordPress recognises at all.
		$checked = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'] );

		if ( empty( $checked['ext'] ) || empty( $checked['type'] ) ) {
			return new \WP_Error( 'upload_unrecognised_type', __( 'This file type is not permitted.', FORMTURA_TEXTDOMAIN ) );
		}

		if ( strtolower( $checked['ext'] ) !== $extension ) {
			return new \WP_Error( 'upload_type_mismatch', __( 'The file contents do not match its extension.', FORMTURA_TEXTDOMAIN ) );
		}

		$allowed = $this->get_allowed_extensions( $field );

		if ( ! empty( $allowed ) && ! in_array( $extension, $allowed, true ) ) {
			return new \WP_Error(
				'upload_disallowed_type',
				sprintf(
					/* translators: %s: comma separated list of extensions */
					__( 'Allowed file types: %s.', FORMTURA_TEXTDOMAIN ),
					implode( ', ', $allowed )
				)
			);
		}

		return true;
	}

	/**
	 * Resolve the extension whitelist configured on a field.
	 *
	 * An empty result means "any type WordPress accepts".
	 *
	 * @since 1.0.3
	 * @param array $field Field configuration.
	 * @return string[] Lowercased extensions.
	 */
	private function get_allowed_extensions( $field ) {
		// Camera captures photos; the field's own type settings never widen
		// that to arbitrary files.
		if ( 'camera' === ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
			return [ 'jpg', 'jpeg', 'png', 'gif', 'webp' ];
		}

		$mode = isset( $field['allowedFileTypes'] ) ? $field['allowedFileTypes'] : 'specify';

		if ( 'specify' !== $mode || empty( $field['specifiedTypes'] ) ) {
			return [];
		}

		$extensions = array_map(
			function( $type ) {
				return strtolower( ltrim( trim( $type ), '.' ) );
			},
			explode( ',', $field['specifiedTypes'] )
		);

		$extensions = array_filter( $extensions );

		// A form must never widen the hard block list.
		return array_values( array_diff( array_unique( $extensions ), self::$blocked_extensions ) );
	}

	/**
	 * Move a validated file into the plugin's upload directory.
	 *
	 * @since 1.0.3
	 * @param array $file Single $_FILES entry.
	 * @return array|\WP_Error File record or error.
	 */
	private function store( $file ) {
		if ( ! function_exists( 'wp_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		// Fails closed: with no writable private vault there is nowhere safe to
		// put an upload, and falling back to a public directory is exactly the
		// behaviour this replaces.
		$dir = $this->storage->prepare_directory();

		if ( false === $dir ) {
			return new \WP_Error(
				'upload_store_failed',
				__( 'The file could not be saved. Please contact the site administrator.', FORMTURA_TEXTDOMAIN )
			);
		}

		$this->current_dir = $dir;

		// Filenames are randomised so stored files cannot be enumerated by
		// guessing the visitor's original name.
		$original  = $file['name'];
		$extension = strtolower( pathinfo( $original, PATHINFO_EXTENSION ) );
		$file['name'] = wp_generate_password( 24, false, false ) . '.' . $extension;

		$filter = [ $this, 'filter_upload_dir' ];
		add_filter( 'upload_dir', $filter );

		$moved = wp_handle_upload( $file, [
			'test_form' => false,
			'unique_filename_callback' => null,
		] );

		remove_filter( 'upload_dir', $filter );

		$this->current_dir = '';

		if ( isset( $moved['error'] ) ) {
			return new \WP_Error( 'upload_move_failed', $moved['error'] );
		}

		@chmod( $moved['file'], 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		// $moved['url'] is deliberately discarded: it points into the public
		// uploads tree, which is no longer where the file lives.
		$record = $this->storage->create_record( $original, $moved['file'], $moved['type'], (int) $file['size'] );

		if ( false === $record ) {
			wp_delete_file( $moved['file'] );

			return new \WP_Error(
				'upload_store_failed',
				__( 'The file could not be saved. Please contact the site administrator.', FORMTURA_TEXTDOMAIN )
			);
		}

		return $record;
	}

	/**
	 * Point wp_handle_upload() at the private vault for the current store().
	 *
	 * The URL fields are blanked rather than rewritten: there is no public URL
	 * for a vault file, and leaving a plausible-looking one here would let it
	 * reach entry metadata.
	 *
	 * @since 1.0.3
	 * @param array $dirs Upload directory parts.
	 * @return array Filtered parts.
	 */
	public function filter_upload_dir( $dirs ) {
		if ( '' === $this->current_dir ) {
			return $dirs;
		}

		$dirs['basedir'] = $this->storage->get_site_root();
		$dirs['path']    = $this->current_dir;
		$dirs['subdir']  = '';
		$dirs['baseurl'] = '';
		$dirs['url']     = '';
		$dirs['error']   = false;

		return $dirs;
	}

	/**
	 * Drop guard files into the upload directory.
	 *
	 * Stops the webserver executing anything stored here and prevents the
	 * directory being listed. Written once, then left alone.
	 *
	 * @since 1.0.3
	 * @param string $path Absolute directory path.
	 */
	public static function protect_upload_dir( $path ) {
		if ( ! wp_mkdir_p( $path ) ) {
			return;
		}

		$htaccess = $path . '/.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			$rules = "# Written by Formtura. Do not edit.\n"
				. "Options -Indexes\n"
				. "<Files *>\n"
				. "\tSetHandler none\n"
				. "\tSetHandler default-handler\n"
				. "\tOptions -ExecCGI\n"
				. "\tRemoveHandler .cgi .php .php3 .php4 .php5 .php7 .php8 .phtml .phar .pl .py .jsp .asp .aspx .shtml .sh\n"
				. "\tRemoveType .cgi .php .php3 .php4 .php5 .php7 .php8 .phtml .phar .pl .py .jsp .asp .aspx .shtml .sh\n"
				. "\tphp_flag engine off\n"
				. "</Files>\n";

			file_put_contents( $htaccess, $rules ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}

		$index = $path . '/index.php';

		if ( ! file_exists( $index ) ) {
			file_put_contents( $index, "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}

	/**
	 * Delete files stored earlier in a failed request.
	 *
	 * Public so other file-producing steps in the same submission (currently
	 * Signature) can remove files this class already moved to disk when a
	 * later step fails - a rejected submission must never leave files behind.
	 *
	 * @since 1.0.3
	 * @param array $results Map of field name => file records.
	 */
	public function cleanup( $results ) {
		$this->storage->delete_records( $results );
	}

	/**
	 * Collect absolute paths for files that should ride along with an email.
	 *
	 * @since 1.0.3
	 * @param array             $form       Form data.
	 * @param array             $entry_data Saved entry data.
	 * @param File_Storage|null $storage    Optional storage service.
	 * @return string[] Absolute file paths.
	 */
	public static function get_email_attachments( $form, $entry_data, $storage = null ) {
		$attachments = [];

		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $attachments;
		}

		$storage = $storage instanceof File_Storage ? $storage : new File_Storage();

		foreach ( $form['fields'] as $field ) {
			if ( ! isset( $field['type'] ) || 'file-upload' !== $field['type'] ) {
				continue;
			}

			if ( empty( $field['attachToEmail'] ) ) {
				continue;
			}

			$field_name = fta_get_field_name( $field );

			if ( empty( $entry_data[ $field_name ] ) || ! is_array( $entry_data[ $field_name ] ) ) {
				continue;
			}

			foreach ( $entry_data[ $field_name ] as $file ) {
				// Resolved through the storage gate, so a record cannot name
				// an arbitrary server file and have it mailed out.
				$path = $storage->resolve( $file );

				if ( false !== $path ) {
					$attachments[] = $path;
				}
			}
		}

		return $attachments;
	}
}
