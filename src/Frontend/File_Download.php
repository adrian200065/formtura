<?php
/**
 * Authenticated File Download
 *
 * The only browser-reachable route to a file in the private vault.
 *
 * The request names an entry, a field, and an index - never a path. The file
 * is looked up from that entry's stored metadata, so a request can only ever
 * reach a file the named entry actually references, and only an administrator
 * may make the request at all.
 *
 * No nonce is required. The action is read-only and authorization plus the
 * ownership lookup provide the security boundary, so links embedded in
 * notification emails keep working past a nonce's 24-hour lifetime instead of
 * decaying into confusing failures.
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
 * File_Download class.
 */
class File_Download {

	/**
	 * admin-post.php action name.
	 */
	const ACTION = 'fta_download_file';

	/**
	 * Capability required to retrieve any stored file.
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Private storage service.
	 *
	 * @var File_Storage
	 */
	private $storage;

	/**
	 * Constructor.
	 *
	 * @since 1.0.5
	 * @param File_Storage|null $storage Optional storage service.
	 */
	public function __construct( $storage = null ) {
		$this->storage = $storage instanceof File_Storage ? $storage : new File_Storage();
	}

	/**
	 * Register the download route.
	 *
	 * Deliberately only the authenticated action: admin_post_nopriv_* fires for
	 * logged-out visitors, which would hand every stored file to the public.
	 *
	 * @since 1.0.5
	 * @return void
	 */
	public function register() {
		add_action( 'admin_post_' . self::ACTION, [ $this, 'handle' ] );
	}

	/**
	 * Build the download URL for one stored file.
	 *
	 * @since 1.0.5
	 * @param int    $entry_id Entry the file belongs to.
	 * @param string $field    Field name holding the record.
	 * @param int    $index    Position within that field's record list.
	 * @return string
	 */
	public static function url( $entry_id, $field, $index = 0 ) {
		return add_query_arg(
			[
				'action'   => self::ACTION,
				'entry_id' => (int) $entry_id,
				'field'    => $field,
				'file'     => (int) $index,
			],
			admin_url( 'admin-post.php' )
		);
	}

	/**
	 * Serve one stored file to an authorized administrator.
	 *
	 * @since 1.0.5
	 * @return void
	 */
	public function handle() {
		if ( ! is_user_logged_in() || ! current_user_can( self::CAPABILITY ) ) {
			$this->deny( __( 'You do not have permission to access this file.', 'formtura' ), 403 );
		}

		$entry_id = isset( $_GET['entry_id'] ) ? absint( $_GET['entry_id'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
		$field    = isset( $_GET['field'] ) ? sanitize_text_field( wp_unslash( $_GET['field'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
		$index    = isset( $_GET['file'] ) ? absint( $_GET['file'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification

		if ( ! $entry_id || '' === $field ) {
			$this->deny( __( 'The requested file could not be found.', 'formtura' ), 404 );
		}

		$record = $this->find_record( $entry_id, $field, $index );

		if ( null === $record ) {
			$this->deny( __( 'The requested file could not be found.', 'formtura' ), 404 );
		}

		$path = $this->storage->resolve( $record );

		if ( false === $path ) {
			$this->deny( __( 'The requested file could not be found.', 'formtura' ), 404 );
		}

		$this->stream( $path, $record );
	}

	/**
	 * Look up one file record from the entry that owns it.
	 *
	 * The lookup itself is the ownership check: a record is only reachable
	 * through the entry whose data contains it.
	 *
	 * @since 1.0.5
	 * @param int    $entry_id Entry ID.
	 * @param string $field    Field name.
	 * @param int    $index    Record index.
	 * @return array|null
	 */
	private function find_record( $entry_id, $field, $index ) {
		$entry = fta_get_entry( $entry_id );

		if ( empty( $entry ) || ! is_array( $entry ) || empty( $entry['data'] ) || ! is_array( $entry['data'] ) ) {
			return null;
		}

		if ( ! isset( $entry['data'][ $field ] ) || ! is_array( $entry['data'][ $field ] ) ) {
			return null;
		}

		$records = $entry['data'][ $field ];

		if ( ! isset( $records[ $index ] ) || ! File_Storage::is_file_record( $records[ $index ] ) ) {
			return null;
		}

		return $records[ $index ];
	}

	/**
	 * Send the file.
	 *
	 * @since 1.0.5
	 * @param string $path   Resolved absolute path.
	 * @param array  $record Stored record, for its original filename.
	 * @return void
	 */
	private function stream( $path, $record ) {
		$size = filesize( $path );
		$name = isset( $record['name'] ) ? sanitize_file_name( $record['name'] ) : basename( $path );

		if ( '' === $name ) {
			$name = 'download';
		}

		// The stored type is not trusted for the response: it was verified at
		// upload time, but serving it back with a sniffable content type is how
		// a stored file turns into stored XSS. nosniff plus attachment
		// disposition means the browser downloads rather than renders.
		$type = isset( $record['type'] ) && is_string( $record['type'] ) && '' !== $record['type']
			? $record['type']
			: 'application/octet-stream';

		nocache_headers();

		$this->send_header( 'Content-Type: ' . $type );
		$this->send_header( 'Content-Disposition: attachment; filename="' . str_replace( '"', '', $name ) . '"' );
		$this->send_header( 'X-Content-Type-Options: nosniff' );
		$this->send_header( 'Content-Transfer-Encoding: binary' );

		if ( false !== $size ) {
			$this->send_header( 'Content-Length: ' . $size );
		}

		readfile( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		$this->finish();
	}

	/**
	 * Refuse the request.
	 *
	 * The same message is used for "no such entry", "no such field", and "no
	 * such file", so a caller cannot probe which entries or files exist.
	 *
	 * @since 1.0.5
	 * @param string $message Message to display.
	 * @param int    $status  HTTP status code.
	 * @return void
	 */
	private function deny( $message, $status ) {
		wp_die(
			esc_html( $message ),
			esc_html__( 'Formtura', 'formtura' ),
			[ 'response' => $status ]
		);
	}

	/**
	 * Emit one response header.
	 *
	 * Isolated so tests can assert on the headers without a real SAPI.
	 *
	 * @since 1.0.5
	 * @param string $header Full header line.
	 * @return void
	 */
	protected function send_header( $header ) {
		if ( defined( 'FORMTURA_TESTS' ) && FORMTURA_TESTS ) {
			$GLOBALS['fta_test_sent_headers'][] = $header;

			return;
		}

		header( $header );
	}

	/**
	 * End the request after a completed download.
	 *
	 * Isolated so tests can exercise streaming without killing the process.
	 *
	 * @since 1.0.5
	 * @return void
	 */
	protected function finish() {
		if ( defined( 'FORMTURA_TESTS' ) && FORMTURA_TESTS ) {
			return;
		}

		exit;
	}
}
