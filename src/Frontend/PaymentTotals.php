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

		foreach ( $form['fields'] as $field ) {
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
				$defined   = fta_get_field_items( $field );

				foreach ( $submitted as $value ) {
					$value = trim( (string) $value );

					if ( '' === $value ) {
						continue;
					}

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
						$errors[ $field_name ] = __( 'Invalid selection.', FORMTURA_TEXTDOMAIN );
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
				$code = isset( $submission[ $field_name ] ) ? trim( (string) $submission[ $field_name ] ) : '';

				if ( '' === $code ) {
					continue;
				}

				$found = self::find_coupon( $field, $code );

				if ( null === $found ) {
					$errors[ $field_name ] = __( 'This coupon code is not valid.', FORMTURA_TEXTDOMAIN );
					continue;
				}

				$coupon      = $found;
				$coupon_code = $found['code'];
			}
		}

		if ( ! empty( $errors ) ) {
			return new \WP_Error(
				'payment_invalid',
				__( 'Please correct the errors below.', FORMTURA_TEXTDOMAIN ),
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

		foreach ( $coupons as $coupon ) {
			if ( ! is_array( $coupon ) || ! isset( $coupon['code'] ) ) {
				continue;
			}

			if ( 0 !== strcasecmp( trim( (string) $coupon['code'] ), trim( $code ) ) ) {
				continue;
			}

			return [
				'code'  => (string) $coupon['code'],
				'type'  => isset( $coupon['type'] ) && 'percent' === $coupon['type'] ? 'percent' : 'fixed',
				'value' => isset( $coupon['value'] ) && is_numeric( $coupon['value'] ) ? (float) $coupon['value'] : 0.0,
			];
		}

		return null;
	}
}
