<?php
/**
 * Checkboxes Field Template (legacy alias)
 *
 * Forms saved before 1.0.3 stored the multi-answer choice field as
 * `checkboxes`. Installer::migrate_choice_field_types() rewrites those to
 * `checkbox`, but this alias keeps any form the migration has not reached
 * rendering correctly.
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

require __DIR__ . '/checkbox.php';
