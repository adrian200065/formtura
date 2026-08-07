<?php
/**
 * Date Field Template (alias)
 *
 * The palette registers this field as `datetime`, but FormBuilder.jsx still
 * carries a `date` default label, so forms may hold either type.
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

require __DIR__ . '/datetime.php';
