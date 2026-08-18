<?php
/**
 * Entry Values Class
 *
 * Renders stored entry data as text for the admin surfaces.
 *
 * @package Formtura
 * @since 1.0.6
 */

namespace Formtura\Admin;

use Formtura\Frontend\File_Storage;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Entry_Values class.
 *
 * Entry data is not flat. Checkboxes and multi-selects hold a list, address
 * and name fields hold parts keyed by position, file fields hold records, and
 * a payment form holds a server-computed order under a reserved key. The list
 * preview, the detail view and the CSV export all need the same answer to
 * "what does this value say" - keeping it here is what stops each of them
 * reaching for implode() or a string cast and rendering a nested value as the
 * literal word "Array".
 */
class Entry_Values {

	/**
	 * Reserved key holding the server-computed payment order.
	 *
	 * Field names are `field_<timestamp>_<suffix>`, so this cannot collide
	 * with a real answer.
	 */
	const PAYMENT_KEY = '_payment';

	/**
	 * How far to descend into a nested value.
	 *
	 * Entry meta is unserialized from the database, so its depth is bounded by
	 * whatever was stored rather than by anything this code controls. Six
	 * levels comfortably covers every shape the plugin writes (a list of
	 * records, each an associative array) while keeping a pathological value
	 * from exhausting the stack mid-render.
	 */
	const MAX_DEPTH = 6;

	/**
	 * Build a field name => label map from a form definition.
	 *
	 * @since 1.0.6
	 * @param array|null $form Form data, if known.
	 * @return array<string,string> Labels keyed by field name.
	 */
	public static function labels( $form ) {
		$labels = array();

		if ( ! is_array( $form ) || ! isset( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return $labels;
		}

		foreach ( $form['fields'] as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			$name = fta_get_field_name( $field );

			if ( '' === $name ) {
				continue;
			}

			$label = isset( $field['label'] ) ? trim( (string) $field['label'] ) : '';

			$labels[ $name ] = '' !== $label ? $label : $name;
		}

		return $labels;
	}

	/**
	 * The heading to show for one stored key.
	 *
	 * @since 1.0.6
	 * @param string $key    Stored data key.
	 * @param array  $labels Label map from labels().
	 * @return string
	 */
	public static function label( $key, array $labels = array() ) {
		$key = (string) $key;

		if ( self::PAYMENT_KEY === $key ) {
			return __( 'Payment', 'formtura' );
		}

		if ( isset( $labels[ $key ] ) && '' !== $labels[ $key ] ) {
			return $labels[ $key ];
		}

		// A field deleted from the form after entries were collected has no
		// label left to look up, so the raw key is all there is to work with.
		return ucfirst( str_replace( '_', ' ', $key ) );
	}

	/**
	 * Render one stored key's value, honouring reserved keys.
	 *
	 * @since 1.0.6
	 * @param string $key   Stored data key.
	 * @param mixed  $value Stored value.
	 * @return string
	 */
	public static function text_for( $key, $value ) {
		if ( self::PAYMENT_KEY === (string) $key && is_array( $value ) ) {
			return self::payment_text( $value );
		}

		return self::to_text( $value );
	}

	/**
	 * Render a stored value as plain text.
	 *
	 * @since 1.0.6
	 * @param mixed $value Stored value.
	 * @param int   $depth Current recursion depth.
	 * @return string
	 */
	public static function to_text( $value, $depth = 0 ) {
		if ( null === $value ) {
			return '';
		}

		if ( is_bool( $value ) ) {
			return $value ? '1' : '0';
		}

		if ( is_scalar( $value ) ) {
			return (string) $value;
		}

		if ( ! is_array( $value ) ) {
			return '';
		}

		// A record's `path` is a private vault location, never something to
		// render. The visitor-supplied filename is the only part of a record
		// that is meant to be seen.
		if ( File_Storage::is_file_record( $value ) ) {
			return isset( $value['name'] ) ? (string) $value['name'] : '';
		}

		if ( $depth >= self::MAX_DEPTH ) {
			return '';
		}

		$parts = array();

		foreach ( $value as $item ) {
			$text = self::to_text( $item, $depth + 1 );

			if ( '' !== trim( $text ) ) {
				$parts[] = $text;
			}
		}

		return implode( ', ', $parts );
	}

	/**
	 * Render a computed payment order.
	 *
	 * The order's own currency is used rather than the site's current setting:
	 * an entry records what was charged at the time, and re-reading today's
	 * setting would relabel historic amounts after a currency change.
	 *
	 * @since 1.0.6
	 * @param array $payment Stored payment order.
	 * @return string
	 */
	public static function payment_text( array $payment ) {
		$currency = isset( $payment['currency'] ) ? trim( (string) $payment['currency'] ) : '';
		$amount   = number_format( isset( $payment['amount'] ) ? (float) $payment['amount'] : 0.0, 2 );

		$text = trim( $currency . ' ' . $amount );

		$items = array();

		if ( isset( $payment['items'] ) && is_array( $payment['items'] ) ) {
			foreach ( $payment['items'] as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}

				$items[] = sprintf(
					'%s (%s)',
					isset( $item['label'] ) ? (string) $item['label'] : '',
					number_format( isset( $item['price'] ) ? (float) $item['price'] : 0.0, 2 )
				);
			}
		}

		if ( ! empty( $items ) ) {
			$text .= ' - ' . implode( ', ', $items );
		}

		if ( ! empty( $payment['coupon'] ) ) {
			$text .= sprintf(
				' [%s: %s]',
				__( 'coupon', 'formtura' ),
				(string) $payment['coupon']
			);
		}

		return $text;
	}

	/**
	 * The file records held by a stored value, if any.
	 *
	 * Callers that can offer a download - the entry detail view - need the
	 * records themselves rather than their text, so they can build authorized
	 * File_Download links.
	 *
	 * @since 1.0.6
	 * @param mixed $value Stored value.
	 * @return array[] File records, empty when the value holds none.
	 */
	public static function file_records( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		if ( File_Storage::is_file_record( $value ) ) {
			return array( $value );
		}

		$records = array();

		foreach ( $value as $item ) {
			if ( is_array( $item ) && File_Storage::is_file_record( $item ) ) {
				$records[] = $item;
			}
		}

		return $records;
	}
}
