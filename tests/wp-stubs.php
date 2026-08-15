<?php
/**
 * Minimal WordPress function stubs for unit tests.
 *
 * Only covers what the plugin's templates and helpers call. Escaping stubs
 * mirror WordPress behaviour closely enough that assertions on rendered markup
 * are meaningful.
 *
 * @package Formtura
 */

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal stand-in for WordPress's WP_Error.
	 */
	class WP_Error {

		/**
		 * @var array<string, string[]>
		 */
		private $errors = [];

		/**
		 * @var array<string, mixed>
		 */
		private $error_data = [];

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( '' === $code ) {
				return;
			}

			$this->errors[ $code ][] = $message;

			if ( '' !== $data ) {
				$this->error_data[ $code ] = $data;
			}
		}

		public function get_error_code() {
			$codes = array_keys( $this->errors );

			return empty( $codes ) ? '' : $codes[0];
		}

		public function get_error_message( $code = '' ) {
			$code = '' === $code ? $this->get_error_code() : $code;

			return isset( $this->errors[ $code ][0] ) ? $this->errors[ $code ][0] : '';
		}

		public function get_error_data( $code = '' ) {
			$code = '' === $code ? $this->get_error_code() : $code;

			return isset( $this->error_data[ $code ] ) ? $this->error_data[ $code ] : null;
		}
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) {
		return $text;
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return esc_html( $text );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( $text, $domain = 'default' ) {
		echo htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) {
		echo $text;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Mirrors real WordPress: _sanitize_text_fields() early-returns '' for an
	 * array or object, rather than letting it reach a string cast. Without
	 * this guard, (string) on an array here would only emit a PHP warning in
	 * this stub - not the '' a caller written against real WordPress
	 * behaviour is entitled to assume.
	 */
	function sanitize_text_field( $str ) {
		if ( is_array( $str ) || is_object( $str ) ) {
			return '';
		}

		return trim( strip_tags( (string) $str ) );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $maybeint ) {
		return abs( (int) $maybeint );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( $class ) {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $class );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $text, $remove_breaks = false ) {
		$text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text );
		$text = strip_tags( $text );

		if ( $remove_breaks ) {
			$text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
		}

		return trim( $text );
	}
}

if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) {
		return strip_tags( (string) $data, '<p><a><strong><em><ul><ol><li><br><h1><h2><h3><h4><span><div>' );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, $echo = true ) {
		$result = (string) $checked === (string) $current ? ' checked="checked"' : '';

		if ( $echo ) {
			echo $result;
		}

		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, $echo = true ) {
		$result = (string) $selected === (string) $current ? ' selected="selected"' : '';

		if ( $echo ) {
			echo $result;
		}

		return $result;
	}
}

if ( ! function_exists( 'get_option' ) ) {
	/**
	 * Reads from $GLOBALS['fta_test_options'] when a test has seeded it, so
	 * settings-dependent code can be exercised without a database.
	 */
	function get_option( $option, $default = false ) {
		if ( isset( $GLOBALS['fta_test_options'] ) && array_key_exists( $option, $GLOBALS['fta_test_options'] ) ) {
			return $GLOBALS['fta_test_options'][ $option ];
		}

		return $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value, $autoload = null ) {
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * Reads from $GLOBALS['fta_test_transients'] so time-based throttling can
	 * be exercised without a real object cache. Expiration is not modelled -
	 * tests that care about expiry seed or clear the global directly.
	 */
	function get_transient( $key ) {
		return isset( $GLOBALS['fta_test_transients'][ $key ] ) ? $GLOBALS['fta_test_transients'][ $key ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['fta_test_transients'][ $key ] = $value;

		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['fta_test_transients'][ $key ] );

		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) {
		return $value;
	}
}

if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type, $gmt = 0 ) {
		return 'Y' === $type ? gmdate( 'Y' ) : gmdate( 'Y-m-d H:i:s' );
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( $capability ) {
		return false;
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( $args = [] ) {
		return [];
	}
}

if ( ! function_exists( 'get_terms' ) ) {
	function get_terms( $args = [] ) {
		return [];
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return $thing instanceof \WP_Error;
	}
}

if ( ! function_exists( 'wp_max_upload_size' ) ) {
	function wp_max_upload_size() {
		return 64 * 1024 * 1024;
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( $length = 12, $special_chars = true, $extra_special_chars = false ) {
		return substr( str_repeat( 'abcdefghijklmnopqrstuvwxyz0123456789', 4 ), 0, $length );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $filename ) {
		return preg_replace( '/[^A-Za-z0-9._-]/', '', (string) $filename );
	}
}

if ( ! function_exists( 'wp_check_filetype_and_ext' ) ) {
	function wp_check_filetype_and_ext( $file, $filename, $mimes = null ) {
		$ext  = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
		$map  = [
			'jpg'  => 'image/jpeg',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'gif'  => 'image/gif',
			'pdf'  => 'application/pdf',
			'txt'  => 'text/plain',
			'zip'  => 'application/zip',
		];

		return [
			'ext'             => isset( $map[ $ext ] ) ? $ext : false,
			'type'            => isset( $map[ $ext ] ) ? $map[ $ext ] : false,
			'proper_filename' => false,
		];
	}
}

if ( ! function_exists( 'wp_mkdir_p' ) ) {
	function wp_mkdir_p( $target ) {
		return is_dir( $target ) || mkdir( $target, 0777, true );
	}
}

if ( ! function_exists( 'wp_delete_file' ) ) {
	function wp_delete_file( $file ) {
		return file_exists( $file ) ? unlink( $file ) : false;
	}
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	function wp_upload_dir( $time = null, $create_dir = true, $refresh_cache = false ) {
		// Tests that need an isolated legacy upload tree seed
		// $GLOBALS['fta_test_upload_basedir']; everything else keeps the shared
		// default.
		$base = isset( $GLOBALS['fta_test_upload_basedir'] )
			? $GLOBALS['fta_test_upload_basedir']
			: sys_get_temp_dir() . '/formtura-tests-uploads';

		return [
			'path'    => $base,
			'url'     => 'https://example.com/uploads',
			'subdir'  => '',
			'basedir' => $base,
			'baseurl' => 'https://example.com/uploads',
			'error'   => false,
		];
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( $show = '', $filter = 'raw' ) {
		return 'Test Site';
	}
}

if ( ! function_exists( 'locate_template' ) ) {
	function locate_template( $template_names, $load = false, $require_once = true ) {
		return '';
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( $action = -1, $name = '_wpnonce', $referer = true, $echo = true ) {
		$field = sprintf( '<input type="hidden" name="%s" value="test-nonce">', $name );

		if ( $echo ) {
			echo $field;
		}

		return $field;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_unslash', $value );
		}

		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
		return true;
	}
}

if ( ! function_exists( 'wp_remote_post' ) ) {
	/**
	 * Delegates to the callable in $GLOBALS['fta_test_http_handler'] so tests can
	 * decide what the remote endpoint returns. Unhandled requests are an error:
	 * a unit test must never reach the network.
	 */
	function wp_remote_post( $url, $args = [] ) {
		if ( isset( $GLOBALS['fta_test_http_handler'] ) && is_callable( $GLOBALS['fta_test_http_handler'] ) ) {
			return call_user_func( $GLOBALS['fta_test_http_handler'], $url, $args );
		}

		return new \WP_Error( 'http_request_failed', 'No HTTP handler registered for this test.' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $response ) {
		return isset( $response['body'] ) ? $response['body'] : '';
	}
}

if ( ! class_exists( 'FTA_Test_Ajax_Response' ) ) {
	/**
	 * Thrown by the wp_send_json_* stubs below to stand in for wp_die()'s
	 * request termination without killing the PHPUnit process, so an AJAX
	 * handler that never returns after calling them (matching real WordPress
	 * behaviour) can still be exercised and asserted on in a test.
	 */
	class FTA_Test_Ajax_Response extends \Exception {
		/** @var bool */
		public $success;

		/** @var mixed */
		public $data;

		public function __construct( $success, $data ) {
			parent::__construct( $success ? 'wp_send_json_success' : 'wp_send_json_error' );
			$this->success = $success;
			$this->data    = $data;
		}
	}
}

if ( ! function_exists( 'wp_send_json_success' ) ) {
	function wp_send_json_success( $data = null ) {
		throw new FTA_Test_Ajax_Response( true, $data );
	}
}

if ( ! function_exists( 'wp_send_json_error' ) ) {
	function wp_send_json_error( $data = null ) {
		throw new FTA_Test_Ajax_Response( false, $data );
	}
}

if ( ! function_exists( 'check_ajax_referer' ) ) {
	/**
	 * Tests set $GLOBALS['fta_test_ajax_referer_valid'] = false to simulate a
	 * missing or incorrect nonce. WordPress halts the request in that case
	 * (the default $stop = true dies); this stub throws instead, so a caller
	 * that - correctly - never checks the return value still stops here
	 * rather than falling through to code that assumes a checked request.
	 */
	function check_ajax_referer( $action = -1, $query_arg = false, $stop = true ) {
		$valid = ! isset( $GLOBALS['fta_test_ajax_referer_valid'] ) || $GLOBALS['fta_test_ajax_referer_valid'];

		if ( ! $valid && $stop ) {
			throw new FTA_Test_Ajax_Response( false, [ 'message' => 'Nonce check failed.' ] );
		}

		return $valid ? 1 : false;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	/**
	 * Records every deleted option name in $GLOBALS['fta_test_deleted_options']
	 * so uninstall tests can assert on exactly what a run removed - and, just as
	 * importantly, assert that a retaining run removed nothing.
	 */
	function delete_option( $option ) {
		$GLOBALS['fta_test_deleted_options'][] = $option;
		unset( $GLOBALS['fta_test_options'][ $option ] );

		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( $option, $value = '', $deprecated = '', $autoload = null ) {
		$GLOBALS['fta_test_options'][ $option ] = $value;

		return true;
	}
}

if ( ! function_exists( 'wp_cache_flush' ) ) {
	function wp_cache_flush() {
		$GLOBALS['fta_test_cache_flushed'] = true;

		return true;
	}
}

if ( ! function_exists( 'get_current_blog_id' ) ) {
	function get_current_blog_id() {
		return isset( $GLOBALS['fta_test_blog_id'] ) ? (int) $GLOBALS['fta_test_blog_id'] : 1;
	}
}

if ( ! function_exists( 'wp_normalize_path' ) ) {
	/**
	 * Mirrors WordPress: backslashes to forward slashes, runs of slashes
	 * collapsed, and a Windows drive letter upper-cased.
	 */
	function wp_normalize_path( $path ) {
		$path = str_replace( '\\', '/', $path );
		$path = preg_replace( '|(?<=.)/+|', '/', $path );

		if ( ':' === substr( $path, 1, 1 ) ) {
			$path = ucfirst( $path );
		}

		return $path;
	}
}

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( $value ) {
		return rtrim( $value, '/\\' ) . '/';
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $value ) {
		return rtrim( $value, '/\\' );
	}
}
