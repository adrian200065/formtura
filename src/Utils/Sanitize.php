<?php
/**
 * Sanitize Utility Class
 *
 * Custom sanitization methods for form data.
 *
 * @package Formtura
 * @since 1.0.0
 */

namespace Formtura\Utils;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sanitize class.
 */
class Sanitize {

	/**
	 * Sanitize field value based on type.
	 *
	 * @since 1.0.0
	 * @param mixed  $value Field value.
	 * @param string $type Field type.
	 * @return mixed Sanitized value.
	 */
	public static function field( $value, $type = 'text' ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( $value );

			case 'url':
				return esc_url_raw( $value );

			case 'number':
				return is_numeric( $value ) ? floatval( $value ) : 0;

			case 'textarea':
				return sanitize_textarea_field( $value );

			case 'checkbox':
			case 'radio':
			case 'select':
				if ( is_array( $value ) ) {
					return array_map( 'sanitize_text_field', $value );
				}
				return sanitize_text_field( $value );

			case 'file':
				// File uploads are handled separately.
				return $value;

			case 'html':
				return wp_kses_post( $value );

			case 'text':
			default:
				return sanitize_text_field( $value );
		}
	}

	/**
	 * Sanitize array recursively.
	 *
	 * @since 1.0.0
	 * @param array $array Array to sanitize.
	 * @return array Sanitized array.
	 */
	public static function array( $array ) {
		if ( ! is_array( $array ) ) {
			return [];
		}

		foreach ( $array as $key => $value ) {
			if ( is_array( $value ) ) {
				$array[ $key ] = self::array( $value );
			} else {
				$array[ $key ] = sanitize_text_field( $value );
			}
		}

		return $array;
	}

	/**
	 * Sanitize JSON string.
	 *
	 * @since 1.0.0
	 * @param string $json JSON string.
	 * @return string Sanitized JSON string.
	 */
	public static function json( $json ) {
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return '';
		}

		return wp_json_encode( self::array( $data ) );
	}

	/**
	 * Sanitize boolean value.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to sanitize.
	 * @return bool Boolean value.
	 */
	public static function boolean( $value ) {
		return filter_var( $value, FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * Sanitize integer value.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to sanitize.
	 * @return int Integer value.
	 */
	public static function integer( $value ) {
		return absint( $value );
	}

	/**
	 * Sanitize float value.
	 *
	 * @since 1.0.0
	 * @param mixed $value Value to sanitize.
	 * @return float Float value.
	 */
	public static function float( $value ) {
		return floatval( $value );
	}

	/**
	 * Sanitize slug.
	 *
	 * @since 1.0.0
	 * @param string $value Value to sanitize.
	 * @return string Sanitized slug.
	 */
	public static function slug( $value ) {
		return sanitize_title( $value );
	}

	/**
	 * Sanitize key.
	 *
	 * @since 1.0.0
	 * @param string $value Value to sanitize.
	 * @return string Sanitized key.
	 */
	public static function key( $value ) {
		return sanitize_key( $value );
	}

	/**
	 * Sanitize hex color.
	 *
	 * @since 1.0.0
	 * @param string $color Color value.
	 * @return string Sanitized color.
	 */
	public static function hex_color( $color ) {
		if ( empty( $color ) ) {
			return '';
		}

		// Remove # if present.
		$color = ltrim( $color, '#' );

		// Validate hex color.
		if ( preg_match( '/^[a-fA-F0-9]{6}$/', $color ) ) {
			return '#' . $color;
		}

		// Validate short hex color.
		if ( preg_match( '/^[a-fA-F0-9]{3}$/', $color ) ) {
			return '#' . $color;
		}

		return '';
	}

	/**
	 * Sanitize file name.
	 *
	 * @since 1.0.0
	 * @param string $filename File name.
	 * @return string Sanitized file name.
	 */
	public static function filename( $filename ) {
		return sanitize_file_name( $filename );
	}
}
