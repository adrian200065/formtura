<?php
/**
 * PaymentTotals Class
 *
 * Recomputes the authoritative order amount on submission. Prices come
 * exclusively from the form definition; whatever totals or prices the
 * browser posted are never consulted. No gateway is involved - the
 * result is recorded with the entry, nothing is charged.
 *
 * @package Formtura
 * @since 1.0.4
 */

namespace Formtura\Frontend;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * PaymentTotals class.
 */
class PaymentTotals {

	/**
	 * Field types that contribute priced items.
	 *
	 * @var string[]
	 */
	const ITEM_TYPES = [ 'payment-single', 'payment-checkbox', 'payment-multiple', 'payment-dropdown' ];

	/**
	 * Whether a form contains any payment fields.
	 *
	 * @since 1.0.4
	 * @param array $form Form data.
	 * @return bool
	 */
	public function form_has_payment_fields( $form ) {
		if ( empty( $form['fields'] ) || ! is_array( $form['fields'] ) ) {
			return false;
		}

		foreach ( $form['fields'] as $field ) {
			if ( isset( $field['type'] ) && in_array( $field['type'], self::ITEM_TYPES, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Compute the order from the form definition and the submission.
	 *
	 * @since 1.0.4
	 * @param array $form       Form data.
	 * @param array $submission Raw submission (typically $_POST).
	 * @return array|\WP_Error Order data, or WP_Error with per-field messages.
	 */
	public function compute( $form, $submission ) {
		$items       = [];
		$errors      = [];
		$coupon      = null;
		$coupon_code = null;

		// A published method: guard the same shape form_has_payment_fields()
		// already guards, rather than trust the caller to have checked it.
		$fields = isset( $form['fields'] ) && is_array( $form['fields'] ) ? $form['fields'] : [];

		foreach ( $fields as $field ) {
			$type       = isset( $field['type'] ) ? $field['type'] : '';
			$field_name = fta_get_field_name( $field );

			if ( '' === $field_name ) {
				continue;
			}

			if ( 'payment-single' === $type ) {
				$items[] = [
					'label' => isset( $field['label'] ) ? (string) $field['label'] : '',
					'price' => isset( $field['price'] ) && is_numeric( $field['price'] ) ? (float) $field['price'] : 0.0,
				];

				continue;
			}

			if ( in_array( $type, [ 'payment-checkbox', 'payment-multiple', 'payment-dropdown' ], true ) ) {
				$submitted = isset( $submission[ $field_name ] ) ? $submission[ $field_name ] : [];
				$submitted = is_array( $submitted ) ? $submitted : [ $submitted ];

				// Single-select types have exactly one real selection. An
				// array with more than one element can only come from a
				// crafted request, and summing every element would record a
				// total the visitor never saw - take the first and ignore
				// the rest.
				if ( in_array( $type, [ 'payment-multiple', 'payment-dropdown' ], true ) ) {
					$submitted = array_slice( $submitted, 0, 1 );
				}

				$defined = fta_get_field_items( $field );
				$seen    = [];

				foreach ( $submitted as $value ) {
					// A nested array (e.g. a crafted field_items[][]=x
					// request) is not a valid value shape. Casting it with
					// (string) trips an "Array to string conversion"
					// warning; treat it the same as any value the
					// definition doesn't recognise instead, without the
					// cast.
					if ( is_array( $value ) ) {
						$errors[ $field_name ] = __( 'Invalid selection.', 'formtura' );
						break;
					}

					$value = trim( (string) $value );

					// A repeated value (field_items[]=small twice) is only
					// counted once.
					if ( '' === $value || isset( $seen[ $value ] ) ) {
						continue;
					}

					$seen[ $value ] = true;

					$match = null;

					foreach ( $defined as $item ) {
						if ( $item['value'] === $value ) {
							$match = $item;
							break;
						}
					}

					// A value outside the definition is a forged request,
					// not a pricing decision.
					if ( null === $match ) {
						$errors[ $field_name ] = __( 'Invalid selection.', 'formtura' );
						break;
					}

					$items[] = [
						'label' => $match['label'],
						'price' => $match['price'],
					];
				}

				continue;
			}

			if ( 'coupon' === $type ) {
				$raw_code = isset( $submission[ $field_name ] ) ? $submission[ $field_name ] : '';

				// As with item values above: a nested array reaching
				// trim( (string) $value ) trips a warning rather than
				// producing the field error this shape should get.
				if ( is_array( $raw_code ) ) {
					$errors[ $field_name ] = __( 'This coupon code is not valid.', 'formtura' );
					continue;
				}

				$code = trim( (string) $raw_code );

				if ( '' === $code ) {
					continue;
				}

				$found = self::find_coupon( $field, $code );

				if ( null === $found ) {
					$errors[ $field_name ] = __( 'This coupon code is not valid.', 'formtura' );
					continue;
				}

				$coupon      = $found;
				$coupon_code = $found['code'];
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'payment_invalid',
				__( 'Please correct the errors below.', 'formtura' ),
				$errors
			);
		}

		$amount = 0.0;

		foreach ( $items as $item ) {
			$amount += $item['price'];
		}

		if ( null !== $coupon ) {
			$amount -= 'percent' === $coupon['type'] ? $amount * $coupon['value'] / 100 : $coupon['value'];
		}

		return [
			'amount'   => round( max( 0.0, $amount ), 2 ),
			'currency' => (string) fta_get_setting( 'currency', 'USD' ),
			'items'    => $items,
			'coupon'   => $coupon_code,
		];
	}

	/**
	 * Look a code up in a coupon field's definition.
	 *
	 * Case-insensitive. Returns the stored casing so the entry records the
	 * code as the author wrote it.
	 *
	 * @since 1.0.4
	 * @param array  $field Coupon field configuration.
	 * @param string $code  Code as entered by the visitor.
	 * @return array|null [ 'code', 'type', 'value' ] or null when unknown.
	 */
	public static function find_coupon( $field, $code ) {
		$coupons = isset( $field['coupons'] ) && is_array( $field['coupons'] ) ? $field['coupons'] : [];

		// A published method that Task 11's AJAX endpoint calls directly
		// with a raw $_POST value - which could be an array. No code in the
		// definition can ever equal that shape, so it simply never matches.
		if ( is_array( $code ) ) {
			return null;
		}

		$code = trim( (string) $code );

		foreach ( $coupons as $coupon ) {
			if ( ! is_array( $coupon ) || ! isset( $coupon['code'] ) ) {
				continue;
			}

			if ( 0 !== strcasecmp( trim( (string) $coupon['code'] ), $code ) ) {
				continue;
			}

			$type  = isset( $coupon['type'] ) && 'percent' === $coupon['type'] ? 'percent' : 'fixed';
			$value = isset( $coupon['value'] ) && is_numeric( $coupon['value'] ) ? (float) $coupon['value'] : 0.0;

			// A negative value would act as a surcharge (amount -= -50
			// increases the total) and a percent above 100 would multiply
			// the amount up - neither is a discount, so clamp both here
			// rather than rely on the zero-floor downstream to hide them.
			$value = max( 0.0, $value );

			if ( 'percent' === $type ) {
				$value = min( 100.0, $value );
			}

			return [
				'code'  => (string) $coupon['code'],
				'type'  => $type,
				'value' => $value,
			];
		}

		return null;
	}
}
