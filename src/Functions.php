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
function fta_get_forms( $args = array() ) {
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
function fta_get_entries( $form_id, $args = array() ) {
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
 * Update an entry.
 *
 * @since 1.0.6
 * @param int   $entry_id Entry ID.
 * @param array $data Fields to change. Supports `is_read` and `data`.
 * @return bool True on success, false on failure.
 */
function fta_update_entry( $entry_id, $data ) {
	$entries_db = new \Formtura\Database\Entries_DB();
	return $entries_db->update( $entry_id, $data );
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
	$settings = get_option( 'fta_settings', array() );

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
	$settings         = get_option( 'fta_settings', array() );
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
	$settings = get_option( 'fta_smtp_settings', array() );

	if ( empty( $key ) ) {
		return $settings;
	}

	return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
}

/**
 * Get the reCAPTCHA configuration.
 *
 * Single source of truth for whether reCAPTCHA is active. Both halves of the
 * flow have to agree: without a site key the browser cannot mint a token, and
 * without a secret key the server cannot verify one. Enabling on either key
 * alone leaves the form unsubmittable, so both are required.
 *
 * @since 1.0.4
 * @return array {
 *     @type bool   $enabled         Whether reCAPTCHA should be applied.
 *     @type string $site_key        Public site key.
 *     @type string $secret_key      Private secret key.
 *     @type string $version         Either 'v2' or 'v3'.
 *     @type string $action          Action name submitted with v3 tokens.
 *     @type float  $score_threshold Minimum accepted v3 score, 0.0-1.0.
 * }
 */
function fta_get_recaptcha_config() {
	$site_key   = trim( (string) fta_get_setting( 'recaptcha_site_key', '' ) );
	$secret_key = trim( (string) \Formtura\Admin\Secret_Crypto::decrypt( fta_get_setting( 'recaptcha_secret_key', '' ) ) );
	$version    = 'v3' === fta_get_setting( 'recaptcha_version', 'v2' ) ? 'v3' : 'v2';

	$threshold = fta_get_setting( 'recaptcha_score_threshold', 0.5 );
	$threshold = is_numeric( $threshold ) ? (float) $threshold : 0.5;
	$threshold = max( 0.0, min( 1.0, $threshold ) );

	return array(
		'enabled'         => '' !== $site_key && '' !== $secret_key,
		'site_key'        => $site_key,
		'secret_key'      => $secret_key,
		'version'         => $version,
		'action'          => 'formtura_submit',
		'score_threshold' => $threshold,
	);
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
 * @param mixed $value Field value.
 * @param array $rules Validation rules.
 * @return bool|string True if valid, error message if invalid.
 */
function fta_validate_field( $value, $rules ) {
	// Required validation.
	if ( ! empty( $rules['required'] ) && empty( $value ) ) {
		return __( 'This field is required.', 'formtura' );
	}

	// Email validation.
	if ( ! empty( $rules['email'] ) && ! is_email( $value ) ) {
		return __( 'Please enter a valid email address.', 'formtura' );
	}

	// URL validation.
	if ( ! empty( $rules['url'] ) && ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
		return __( 'Please enter a valid URL.', 'formtura' );
	}

	// Min length validation.
	if ( isset( $rules['min_length'] ) && strlen( $value ) < $rules['min_length'] ) {
		return sprintf(
			/* translators: %d: minimum number of characters. */
			__( 'Minimum %d characters required.', 'formtura' ),
			$rules['min_length']
		);
	}

	// Max length validation.
	if ( isset( $rules['max_length'] ) && strlen( $value ) > $rules['max_length'] ) {
		return sprintf(
			/* translators: %d: maximum number of characters. */
			__( 'Maximum %d characters allowed.', 'formtura' ),
			$rules['max_length']
		);
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
function fta_render_form( $form_id, $args = array() ) {
	$form = fta_get_form( $form_id );

	if ( ! $form ) {
		return '<p>' . __( 'Form not found.', 'formtura' ) . '</p>';
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
	return apply_filters(
		'fta_field_types',
		array(
			// Standard Fields.
			'text'          => array(
				'label'    => __( 'Single Line Text', 'formtura' ),
				'icon'     => 'text',
				'category' => 'standard',
			),
			'textarea'      => array(
				'label'    => __( 'Paragraph Text', 'formtura' ),
				'icon'     => 'align-left',
				'category' => 'standard',
			),
			'name'          => array(
				'label'    => __( 'Name', 'formtura' ),
				'icon'     => 'user',
				'category' => 'standard',
			),
			'email'         => array(
				'label'    => __( 'Email', 'formtura' ),
				'icon'     => 'mail',
				'category' => 'standard',
			),
			'select'        => array(
				'label'    => __( 'Dropdown', 'formtura' ),
				'icon'     => 'chevron-down',
				'category' => 'standard',
			),
			'radio'         => array(
				'label'    => __( 'Multiple Choice', 'formtura' ),
				'icon'     => 'circle',
				'category' => 'standard',
			),
			'checkbox'      => array(
				'label'    => __( 'Checkboxes', 'formtura' ),
				'icon'     => 'check-square',
				'category' => 'standard',
			),
			'number'        => array(
				'label'    => __( 'Number', 'formtura' ),
				'icon'     => 'hash',
				'category' => 'standard',
			),
			'phone'         => array(
				'label'    => __( 'Phone', 'formtura' ),
				'icon'     => 'phone',
				'category' => 'standard',
			),
			'website'       => array(
				'label'    => __( 'Website / URL', 'formtura' ),
				'icon'     => 'globe',
				'category' => 'standard',
			),
			'html'          => array(
				'label'    => __( 'HTML', 'formtura' ),
				'icon'     => 'code',
				'category' => 'standard',
			),
			'hidden'        => array(
				'label'    => __( 'Hidden Field', 'formtura' ),
				'icon'     => 'eye-off',
				'category' => 'standard',
			),
			'captcha'       => array(
				'label'    => __( 'CAPTCHA', 'formtura' ),
				'icon'     => 'lock',
				'category' => 'standard',
			),
			// Advanced Fields.
			'address'       => array(
				'label'    => __( 'Address', 'formtura' ),
				'icon'     => 'map-pin',
				'category' => 'advanced',
			),
			'datetime'      => array(
				'label'    => __( 'Date / Time', 'formtura' ),
				'icon'     => 'calendar',
				'category' => 'advanced',
			),
			'password'      => array(
				'label'    => __( 'Password', 'formtura' ),
				'icon'     => 'lock',
				'category' => 'advanced',
			),
			'file-upload'   => array(
				'label'    => __( 'File Upload', 'formtura' ),
				'icon'     => 'upload',
				'category' => 'advanced',
			),
			'number-slider' => array(
				'label'    => __( 'Slider', 'formtura' ),
				'icon'     => 'trending-up',
				'category' => 'advanced',
			),
			'rating'        => array(
				'label'    => __( 'Star Rating', 'formtura' ),
				'icon'     => 'star',
				'category' => 'advanced',
			),
			'repeater'      => array(
				'label'    => __( 'Repeater', 'formtura' ),
				'icon'     => 'repeat',
				'category' => 'advanced',
			),
			'signature'     => array(
				'label'    => __( 'Signature', 'formtura' ),
				'icon'     => 'pen-tool',
				'category' => 'advanced',
			),
		)
	);
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

		return array(
			'label'     => (string) $label,
			'value'     => (string) $value,
			'isDefault' => ! empty( $choice['isDefault'] ),
		);
	}

	return array(
		'label'     => (string) $choice,
		'value'     => (string) $choice,
		'isDefault' => false,
	);
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
	$choices = array();

	if ( 'post_type' === $source && ! empty( $field['dynamicPostType'] ) ) {
		$posts = get_posts(
			array(
				'post_type'      => sanitize_key( $field['dynamicPostType'] ),
				'posts_per_page' => 100,
				'post_status'    => 'publish',
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		foreach ( $posts as $post ) {
			$choices[] = array(
				'label'     => $post->post_title,
				'value'     => (string) $post->ID,
				'isDefault' => false,
			);
		}
	} elseif ( 'taxonomy' === $source && ! empty( $field['dynamicTaxonomy'] ) ) {
		$terms = get_terms(
			array(
				'taxonomy'   => sanitize_key( $field['dynamicTaxonomy'] ),
				'hide_empty' => false,
				'number'     => 100,
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$choices[] = array(
					'label'     => $term->name,
					'value'     => (string) $term->term_id,
					'isDefault' => false,
				);
			}
		}
	} else {
		$raw = array();

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
 * Get the symbol for the configured currency.
 *
 * @since 1.0.4
 * @return string Currency symbol, or the currency code when no symbol is known.
 */
function fta_get_currency_symbol() {
	$currency = (string) fta_get_setting( 'currency', 'USD' );

	$symbols = apply_filters(
		'fta_currency_symbols',
		array(
			'USD' => '$',
			'EUR' => '€',
			'GBP' => '£',
			'AUD' => '$',
			'CAD' => '$',
			'JPY' => '¥',
		)
	);

	return isset( $symbols[ $currency ] ) ? $symbols[ $currency ] : $currency;
}

/**
 * Format an amount in the configured currency.
 *
 * Display formatting only - payment math happens on unformatted floats.
 *
 * @since 1.0.4
 * @param float|int|string $amount Amount to format.
 * @return string Formatted price, e.g. "$10.00".
 */
function fta_format_price( $amount ) {
	return fta_get_currency_symbol() . number_format( (float) $amount, 2 );
}

/**
 * Get a payment field's items in a predictable shape.
 *
 * Items are the payment counterpart of choices: the builder saves
 * { label, value, price, isDefault } and this helper guarantees that shape
 * whatever is stored. Prices in the definition are the only prices the
 * server trusts.
 *
 * @since 1.0.4
 * @param array $field Field configuration.
 * @return array[] Normalized items.
 */
function fta_get_field_items( $field ) {
	$items      = isset( $field['items'] ) && is_array( $field['items'] ) ? $field['items'] : array();
	$normalized = array();

	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}

		$label = isset( $item['label'] ) ? (string) $item['label'] : '';
		$value = isset( $item['value'] ) && '' !== (string) $item['value'] ? (string) $item['value'] : $label;

		if ( '' === $value ) {
			continue;
		}

		$normalized[] = array(
			'label'     => $label,
			'value'     => $value,
			'price'     => isset( $item['price'] ) && is_numeric( $item['price'] ) ? (float) $item['price'] : 0.0,
			'isDefault' => ! empty( $item['isDefault'] ),
		);
	}

	return $normalized;
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
	$classes = array( 'fta-field' );

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
function fta_get_template_part( $slug, $name = '', $args = array() ) {
	$templates = array();
	$name      = (string) $name;

	if ( '' !== $name ) {
		$templates[] = "{$slug}-{$name}.php";
	}

	$templates[] = "{$slug}.php";

	// Allow themes to override templates.
	$located = locate_template(
		array_map(
			function ( $template ) {
				return 'formtura/' . $template;
			},
			$templates
		),
		false,
		false
	);

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
		// Every template under templates/ expects a single $field variable;
		// see the @var docblocks in templates/fields/*.php.
		$field = isset( $args['field'] ) ? $args['field'] : null;
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
