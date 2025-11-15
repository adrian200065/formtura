<?php
/**
 * Global Helper Functions
 *
 * Collection of global utility functions for the plugin.
 *
 * @package Formtura
 * @since 1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Get a form by ID.
 *
 * @since 1.0.0
 * @param int $form_id Form ID.
 * @return array|null Form data or null if not found.
 */
function fta_get_form( $form_id ) {
	$forms_db = new \Formtura\Database\Forms_DB();
	return $forms_db->get( $form_id );
}

/**
 * Get all forms.
 *
 * @since 1.0.0
 * @param array $args Query arguments.
 * @return array Array of forms.
 */
function fta_get_forms( $args = [] ) {
	$forms_db = new \Formtura\Database\Forms_DB();
	return $forms_db->get_all( $args );
}

/**
 * Create a new form.
 *
 * @since 1.0.0
 * @param array $data Form data.
 * @return int|false Form ID on success, false on failure.
 */
function fta_create_form( $data ) {
	$forms_db = new \Formtura\Database\Forms_DB();
	return $forms_db->create( $data );
}

/**
 * Update a form.
 *
 * @since 1.0.0
 * @param int   $form_id Form ID.
 * @param array $data Form data.
 * @return bool True on success, false on failure.
 */
function fta_update_form( $form_id, $data ) {
	$forms_db = new \Formtura\Database\Forms_DB();
	return $forms_db->update( $form_id, $data );
}

/**
 * Delete a form.
 *
 * @since 1.0.0
 * @param int $form_id Form ID.
 * @return bool True on success, false on failure.
 */
function fta_delete_form( $form_id ) {
	$forms_db = new \Formtura\Database\Forms_DB();
	return $forms_db->delete( $form_id );
}

/**
 * Get an entry by ID.
 *
 * @since 1.0.0
 * @param int $entry_id Entry ID.
 * @return array|null Entry data or null if not found.
 */
function fta_get_entry( $entry_id ) {
	$entries_db = new \Formtura\Database\Entries_DB();
	return $entries_db->get( $entry_id );
}

/**
 * Get entries for a form.
 *
 * @since 1.0.0
 * @param int   $form_id Form ID.
 * @param array $args Query arguments.
 * @return array Array of entries.
 */
function fta_get_entries( $form_id, $args = [] ) {
	$entries_db = new \Formtura\Database\Entries_DB();
	return $entries_db->get_by_form( $form_id, $args );
}

/**
 * Create a new entry.
 *
 * @since 1.0.0
 * @param array $data Entry data.
 * @return int|false Entry ID on success, false on failure.
 */
function fta_create_entry( $data ) {
	$entries_db = new \Formtura\Database\Entries_DB();
	return $entries_db->create( $data );
}

/**
 * Delete an entry.
 *
 * @since 1.0.0
 * @param int $entry_id Entry ID.
 * @return bool True on success, false on failure.
 */
function fta_delete_entry( $entry_id ) {
	$entries_db = new \Formtura\Database\Entries_DB();
	return $entries_db->delete( $entry_id );
}

/**
 * Get plugin settings.
 *
 * @since 1.0.0
 * @param string $key Optional. Specific setting key to retrieve.
 * @param mixed  $default Default value if setting not found.
 * @return mixed Setting value or all settings.
 */
function fta_get_setting( $key = '', $default = null ) {
	$settings = get_option( 'fta_settings', [] );

	if ( empty( $key ) ) {
		return $settings;
	}

	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Update plugin settings.
 *
 * @since 1.0.0
 * @param string $key Setting key.
 * @param mixed  $value Setting value.
 * @return bool True on success, false on failure.
 */
function fta_update_setting( $key, $value ) {
	$settings = get_option( 'fta_settings', [] );
	$settings[ $key ] = $value;
	return update_option( 'fta_settings', $settings );
}

/**
 * Get SMTP settings.
 *
 * @since 1.0.0
 * @param string $key Optional. Specific setting key to retrieve.
 * @param mixed  $default Default value if setting not found.
 * @return mixed Setting value or all settings.
 */
function fta_get_smtp_setting( $key = '', $default = null ) {
	$settings = get_option( 'fta_smtp_settings', [] );

	if ( empty( $key ) ) {
		return $settings;
	}

	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Sanitize form field data.
 *
 * @since 1.0.0
 * @param mixed  $value Field value.
 * @param string $type Field type.
 * @return mixed Sanitized value.
 */
function fta_sanitize_field( $value, $type = 'text' ) {
	return \Formtura\Utils\Sanitize::field( $value, $type );
}

/**
 * Validate form field data.
 *
 * @since 1.0.0
 * @param mixed  $value Field value.
 * @param array  $rules Validation rules.
 * @return bool|string True if valid, error message if invalid.
 */
function fta_validate_field( $value, $rules ) {
	// Required validation.
	if ( ! empty( $rules['required'] ) && empty( $value ) ) {
		return __( 'This field is required.', FORMTURA_TEXTDOMAIN );
	}

	// Email validation.
	if ( ! empty( $rules['email'] ) && ! is_email( $value ) ) {
		return __( 'Please enter a valid email address.', FORMTURA_TEXTDOMAIN );
	}

	// URL validation.
	if ( ! empty( $rules['url'] ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
		return __( 'Please enter a valid URL.', FORMTURA_TEXTDOMAIN );
	}

	// Min length validation.
	if ( isset( $rules['min_length'] ) && strlen( $value ) < $rules['min_length'] ) {
		return sprintf( __( 'Minimum %d characters required.', FORMTURA_TEXTDOMAIN ), $rules['min_length'] );
	}

	// Max length validation.
	if ( isset( $rules['max_length'] ) && strlen( $value ) > $rules['max_length'] ) {
		return sprintf( __( 'Maximum %d characters allowed.', FORMTURA_TEXTDOMAIN ), $rules['max_length'] );
	}

	return true;
}

/**
 * Render a form.
 *
 * @since 1.0.0
 * @param int   $form_id Form ID.
 * @param array $args Optional arguments.
 * @return string Form HTML.
 */
function fta_render_form( $form_id, $args = [] ) {
	$form = fta_get_form( $form_id );

	if ( ! $form ) {
		return '<p>' . __( 'Form not found.', FORMTURA_TEXTDOMAIN ) . '</p>';
	}

	ob_start();
	include FORMTURA_PLUGIN_DIR . 'templates/form-wrapper.php';
	return ob_get_clean();
}

/**
 * Get form field types.
 *
 * @since 1.0.0
 * @return array Array of field types.
 */
function fta_get_field_types() {
	return apply_filters( 'fta_field_types', [
		'text' => [
			'label'     => __( 'Single Line Text', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'text',
			'category'  => 'standard',
		],
		'textarea' => [
			'label'     => __( 'Paragraph Text', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'align-left',
			'category'  => 'standard',
		],
		'email' => [
			'label'     => __( 'Email', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'mail',
			'category'  => 'standard',
		],
		'number' => [
			'label'     => __( 'Number', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'hash',
			'category'  => 'standard',
		],
		'select' => [
			'label'     => __( 'Dropdown', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'chevron-down',
			'category'  => 'standard',
		],
		'radio' => [
			'label'     => __( 'Multiple Choice', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'circle',
			'category'  => 'standard',
		],
		'checkbox' => [
			'label'     => __( 'Checkboxes', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'check-square',
			'category'  => 'standard',
		],
		'name' => [
			'label'     => __( 'Name', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'user',
			'category'  => 'standard',
		],
	] );
}

/**
 * Log debug message.
 *
 * @since 1.0.0
 * @param mixed  $message Message to log.
 * @param string $level Log level (info, warning, error).
 */
function fta_log( $message, $level = 'info' ) {
	if ( ! fta_get_setting( 'debug_mode', false ) ) {
		return;
	}

	if ( is_array( $message ) || is_object( $message ) ) {
		$message = print_r( $message, true );
	}

	error_log( sprintf( '[Formtura][%s] %s', strtoupper( $level ), $message ) );
}

/**
 * Get template part.
 *
 * @since 1.0.0
 * @param string $slug Template slug.
 * @param string $name Optional. Template name.
 * @param array  $args Optional. Arguments to pass to template.
 */
function fta_get_template_part( $slug, $name = '', $args = [] ) {
	$templates = [];
	$name = (string) $name;

	if ( '' !== $name ) {
		$templates[] = "{$slug}-{$name}.php";
	}

	$templates[] = "{$slug}.php";

	// Allow themes to override templates.
	$located = locate_template( array_map( function( $template ) {
		return 'formtura/' . $template;
	}, $templates ), false, false );

	// Fall back to plugin templates.
	if ( ! $located ) {
		foreach ( $templates as $template ) {
			if ( file_exists( FORMTURA_PLUGIN_DIR . 'templates/' . $template ) ) {
				$located = FORMTURA_PLUGIN_DIR . 'templates/' . $template;
				break;
			}
		}
	}

	if ( $located ) {
		extract( $args );
		include $located;
	}
}
