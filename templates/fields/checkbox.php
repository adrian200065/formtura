<?php
/**
 * Multiple Choice Field Template (alias)
 *
 * The builder registers its single-answer choice field under the type
 * `checkbox` and labels it "Multiple Choice", while `checkboxes` is the
 * multi-answer field. FieldPreview.jsx renders both `radio` and `checkbox` as
 * radio inputs, so this template delegates to keep the two in step.
 *
 * @package Formtura
 * @since 1.0.3
 *
 * @var array $field Field configuration.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require __DIR__ . '/radio.php';
