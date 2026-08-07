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
		// Standard Fields
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
		'name' => [
			'label'     => __( 'Name', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'user',
			'category'  => 'standard',
		],
		'email' => [
			'label'     => __( 'Email', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'mail',
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
		'number' => [
			'label'     => __( 'Number', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'hash',
			'category'  => 'standard',
		],
		'phone' => [
			'label'     => __( 'Phone', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'phone',
			'category'  => 'standard',
		],
		'website' => [
			'label'     => __( 'Website / URL', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'globe',
			'category'  => 'standard',
		],
		'html' => [
			'label'     => __( 'HTML', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'code',
			'category'  => 'standard',
		],
		'hidden' => [
			'label'     => __( 'Hidden Field', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'eye-off',
			'category'  => 'standard',
		],
		'captcha' => [
			'label'     => __( 'CAPTCHA', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'lock',
			'category'  => 'standard',
		],
		// Advanced Fields
		'address' => [
			'label'     => __( 'Address', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'map-pin',
			'category'  => 'advanced',
		],
		'datetime' => [
			'label'     => __( 'Date / Time', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'calendar',
			'category'  => 'advanced',
		],
		'password' => [
			'label'     => __( 'Password', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'lock',
			'category'  => 'advanced',
		],
		'file-upload' => [
			'label'     => __( 'File Upload', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'upload',
			'category'  => 'advanced',
		],
		'number-slider' => [
			'label'     => __( 'Slider', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'trending-up',
			'category'  => 'advanced',
		],
		'rating' => [
			'label'     => __( 'Star Rating', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'star',
			'category'  => 'advanced',
		],
		'repeater' => [
			'label'     => __( 'Repeater', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'repeat',
			'category'  => 'advanced',
		],
		'signature' => [
			'label'     => __( 'Signature', FORMTURA_TEXTDOMAIN ),
			'icon'      => 'pen-tool',
			'category'  => 'advanced',
		],
	] );
}

/**
 * Get the submission key for a field.
 *
 * This is the single source of truth for a field's `name` attribute. The React
 * builder identifies fields by `id` only and never assigns a separate `name`,
 * so the id doubles as the submission key. Templates and the submission handler
 * must both go through this helper or submitted values will not line up with
 * the saved field definitions.
 *
 * @since 1.0.3
 * @param array $field Field configuration.
 * @return string Field submission key.
 */
function fta_get_field_name( $field ) {
	if ( ! empty( $field['name'] ) ) {
		return (string) $field['name'];
	}

	return isset( $field['id'] ) ? (string) $field['id'] : '';
}

/**
 * Get the DOM id used for a field's primary input.
 *
 * @since 1.0.3
 * @param array  $field  Field configuration.
 * @param string $suffix Optional suffix for fields rendering multiple inputs.
 * @return string Input DOM id.
 */
function fta_get_field_input_id( $field, $suffix = '' ) {
	$id = 'fta-field-' . fta_get_field_name( $field );

	if ( '' !== $suffix ) {
		$id .= '-' . $suffix;
	}

	return $id;
}

/**
 * Normalize a single choice into a label/value/default triplet.
 *
 * Choices arrive either as objects from the builder's choice editor or as plain
 * strings from the legacy `options` array.
 *
 * @since 1.0.3
 * @param mixed $choice Raw choice.
 * @return array Normalized choice.
 */
function fta_normalize_field_choice( $choice ) {
	if ( is_array( $choice ) ) {
		$label = isset( $choice['label'] ) ? $choice['label'] : '';
		$value = isset( $choice['value'] ) && '' !== $choice['value'] ? $choice['value'] : $label;

		return [
			'label'     => (string) $label,
			'value'     => (string) $value,
			'isDefault' => ! empty( $choice['isDefault'] ),
		];
	}

	return [
		'label'     => (string) $choice,
		'value'     => (string) $choice,
		'isDefault' => false,
	];
}

/**
 * Resolve the choices for a choice-based field.
 *
 * Mirrors the builder preview: manual choices by default, or posts/terms when
 * the field is populated dynamically. Randomization is applied last so it
 * affects dynamic sources too.
 *
 * @since 1.0.3
 * @param array $field Field configuration.
 * @return array List of normalized choices.
 */
function fta_get_field_choices( $field ) {
	$source  = isset( $field['dynamicChoices'] ) ? $field['dynamicChoices'] : '';
	$choices = [];

	if ( 'post_type' === $source && ! empty( $field['dynamicPostType'] ) ) {
		$posts = get_posts( [
			'post_type'      => sanitize_key( $field['dynamicPostType'] ),
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'orderby'        => 'title',
			'order'          => 'ASC',
		] );

		foreach ( $posts as $post ) {
			$choices[] = [
				'label'     => $post->post_title,
				'value'     => (string) $post->ID,
				'isDefault' => false,
			];
		}
	} elseif ( 'taxonomy' === $source && ! empty( $field['dynamicTaxonomy'] ) ) {
		$terms = get_terms( [
			'taxonomy'   => sanitize_key( $field['dynamicTaxonomy'] ),
			'hide_empty' => false,
			'number'     => 100,
		] );

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$choices[] = [
					'label'     => $term->name,
					'value'     => (string) $term->term_id,
					'isDefault' => false,
				];
			}
		}
	} else {
		$raw = [];

		if ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {
			$raw = $field['choices'];
		} elseif ( ! empty( $field['options'] ) && is_array( $field['options'] ) ) {
			$raw = $field['options'];
		}

		$choices = array_map( 'fta_normalize_field_choice', $raw );
	}

	if ( ! empty( $field['randomizeChoices'] ) ) {
		shuffle( $choices );
	}

	return $choices;
}

/**
 * Build the CSS classes for a field wrapper.
 *
 * @since 1.0.3
 * @param array  $field      Field configuration.
 * @param string $base_class Type-specific wrapper class.
 * @return string Space separated class list.
 */
function fta_get_field_wrapper_class( $field, $base_class = '' ) {
	$classes = [ 'fta-field' ];

	if ( $base_class ) {
		$classes[] = $base_class;
	}

	if ( ! empty( $field['fieldSize'] ) ) {
		$classes[] = 'fta-field-size-' . sanitize_html_class( $field['fieldSize'] );
	}

	if ( ! empty( $field['choiceLayout'] ) ) {
		$classes[] = 'fta-choices-' . sanitize_html_class( $field['choiceLayout'] );
	}

	if ( ! empty( $field['cssClasses'] ) ) {
		foreach ( preg_split( '/\s+/', $field['cssClasses'] ) as $custom_class ) {
			if ( '' !== $custom_class ) {
				$classes[] = sanitize_html_class( $custom_class );
			}
		}
	}

	return implode( ' ', array_unique( $classes ) );
}

/**
 * Build the wrapper data attributes for a field.
 *
 * Emits the `data-conditional-logic` payload that assets/js/frontend.js reads
 * when deciding whether to show or hide a field.
 *
 * @since 1.0.3
 * @param array $field Field configuration.
 * @return string Escaped attribute string, ready to echo inside a tag.
 */
function fta_get_field_wrapper_data( $field ) {
	$attributes = sprintf( ' data-field-id="%s"', esc_attr( fta_get_field_name( $field ) ) );

	// The builder writes `conditionalLogic`; older forms used `conditional_logic`.
	$logic = null;

	if ( ! empty( $field['conditionalLogic'] ) && is_array( $field['conditionalLogic'] ) ) {
		$logic = $field['conditionalLogic'];
	} elseif ( ! empty( $field['conditional_logic'] ) && is_array( $field['conditional_logic'] ) ) {
		$logic = $field['conditional_logic'];
	}

	if ( $logic && ! empty( $logic['enabled'] ) ) {
		$attributes .= sprintf(
			' data-conditional-logic="%s"',
			esc_attr( wp_json_encode( $logic ) )
		);
	}

	return $attributes;
}

/**
 * Render the label for a field.
 *
 * @since 1.0.3
 * @param array  $field  Field configuration.
 * @param string $for_id Optional id of the control the label points at.
 */
function fta_field_label( $field, $for_id = '' ) {
	if ( ! empty( $field['hideLabel'] ) || empty( $field['label'] ) ) {
		return;
	}

	$for_id   = '' !== $for_id ? $for_id : fta_get_field_input_id( $field );
	$required = ! empty( $field['required'] ) ? ' required' : '';

	printf(
		'<label for="%1$s" class="fta-field-label%2$s">%3$s</label>',
		esc_attr( $for_id ),
		esc_attr( $required ),
		esc_html( $field['label'] )
	);
}

/**
 * Render the description for a field.
 *
 * @since 1.0.3
 * @param array $field Field configuration.
 */
function fta_field_description( $field ) {
	if ( empty( $field['description'] ) ) {
		return;
	}

	printf(
		'<span class="fta-field-description">%s</span>',
		esc_html( $field['description'] )
	);
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
 * @return bool True when a template was located and included.
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

		return true;
	}

	return false;
}

/**
 * Get a cache-busting version for a local plugin asset.
 *
 * Uses the file modification time when the asset exists so watched development
 * builds are immediately visible. Falls back to the plugin version for packaged
 * installs where the requested file is unavailable.
 *
 * @since 1.0.2
 * @param string $relative_path Plugin-relative asset path.
 * @return string Asset version.
 */
function fta_asset_version( $relative_path ) {
	$asset_path = FORMTURA_PLUGIN_DIR . ltrim( $relative_path, '/' );

	return file_exists( $asset_path )
		? (string) filemtime( $asset_path )
		: FORMTURA_VERSION;
}
