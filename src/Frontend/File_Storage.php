<?php
/**
 * Private File Storage
 *
 * Owns every path decision for submitted files: where they live, how a stored
 * record maps back to a real file, and which files may be deleted.
 *
 * Files are kept outside the WordPress document root. The previous location,
 * wp-content/uploads/formtura, was protected only by a .htaccess guard file,
 * which nginx and IIS ignore entirely - on those servers every uploaded
 * resume and signature was world-readable to anyone who learned the URL.
 * Moving the tree out of the document root removes the webserver from the
 * access-control path altogether.
 *
 * @package Formtura
 * @since 1.0.5
 */

namespace Formtura\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * File_Storage class.
 */
class File_Storage {

	/**
	 * Directory name the plugin used inside wp-content/uploads.
	 */
	const LEGACY_DIR = 'formtura';

	/**
	 * Absolute path to the vault root, without a trailing slash.
	 *
	 * @var string
	 */
	private $root;

	/**
	 * Constructor.
	 *
	 * @since 1.0.5
	 * @param string|null $root Optional explicit vault root. Injected by tests;
	 *                          production callers use the derived default.
	 */
	public function __construct( $root = null ) {
		if ( null === $root || '' === $root ) {
			$root = self::default_root();
		}

		$this->root = untrailingslashit( wp_normalize_path( $root ) );
	}

	/**
	 * The vault root for this installation.
	 *
	 * Operators override the location with FORMTURA_PRIVATE_UPLOAD_DIR when the
	 * parent of ABSPATH is not writable. The derived default is suffixed with a
	 * hash of the WordPress path so that several installations sharing one
	 * parent directory cannot collide.
	 *
	 * @since 1.0.5
	 * @return string Absolute path, no trailing slash.
	 */
	public static function default_root() {
		if ( defined( 'FORMTURA_PRIVATE_UPLOAD_DIR' ) && FORMTURA_PRIVATE_UPLOAD_DIR ) {
			return untrailingslashit( wp_normalize_path( FORMTURA_PRIVATE_UPLOAD_DIR ) );
		}

		$wordpress_root = untrailingslashit( wp_normalize_path( ABSPATH ) );

		return dirname( $wordpress_root ) . '/.formtura-private-' . substr( hash( 'sha256', $wordpress_root ), 0, 12 );
	}

	/**
	 * The vault root.
	 *
	 * @since 1.0.5
	 * @return string
	 */
	public function get_root() {
		return $this->root;
	}

	/**
	 * The vault directory for the current site.
	 *
	 * Multisite keeps each site's files apart so that deleting or uninstalling
	 * on one site cannot reach another's.
	 *
	 * @since 1.0.5
	 * @return string
	 */
	public function get_site_root() {
		$blog_id = function_exists( 'get_current_blog_id' ) ? (int) get_current_blog_id() : 1;

		return $this->root . '/site-' . $blog_id;
	}

	/**
	 * The legacy public upload directory, if one is recognisable.
	 *
	 * @since 1.0.5
	 * @return string|false Absolute path, or false when uploads are unavailable.
	 */
	public function get_legacy_root() {
		$uploads = wp_upload_dir( null, false );

		if ( empty( $uploads['basedir'] ) ) {
			return false;
		}

		return untrailingslashit( wp_normalize_path( $uploads['basedir'] ) ) . '/' . self::LEGACY_DIR;
	}

	/**
	 * Create (if needed) the dated directory new files are written to.
	 *
	 * @since 1.0.5
	 * @return string|false Absolute directory path, or false when unwritable.
	 */
	public function prepare_directory() {
		$dir = $this->get_site_root() . '/' . gmdate( 'Y/m' );

		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		// Best effort: hosts that ignore the mode still get a directory outside
		// the document root, which is what actually provides the protection.
		@chmod( $dir, 0700 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		if ( ! is_writable( $dir ) ) {
			return false;
		}

		return $dir;
	}

	/**
	 * Whether the vault can be written to at all.
	 *
	 * @since 1.0.5
	 * @return bool
	 */
	public function is_available() {
		return false !== $this->prepare_directory();
	}

	/**
	 * Convert an absolute vault path into the value stored on the entry.
	 *
	 * @since 1.0.5
	 * @param string $absolute Absolute path to a file inside the vault.
	 * @return string|false Vault-relative path, or false when outside.
	 */
	public function relative_path( $absolute ) {
		$absolute = wp_normalize_path( (string) $absolute );
		$prefix   = $this->get_site_root() . '/';

		if ( 0 !== strpos( $absolute, $prefix ) ) {
			return false;
		}

		return substr( $absolute, strlen( $prefix ) );
	}

	/**
	 * Build the entry-metadata record for a stored file.
	 *
	 * The record deliberately carries no URL and no absolute path: it is
	 * persisted, rendered in the admin, and passed to notification formatting,
	 * so anything in it should be safe to expose.
	 *
	 * @since 1.0.5
	 * @param string $name     Visitor-visible original filename.
	 * @param string $absolute Absolute path of the stored file.
	 * @param string $type     Verified MIME type.
	 * @param int    $size     Size in bytes.
	 * @return array|false Record, or false when the path is outside the vault.
	 */
	public function create_record( $name, $absolute, $type, $size ) {
		$relative = $this->relative_path( $absolute );

		if ( false === $relative ) {
			return false;
		}

		return [
			'name' => sanitize_file_name( $name ),
			'path' => $relative,
			'type' => (string) $type,
			'size' => (int) $size,
		];
	}

	/**
	 * Whether a value looks like a file record this class manages.
	 *
	 * @since 1.0.5
	 * @param mixed $record Candidate value.
	 * @return bool
	 */
	public static function is_file_record( $record ) {
		return is_array( $record ) && ( isset( $record['path'] ) || isset( $record['file'] ) );
	}

	/**
	 * Resolve a stored record to a readable absolute path.
	 *
	 * This is the only bridge from persisted data to a filesystem read, so it
	 * fails closed: anything that is not demonstrably an existing file inside
	 * the vault (or a recognised legacy location) returns false.
	 *
	 * @since 1.0.5
	 * @param array $record Stored file record.
	 * @return string|false Absolute path, or false.
	 */
	public function resolve( $record ) {
		if ( ! is_array( $record ) ) {
			return false;
		}

		if ( isset( $record['path'] ) ) {
			return $this->resolve_relative( $record['path'] );
		}

		if ( isset( $record['file'] ) ) {
			return $this->resolve_legacy( $record['file'] );
		}

		return false;
	}

	/**
	 * Resolve a vault-relative path.
	 *
	 * @since 1.0.5
	 * @param string $relative Stored relative path.
	 * @return string|false
	 */
	private function resolve_relative( $relative ) {
		if ( ! is_string( $relative ) || '' === $relative ) {
			return false;
		}

		// A null byte can truncate the path inside a C-level filesystem call,
		// so a value containing one is never trustworthy.
		if ( false !== strpos( $relative, "\0" ) ) {
			return false;
		}

		$relative = wp_normalize_path( $relative );

		// Records store relative paths only; an absolute value means the record
		// was tampered with or written by code that bypassed create_record().
		if ( '/' === substr( $relative, 0, 1 ) || preg_match( '#^[A-Za-z]:/#', $relative ) ) {
			return false;
		}

		$site_root = $this->get_site_root();
		$candidate = $site_root . '/' . ltrim( $relative, '/' );

		// realpath() collapses ".." and symlinks against the real filesystem,
		// which is what makes the prefix comparison below meaningful.
		$real = realpath( $candidate );

		if ( false === $real ) {
			return false;
		}

		$real      = wp_normalize_path( $real );
		$real_root = realpath( $site_root );

		if ( false === $real_root ) {
			return false;
		}

		$real_root = wp_normalize_path( $real_root );

		if ( 0 !== strpos( $real, $real_root . '/' ) ) {
			return false;
		}

		return is_file( $real ) ? $real : false;
	}

	/**
	 * Resolve a pre-1.0.5 record that stored an absolute public path.
	 *
	 * Legacy metadata is not rewritten during migration, so an old record must
	 * keep working by mapping its year/month suffix into the vault. The old
	 * public URL in such records is ignored entirely.
	 *
	 * @since 1.0.5
	 * @param string $absolute Absolute path recorded before the vault existed.
	 * @return string|false
	 */
	private function resolve_legacy( $absolute ) {
		if ( ! is_string( $absolute ) || '' === $absolute || false !== strpos( $absolute, "\0" ) ) {
			return false;
		}

		$absolute    = wp_normalize_path( $absolute );
		$legacy_root = $this->get_legacy_root();

		if ( false === $legacy_root ) {
			return false;
		}

		// Only paths the plugin itself wrote are recognised; an arbitrary
		// absolute path in a record is not a licence to read it.
		if ( 0 !== strpos( $absolute, $legacy_root . '/' ) ) {
			return false;
		}

		$suffix = substr( $absolute, strlen( $legacy_root ) + 1 );

		// After migration the file lives in the vault under the same suffix.
		$migrated = $this->resolve_relative( $suffix );

		if ( false !== $migrated ) {
			return $migrated;
		}

		// Before migration it is still in the legacy tree.
		return is_file( $absolute ) ? $absolute : false;
	}

	/**
	 * Delete every managed file referenced by an entry's data.
	 *
	 * Values that are not file records - ordinary text answers, nested arrays
	 * of scalars - are ignored rather than treated as an error, because entry
	 * data mixes both freely.
	 *
	 * @since 1.0.5
	 * @param mixed $entry_data Saved entry data, or any nested subset of it.
	 * @return bool True when every recognised record was removed.
	 */
	public function delete_records( $entry_data ) {
		if ( ! is_array( $entry_data ) ) {
			return true;
		}

		$success = true;

		if ( self::is_file_record( $entry_data ) ) {
			return $this->delete_record( $entry_data );
		}

		foreach ( $entry_data as $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( ! $this->delete_records( $value ) ) {
				$success = false;
			}
		}

		return $success;
	}

	/**
	 * Delete one resolved record.
	 *
	 * @since 1.0.5
	 * @param array $record Stored file record.
	 * @return bool
	 */
	private function delete_record( $record ) {
		$path = $this->resolve( $record );

		// An unresolvable record is not a failure: the file may already be gone,
		// or the record may point outside anything this class owns. Either way
		// there is nothing here that may be deleted.
		if ( false === $path ) {
			return true;
		}

		return wp_delete_file( $path ) || ! file_exists( $path );
	}

	/**
	 * Remove this site's entire vault directory.
	 *
	 * Called only from an explicitly destructive uninstall.
	 *
	 * @since 1.0.5
	 * @return bool
	 */
	public function remove_site_files() {
		return $this->delete_tree( $this->get_site_root() );
	}

	/**
	 * Remove the legacy public upload directory.
	 *
	 * @since 1.0.5
	 * @return bool
	 */
	public function remove_legacy_files() {
		$legacy_root = $this->get_legacy_root();

		if ( false === $legacy_root ) {
			return true;
		}

		return $this->delete_tree( $legacy_root );
	}

	/**
	 * Recursively delete a directory the plugin owns.
	 *
	 * @since 1.0.5
	 * @param string $dir Absolute directory path.
	 * @return bool
	 */
	private function delete_tree( $dir ) {
		$dir = wp_normalize_path( $dir );

		if ( ! is_dir( $dir ) ) {
			return true;
		}

		// Symlinked directories are unlinked, never descended into, so a link
		// planted inside the vault cannot redirect deletion elsewhere.
		if ( is_link( $dir ) ) {
			return unlink( $dir );
		}

		$success = true;

		foreach ( scandir( $dir ) as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $dir . '/' . $item;

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				if ( ! $this->delete_tree( $path ) ) {
					$success = false;
				}

				continue;
			}

			if ( ! @unlink( $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors
				$success = false;
			}
		}

		return @rmdir( $dir ) && $success; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}

	/**
	 * Log a filesystem problem, when debugging is enabled.
	 *
	 * @since 1.0.5
	 * @param string $message Message to record.
	 * @return void
	 */
	protected function log( $message ) {
		$debug = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( ! $debug && function_exists( 'fta_get_setting' ) ) {
			$debug = (bool) fta_get_setting( 'debug_mode' );
		}

		if ( $debug ) {
			error_log( 'Formtura: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
		}
	}
}
