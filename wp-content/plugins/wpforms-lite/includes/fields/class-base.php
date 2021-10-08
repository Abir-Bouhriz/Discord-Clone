<?php
/**
 * Base field template.
 *
 * @since 1.0.0
 */
abstract class WPForms_Field {

	/**
	 * Full name of the field type, eg "Paragraph Text".
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $name;

	/**
	 * Type of the field, eg "textarea".
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $type;

	/**
	 * Font Awesome Icon used for the editor button, eg "fa-list".
	 *
	 * @since 1.0.0
	 *
	 * @var mixed
	 */
	public $icon = false;

	/**
	 * Priority order the field button should show inside the "Add Fields" tab.
	 *
	 * @since 1.0.0
	 *
	 * @var int
	 */
	public $order = 1;

	/**
	 * Field group the field belongs to.
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $group = 'standard';

	/**
	 * Placeholder to hold default value(s) for some field types.
	 *
	 * @since 1.0.0
	 *
	 * @var mixed
	 */
	public $defaults;

	/**
	 * Current form ID in the admin builder.
	 *
	 * @since 1.1.1
	 *
	 * @var int|bool
	 */
	public $form_id;

	/**
	 * Current field ID.
	 *
	 * @since 1.5.6
	 *
	 * @var int
	 */
	public $field_id;

	/**
	 * Current form data.
	 *
	 * @since 1.1.1
	 *
	 * @var array
	 */
	public $form_data;

	/**
	 * Current field data.
	 *
	 * @since 1.5.6
	 *
	 * @var array
	 */
	public $field_data;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param bool $init Pass false to allow to shortcut the whole initialization, if needed.
	 */
	public function __construct( $init = true ) {

		if ( ! $init ) {
			return;
		}

		// The form ID is to be accessed in the builder.
		$this->form_id = isset( $_GET['form_id'] ) ? (int) $_GET['form_id'] : false; // phpcs:ignore WordPress.Security.NonceVerification

		// Add HTML line breaks before all newlines in Entry Preview.
		add_filter( "wpforms_pro_fields_entry_preview_get_field_value_{$this->type}_field_after", 'nl2br', 100 );

		// Bootstrap.
		$this->init();

		// Temporary solution to get an object of the field class.
		add_filter(
			"wpforms_fields_get_field_object_{$this->type}",
			function () {

				return $this;
			}
		);

		// Add fields tab.
		add_filter( 'wpforms_builder_fields_buttons', array( $this, 'field_button' ), 15 );

		// Field options tab.
		add_action( "wpforms_builder_fields_options_{$this->type}", array( $this, 'field_options' ), 10 );

		// Preview fields.
		add_action( "wpforms_builder_fields_previews_{$this->type}", array( $this, 'field_preview' ), 10 );

		// AJAX Add new field.
		add_action( "wp_ajax_wpforms_new_field_{$this->type}", array( $this, 'field_new' ) );

		// Display field input elements on front-end.
		add_action( "wpforms_display_field_{$this->type}", array( $this, 'field_display' ), 10, 3 );

		// Validation on submit.
		add_action( "wpforms_process_validate_{$this->type}", array( $this, 'validate' ), 10, 3 );

		// Format.
		add_action( "wpforms_process_format_{$this->type}", array( $this, 'format' ), 10, 3 );

		// Prefill.
		add_filter( 'wpforms_field_properties', array( $this, 'field_prefill_value_property' ), 10, 3 );

		// Change the choice's value while saving entries.
		add_filter( 'wpforms_process_before_form_data', [ $this, 'field_fill_empty_choices' ] );

		// Change field name for ajax error.
		add_filter( 'wpforms_process_ajax_error_field_name', [ $this, 'ajax_error_field_name' ], 10, 4 );
	}

	/**
	 * All systems go. Used by subclasses. Required.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Converted to abstract method, as it's required for all fields.
	 */
	abstract public function init();

	/**
	 * Prefill field value with either fallback or dynamic data.
	 * This needs to be public (although internal) to be used in WordPress hooks.
	 *
	 * @since 1.5.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 * @param array $form_data  Prepared form data/settings.
	 *
	 * @return array Modified field properties.
	 */
	public function field_prefill_value_property( $properties, $field, $form_data ) {

		// Process only for current field.
		if ( $this->type !== $field['type'] ) {
			return $properties;
		}

		// Set the form data, so we can reuse it later, even on front-end.
		$this->form_data = $form_data;

		// Dynamic data.
		if ( ! empty( $this->form_data['settings']['dynamic_population'] ) ) {
			$properties = $this->field_prefill_value_property_dynamic( $properties, $field );
		}

		// Fallback data, rewrites dynamic because user-submitted data is more important.
		$properties = $this->field_prefill_value_property_fallback( $properties, $field );

		return $properties;
	}

	/**
	 * As we are processing user submitted data - ignore all admin-defined defaults.
	 * Preprocess choices-related fields only.
	 *
	 * @since 1.5.0
	 *
	 * @param array $field      Field data and settings.
	 * @param array $properties Properties we are modifying.
	 */
	public function field_prefill_remove_choices_defaults( $field, &$properties ) {

		// Skip this step on admin page.
		if ( is_admin() && ! wpforms_is_admin_page( 'entries', 'edit' ) ) {
			return;
		}
		if (
			! empty( $field['dynamic_choices'] ) ||
			! empty( $field['choices'] )
		) {
			array_walk_recursive(
				$properties['inputs'],
				function ( &$value, $key ) {

					if ( 'default' === $key ) {
						$value = false;
					}
					if ( 'wpforms-selected' === $value ) {
						$value = '';
					}
				}
			);
		}
	}

	/**
	 * Whether current field can be populated dynamically.
	 *
	 * @since 1.5.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return bool
	 */
	public function is_dynamic_population_allowed( $properties, $field ) {

		$allowed = true;

		// Allow population on front-end only.
		if ( is_admin() ) {
			$allowed = false;
		}

		// For dynamic population we require $_GET.
		if ( empty( $_GET ) ) { // phpcs:ignore
			$allowed = false;
		}

		return apply_filters( 'wpforms_field_is_dynamic_population_allowed', $allowed, $properties, $field );
	}

	/**
	 * Prefill the field value with a dynamic value, that we get from $_GET.
	 * The pattern is: wpf4_12_primary, where:
	 *      4 - form_id,
	 *      12 - field_id,
	 *      first - input key.
	 * As 'primary' is our default input key, "wpf4_12_primary" and "wpf4_12" are the same.
	 *
	 * @since 1.5.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return array Modified field properties.
	 */
	protected function field_prefill_value_property_dynamic( $properties, $field ) {

		if ( ! $this->is_dynamic_population_allowed( $properties, $field ) ) {
			return $properties;
		}

		// Iterate over each GET key, parse, and scrap data from there.
		foreach ( $_GET as $key => $raw_value ) { // phpcs:ignore
			preg_match( '/wpf(\d+)_(\d+)(.*)/i', $key, $matches );

			if ( empty( $matches ) || ! is_array( $matches ) ) {
				continue;
			}

			// Required.
			$form_id  = absint( $matches[1] );
			$field_id = absint( $matches[2] );
			$input    = 'primary';

			// Optional.
			if ( ! empty( $matches[3] ) ) {
				$input = sanitize_key( trim( $matches[3], '_' ) );
			}

			// Both form and field IDs should be the same as current form/field.
			if (
				(int) $this->form_data['id'] !== $form_id ||
				(int) $field['id'] !== $field_id
			) {
				// Go to the next GET param.
				continue;
			}

			if ( ! empty( $raw_value ) ) {
				$this->field_prefill_remove_choices_defaults( $field, $properties );
			}

			/*
			 * Some fields (like checkboxes) support multiple selection.
			 * We do not support nested values, so omit them.
			 * Example: ?wpf771_19_wpforms[fields][19][address1]=test
			 * In this case:
			 *      $input = wpforms
			 *      $raw_value = [fields=>[]]
			 *      $single_value = [19=>[]]
			 * There is no reliable way to clean those things out.
			 * So we will ignore the value altogether if it's an array.
			 * We support only single value numeric arrays, like these:
			 *      ?wpf771_19[]=test1&wpf771_19[]=test2
			 *      ?wpf771_19_value[]=test1&wpf771_19_value[]=test2
			 *      ?wpf771_41_r3_c2[]=1&wpf771_41_r1_c4[]=1
			 */
			if ( is_array( $raw_value ) ) {
				foreach ( $raw_value as $single_value ) {
					$properties = $this->get_field_populated_single_property_value( $single_value, $input, $properties, $field );
				}
			} else {
				$properties = $this->get_field_populated_single_property_value( $raw_value, $input, $properties, $field );
			}
		}

		return $properties;
	}

	/**
	 * Public version of get_field_populated_single_property_value() to use by external classes.
	 *
	 * @since 1.6.0.1
	 *
	 * @param string $raw_value  Value from a GET param, always a string.
	 * @param string $input      Represent a subfield inside the field. May be empty.
	 * @param array  $properties Field properties.
	 * @param array  $field      Current field specific data.
	 *
	 * @return array Modified field properties.
	 */
	public function get_field_populated_single_property_value_public( $raw_value, $input, $properties, $field ) {

		return $this->get_field_populated_single_property_value( $raw_value, $input, $properties, $field );
	}

	/**
	 * Get the value, that is used to prefill via dynamic or fallback population.
	 * Based on field data and current properties.
	 *
	 * @since 1.5.0
	 *
	 * @param string $raw_value  Value from a GET param, always a string.
	 * @param string $input      Represent a subfield inside the field. May be empty.
	 * @param array  $properties Field properties.
	 * @param array  $field      Current field specific data.
	 *
	 * @return array Modified field properties.
	 */
	protected function get_field_populated_single_property_value( $raw_value, $input, $properties, $field ) {

		if ( ! is_string( $raw_value ) ) {
			return $properties;
		}

		$get_value = stripslashes( sanitize_text_field( $raw_value ) );

		// For fields that have dynamic choices we need to add extra logic.
		if ( ! empty( $field['dynamic_choices'] ) ) {

			$properties = $this->get_field_populated_single_property_value_dynamic_choices( $get_value, $properties );

		} elseif ( ! empty( $field['choices'] ) && is_array( $field['choices'] ) ) {

			$properties = $this->get_field_populated_single_property_value_normal_choices( $get_value, $properties, $field );

		} else {
			/*
			 * For other types of fields we need to check that
			 * the key is registered for the defined field in inputs array.
			 */
			if (
				! empty( $input ) &&
				isset( $properties['inputs'][ $input ] )
			) {
				$properties['inputs'][ $input ]['attr']['value'] = $get_value;
			}
		}

		return $properties;
	}

	/**
	 * Get the value, that is used to prefill via dynamic or fallback population.
	 * Based on field data and current properties.
	 * Dynamic choices section.
	 *
	 * @since 1.6.0
	 *
	 * @param string $get_value  Value from a GET param, always a string, sanitized, stripped slashes.
	 * @param array  $properties Field properties.
	 *
	 * @return array Modified field properties.
	 */
	protected function get_field_populated_single_property_value_dynamic_choices( $get_value, $properties ) {

		$default_key = null;

		foreach ( $properties['inputs'] as $input_key => $input_arr ) {
			// Dynamic choices support only integers in its values.
			if ( absint( $get_value ) === $input_arr['attr']['value'] ) {
				$default_key = $input_key;
				// Stop iterating over choices.
				break;
			}
		}

		// Redefine default choice only if dynamic value has changed anything.
		if ( null !== $default_key ) {
			foreach ( $properties['inputs'] as $input_key => $choice_arr ) {
				if ( $input_key === $default_key ) {
					$properties['inputs'][ $input_key ]['default']              = true;
					$properties['inputs'][ $input_key ]['container']['class'][] = 'wpforms-selected';
					// Stop iterating over choices.
					break;
				}
			}
		}

		return $properties;
	}

	/**
	 * Fill choices without labels.
	 *
	 * @since 1.6.2
	 *
	 * @param array $form_data Form data.
	 *
	 * @return array
	 */
	public function field_fill_empty_choices( $form_data ) {

		if ( empty( $form_data['fields'] ) ) {
			return $form_data;
		}

		// Set value for choices with the image only. Conditional logic doesn't work without value.
		foreach ( $form_data['fields'] as $field_key => $field ) {
			// Payment fields have their labels set up upfront.
			if ( empty( $field['choices'] ) || ! in_array( $field['type'], [ 'radio', 'checkbox' ], true ) ) {
				continue;
			}

			foreach ( $field['choices'] as $choice_id => $choice ) {
				if ( ( isset( $choice['value'] ) && '' !== trim( $choice['value'] ) ) || empty( $choice['image'] ) ) {
					continue;
				}
				/* translators: %d - choice number. */
				$form_data['fields'][ $field_key ]['choices'][ $choice_id ]['value'] = sprintf( esc_html__( 'Choice %d', 'wpforms-lite' ), (int) $choice_id );
			}
		}

		return $form_data;
	}

	/**
	 * Get the value, that is used to prefill via dynamic or fallback population.
	 * Based on field data and current properties.
	 * Normal choices section.
	 *
	 * @since 1.6.0
	 *
	 * @param string $get_value  Value from a GET param, always a string, sanitized.
	 * @param array  $properties Field properties.
	 * @param array  $field      Current field specific data.
	 *
	 * @return array Modified field properties.
	 */
	protected function get_field_populated_single_property_value_normal_choices( $get_value, $properties, $field ) {

		$default_key = null;

		// For fields that have normal choices we need to add extra logic.
		foreach ( $field['choices'] as $choice_key => $choice_arr ) {
			$choice_value_key = isset( $field['show_values'] ) ? 'value' : 'label';
			if (
				(
					isset( $choice_arr[ $choice_value_key ] ) &&
					strtoupper( sanitize_text_field( $choice_arr[ $choice_value_key ] ) ) === strtoupper( $get_value )
				) ||
				(
					empty( $choice_arr[ $choice_value_key ] ) &&
					/* translators: %d - choice number. */
					$get_value === sprintf( esc_html__( 'Choice %d', 'wpforms-lite' ), (int) $choice_key )
				)
			) {
				$default_key = $choice_key;
				// Stop iterating over choices.
				break;
			}
		}

		// Redefine default choice only if population value has changed anything.
		if ( null !== $default_key ) {
			foreach ( $field['choices'] as $choice_key => $choice_arr ) {
				if ( $choice_key === $default_key ) {
					$properties['inputs'][ $choice_key ]['default']              = true;
					$properties['inputs'][ $choice_key ]['container']['class'][] = 'wpforms-selected';
					break;
				}
			}
		}

		return $properties;
	}

	/**
	 * Whether current field can be populated dynamically.
	 *
	 * @since 1.5.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return bool
	 */
	public function is_fallback_population_allowed( $properties, $field ) {

		$allowed = true;

		// Allow population on front-end only.
		if ( is_admin() ) {
			$allowed = false;
		}

		/*
		 * Commented out to allow partial fail for complex multi-inputs fields.
		 * Example: name field with first/last format and being required, filled out only first.
		 * On submit we will preserve those sub-inputs that are not empty and display an error for an empty.
		 */
		// Do not populate if there are errors for that field.
		/*
		$errors = wpforms()->process->errors;
		if ( ! empty( $errors[ $this->form_data['id'] ][ $field['id'] ] ) ) {
			$allowed = false;
		}
		*/

		// Require form id being the same for submitted and currently rendered form.
		if (
			! empty( $_POST['wpforms']['id'] ) && // phpcs:ignore
			(int) $_POST['wpforms']['id'] !== (int) $this->form_data['id'] // phpcs:ignore
		) {
			$allowed = false;
		}

		// Require $_POST of submitted field.
		if ( empty( $_POST['wpforms']['fields'] ) ) { // phpcs:ignore
			$allowed = false;
		}

		// Require field (processed and rendered) being the same.
		if ( ! isset( $_POST['wpforms']['fields'][ $field['id'] ] ) ) { // phpcs:ignore
			$allowed = false;
		}

		return apply_filters( 'wpforms_field_is_fallback_population_allowed', $allowed, $properties, $field );
	}

	/**
	 * Prefill the field value with a fallback value from form submission (in case of JS validation failed), that we get from $_POST.
	 *
	 * @since 1.5.0
	 *
	 * @param array $properties Field properties.
	 * @param array $field      Current field specific data.
	 *
	 * @return array Modified field properties.
	 */
	protected function field_prefill_value_property_fallback( $properties, $field ) {

		if ( ! $this->is_fallback_population_allowed( $properties, $field ) ) {
			return $properties;
		}

		if ( empty( $_POST['wpforms']['fields'] ) || ! is_array( $_POST['wpforms']['fields'] ) ) { // phpcs:ignore
			return $properties;
		}

		// We got user submitted raw data (not processed, will be done later).
		$raw_value = $_POST['wpforms']['fields'][ $field['id'] ]; // phpcs:ignore
		$input     = 'primary';

		if ( ! empty( $raw_value ) ) {
			$this->field_prefill_remove_choices_defaults( $field, $properties );
		}

		/*
		 * For this particular field this value may be either array or a string.
		 * In array - this is a complex field, like address.
		 * The key in array will be a sub-input (address1, state), and its appropriate value.
		 */
		if ( is_array( $raw_value ) ) {
			foreach ( $raw_value as $input => $single_value ) {
				$properties = $this->get_field_populated_single_property_value( $single_value, sanitize_key( $input ), $properties, $field );
			}
		} else {
			$properties = $this->get_field_populated_single_property_value( $raw_value, sanitize_key( $input ), $properties, $field );
		}

		return $properties;
	}

	/**
	 * Create the button for the 'Add Fields' tab, inside the form editor.
	 *
	 * @since 1.0.0
	 *
	 * @param array $fields List of form fields with their data.
	 *
	 * @return array
	 */
	public function field_button( $fields ) {

		// Add field information to fields array.
		$fields[ $this->group ]['fields'][] = array(
			'order' => $this->order,
			'name'  => $this->name,
			'type'  => $this->type,
			'icon'  => $this->icon,
		);

		// Wipe hands clean.
		return $fields;
	}

	/**
	 * Create the field options panel. Used by subclasses.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Converted to abstract method, as it's required for all fields.
	 *
	 * @param array $field Field data and settings.
	 */
	abstract public function field_options( $field );

	/**
	 * Create the field preview. Used by subclasses.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Converted to abstract method, as it's required for all fields.
	 *
	 * @param array $field Field data and settings.
	 */
	abstract public function field_preview( $field );

	/**
	 * Helper function to create field option elements.
	 *
	 * Field option elements are pieces that help create a field option.
	 * They are used to quickly build field options.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option Field option to render.
	 * @param array  $field  Field data and settings.
	 * @param array  $args   Field preview arguments.
	 * @param bool   $echo   Print or return the value. Print by default.
	 *
	 * @return mixed echo or return string
	 */
	public function field_element( $option, $field, $args = array(), $echo = true ) {

		$id     = (int) $field['id'];
		$class  = ! empty( $args['class'] ) ? wpforms_sanitize_classes( (array) $args['class'], true ) : '';
		$slug   = ! empty( $args['slug'] ) ? sanitize_title( $args['slug'] ) : '';
		$attrs  = '';
		$output = '';

		if ( ! empty( $args['data'] ) ) {
			foreach ( $args['data'] as $arg_key => $val ) {
				if ( is_array( $val ) ) {
					$val = wp_json_encode( $val );
				}
				$attrs .= ' data-' . $arg_key . '=\'' . $val . '\'';
			}
		}
		if ( ! empty( $args['attrs'] ) ) {
			foreach ( $args['attrs'] as $arg_key => $val ) {
				if ( is_array( $val ) ) {
					$val = wp_json_encode( $val );
				}
				$attrs .= $arg_key . '=\'' . $val . '\'';
			}
		}

		switch ( $option ) {

			// Row.
			case 'row':
				$output = sprintf(
					'<div class="wpforms-field-option-row wpforms-field-option-row-%s %s" id="wpforms-field-option-row-%d-%s" data-field-id="%d" %s>%s</div>',
					$slug,
					$class,
					$id,
					$slug,
					$id,
					$attrs,
					$args['content']
				);
				break;

			// Label.
			case 'label':
				$class  = ! empty( $class ) ? ' class="' . $class . '"' : '';
				$output = sprintf( '<label for="wpforms-field-option-%d-%s"%s>%s', $id, $slug, $class, esc_html( $args['value'] ) );
				if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
					$output .= sprintf( '<i class="fa fa-question-circle-o wpforms-help-tooltip" title="%s"></i>', esc_attr( $args['tooltip'] ) );
				}
				if ( isset( $args['after_tooltip'] ) && ! empty( $args['after_tooltip'] ) ) {
					$output .= $args['after_tooltip'];
				}
				$output .= '</label>';
				break;

			// Text input.
			case 'text':
				$type        = ! empty( $args['type'] ) ? esc_attr( $args['type'] ) : 'text';
				$placeholder = ! empty( $args['placeholder'] ) ? esc_attr( $args['placeholder'] ) : '';
				$before      = ! empty( $args['before'] ) ? '<span class="before-input">' . esc_html( $args['before'] ) . '</span>' : '';
				if ( ! empty( $before ) ) {
					$class .= ' has-before';
				}
				$output = sprintf( '%s<input type="%s" class="%s" id="wpforms-field-option-%d-%s" name="fields[%d][%s]" value="%s" placeholder="%s" %s>', $before, $type, $class, $id, $slug, $id, $slug, esc_attr( $args['value'] ), $placeholder, $attrs );
				break;

			// Textarea.
			case 'textarea':
				$rows   = ! empty( $args['rows'] ) ? (int) $args['rows'] : '3';
				$output = sprintf( '<textarea class="%s" id="wpforms-field-option-%d-%s" name="fields[%d][%s]" rows="%d" %s>%s</textarea>', $class, $id, $slug, $id, $slug, $rows, $attrs, $args['value'] );
				break;

			// Checkbox.
			case 'checkbox':
				$checked = checked( '1', $args['value'], false );
				$output  = sprintf( '<input type="checkbox" class="%s" id="wpforms-field-option-%d-%s" name="fields[%d][%s]" value="1" %s %s>', $class, $id, $slug, $id, $slug, $checked, $attrs );
				$output .= empty( $args['nodesc'] ) ? sprintf( '<label for="wpforms-field-option-%d-%s" class="inline">%s', $id, $slug, $args['desc'] ) : '';
				if ( isset( $args['tooltip'] ) && ! empty( $args['tooltip'] ) ) {
					$output .= sprintf( '<i class="fa fa-question-circle-o wpforms-help-tooltip" title="%s"></i>', esc_attr( $args['tooltip'] ) );
				}
				$output .= empty( $args['nodesc'] ) ? '</label>' : '';
				break;

			// Toggle.
			case 'toggle':
				$output = $this->field_element_toggle( $args, $id, $slug, $attrs, $class );

				break;

			// Select.
			case 'select':
				$options = $args['options'];
				$value   = isset( $args['value'] ) ? $args['value'] : '';
				$output  = sprintf( '<select class="%s" id="wpforms-field-option-%d-%s" name="fields[%d][%s]" %s>', $class, $id, $slug, $id, $slug, $attrs );
				foreach ( $options as $arg_key => $arg_option ) {
					$output .= sprintf( '<option value="%s" %s>%s</option>', esc_attr( $arg_key ), selected( $arg_key, $value, false ), $arg_option );
				}
				$output .= '</select>';
				break;
		}

		if ( ! $echo ) {
			return $output;
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Create field option toggle element.
	 *
	 * @since 1.6.8
	 *
	 * @param array   $args  Arguments.
	 * @param integer $id    Field ID.
	 * @param string  $slug  Field slug.
	 * @param string  $attrs Attributes.
	 * @param string  $class Class.
	 *
	 * @return string
	 */
	private function field_element_toggle( $args, $id, $slug, $attrs, $class ) {

		$input_id = sprintf(
			'wpforms-field-option-%d-%s',
			esc_attr( $id ),
			esc_attr( $slug )
		);

		$field_name = sprintf(
			'fields[%d][%s]',
			esc_attr( $id ),
			esc_attr( $slug )
		);

		$label = ! empty( $args['desc'] ) ? $args['desc'] : '';
		$value = ! empty( $args['value'] ) ? $args['value'] : '';

		// Compatibility with the `checkbox` element.
		$args['label-hide']  = ! empty( $args['nodesc'] ) ? $args['nodesc'] : false;
		$args['input-class'] = $class;

		return wpforms_panel_field_toggle_control( $args, $input_id, $field_name, $label, $value, $attrs );
	}

	/**
	 * Helper function to create common field options that are used frequently.
	 *
	 * @since 1.0.0
	 *
	 * @param string $option Field option to render.
	 * @param array  $field  Field data and settings.
	 * @param array  $args   Field preview arguments.
	 * @param bool   $echo   Print or return the value. Print by default.
	 *
	 * @return mixed echo or return string
	 */
	public function field_option( $option, $field, $args = array(), $echo = true ) {

		switch ( $option ) {

			/**
			 * Basic Fields.
			 */

			/*
			 * Basic Options markup.
			 */
			case 'basic-options':
				$markup = ! empty( $args['markup'] ) ? $args['markup'] : 'open';
				$class  = ! empty( $args['class'] ) ? esc_html( $args['class'] ) : '';

				if ( $markup === 'open' ) {
					$output = sprintf(
						'<div class="wpforms-field-option-field-title">%3$s <span>(ID #%1$d)</span></div>
						<div class="wpforms-field-option-group wpforms-field-option-group-basic active" id="wpforms-field-option-basic-%1$d">
							<a href="#" class="wpforms-field-option-group-toggle">%2$s</a>
							<div class="wpforms-field-option-group-inner %4$s">
						',
						(int) $field['id'],
						esc_html__( 'General', 'wpforms-lite' ),
						esc_html( $this->name ),
						esc_attr( $class )
					);

				} else {
					$output = '</div></div>';
				}
				break;

			/*
			 * Field Label.
			 */
			case 'label':
				$value   = ! empty( $field['label'] ) ? esc_attr( $field['label'] ) : '';
				$tooltip = esc_html__( 'Enter text for the form field label. Field labels are recommended and can be hidden in the Advanced Settings.', 'wpforms-lite' );
				$output  = $this->field_element( 'label', $field, array( 'slug' => 'label', 'value' => esc_html__( 'Label', 'wpforms-lite' ), 'tooltip' => $tooltip ), false );
				$output .= $this->field_element( 'text',  $field, array( 'slug' => 'label', 'value' => $value ), false );
				$output  = $this->field_element( 'row',   $field, array( 'slug' => 'label', 'content' => $output ), false );
				break;

			/*
			 * Field Description.
			 */
			case 'description':
				$value   = ! empty( $field['description'] ) ? esc_attr( $field['description'] ) : '';
				$tooltip = esc_html__( 'Enter text for the form field description.', 'wpforms-lite' );
				$output  = $this->field_element( 'label',    $field, array( 'slug' => 'description', 'value' => esc_html__( 'Description', 'wpforms-lite' ), 'tooltip' => $tooltip ), false );
				$output .= $this->field_element( 'textarea', $field, array( 'slug' => 'description', 'value' => $value ), false );
				$output  = $this->field_element( 'row',      $field, array( 'slug' => 'description', 'content' => $output ), false );
				break;

			/*
			 * Field Required toggle.
			 */
			case 'required':
				$default = ! empty( $args['default'] ) ? $args['default'] : '0';
				$value   = isset( $field['required'] ) ? $field['required'] : $default;
				$tooltip = esc_html__( 'Check this option to mark the field required. A form will not submit unless all required fields are provided.', 'wpforms-lite' );
				$output  = $this->field_element(
					'toggle',
					$field,
					[
						'slug'    => 'required',
						'value'   => $value,
						'desc'    => esc_html__( 'Required', 'wpforms-lite' ),
						'tooltip' => $tooltip,
					],
					false
				);
				$output  = $this->field_element(
					'row',
					$field,
					[
						'slug'    => 'required',
						'content' => $output,
					],
					false
				);

				break;

			/*
			 * Field Meta (field type and ID).
			 */
			case 'meta':
				$output  = sprintf( '<label>%s</label>', 'Type' );
				$output .= sprintf( '<p class="meta">%s <span class="id">(ID #%d)</span></p>', $this->name, $field['id'] );
				$output  = $this->field_element( 'row', $field, array( 'slug' => 'meta', 'content' => $output ), false );
				break;

			/*
			 * Code Block.
			 */
			case 'code':
				$value   = ! empty( $field['code'] ) ? esc_textarea( $field['code'] ) : '';
				$tooltip = esc_html__( 'Enter code for the form field.', 'wpforms-lite' );
				$output  = $this->field_element( 'label',    $field, array( 'slug' => 'code', 'value' => esc_html__( 'Code', 'wpforms-lite' ), 'tooltip' => $tooltip ), false );
				$output .= $this->field_element( 'textarea', $field, array( 'slug' => 'code', 'value' => $value ), false );
				$output  = $this->field_element( 'row',      $field, array( 'slug' => 'code', 'content' => $output ), false );
				break;

			/*
			 * Choices.
			 */
			case 'choices':
				$values     = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;
				$label      = ! empty( $args['label'] ) ? esc_html( $args['label'] ) : esc_html__( 'Choices', 'wpforms-lite' );
				$class      = array();
				$field_type = $this->type;

				if ( ! empty( $field['multiple'] ) ) {
					$field_type = 'checkbox';
				}

				if ( ! empty( $field['show_values'] ) ) {
					$class[] = 'show-values';
				}
				if ( ! empty( $field['dynamic_choices'] ) ) {
					$class[] = 'wpforms-hidden';
				}
				if ( ! empty( $field['choices_images'] ) ) {
					$class[] = 'show-images';
				}

				// Field label.
				$lbl = $this->field_element(
					'label',
					$field,
					array(
						'slug'          => 'choices',
						'value'         => $label,
						'tooltip'       => esc_html__( 'Add choices for the form field.', 'wpforms-lite' ),
						'after_tooltip' => '<a href="#" class="toggle-bulk-add-display toggle-unfoldable-cont"><i class="fa fa-download"></i><span>' . esc_html__( 'Bulk Add', 'wpforms-lite' ) . '</span></a>',
					),
					false
				);

				// Field contents.
				$fld = sprintf(
					'<ul data-next-id="%s" class="choices-list %s" data-field-id="%d" data-field-type="%s">',
					max( array_keys( $values ) ) + 1,
					wpforms_sanitize_classes( $class, true ),
					$field['id'],
					$this->type
				);
				foreach ( $values as $key => $value ) {
					$default   = ! empty( $value['default'] ) ? $value['default'] : '';
					$base      = sprintf( 'fields[%s][choices][%s]', $field['id'], $key );
					$image     = ! empty( $value['image'] ) ? $value['image'] : '';
					$image_btn = '';

					$fld .= '<li data-key="' . absint( $key ) . '">';
					$fld .= sprintf(
						'<input type="%s" name="%s[default]" class="default" value="1" %s>',
						$field_type === 'checkbox' ? 'checkbox' : 'radio',
						$base,
						checked( '1', $default, false )
					);
					$fld .= '<span class="move"><i class="fa fa-bars"></i></span>';
					$fld .= sprintf(
						'<input type="text" name="%s[label]" value="%s" class="label">',
						$base,
						esc_attr( $value['label'] )
					);
					$fld .= '<a class="add" href="#"><i class="fa fa-plus-circle"></i></a><a class="remove" href="#"><i class="fa fa-minus-circle"></i></a>';
					$fld .= sprintf(
						'<input type="text" name="%s[value]" value="%s" class="value">',
						$base,
						esc_attr( ! isset( $value['value'] ) ? '' : $value['value'] )
					);
					$fld .= '<div class="wpforms-image-upload">';
					$fld .= '<div class="preview">';

					if ( ! empty( $image ) ) {
						$fld .= sprintf(
							'<img src="%s"><a href="#" title="%s" class="wpforms-image-upload-remove"><i class="fa fa-trash-o"></i></a>',
							esc_url_raw( $image ),
							esc_attr__( 'Remove Image', 'wpforms-lite' )
						);

						$image_btn = ' style="display:none;"';
					}
					$fld .= '</div>';
					$fld .= sprintf(
						'<button class="wpforms-btn wpforms-btn-sm wpforms-btn-blue wpforms-btn-block wpforms-image-upload-add" data-after-upload="hide"%s>%s</button>',
						$image_btn,
						esc_html__( 'Upload Image', 'wpforms-lite' )
					);
					$fld .= sprintf(
						'<input type="hidden" name="%s[image]" value="%s" class="source">',
						$base,
						esc_url_raw( $image )
					);
					$fld .= '</div>';
					$fld .= '</li>';
				}
				$fld .= '</ul>';

				// Field note: dynamic status.
				$source  = '';
				$type    = '';
				$dynamic = ! empty( $field['dynamic_choices'] ) ? esc_html( $field['dynamic_choices'] ) : '';

				if ( 'post_type' === $dynamic && ! empty( $field[ 'dynamic_' . $dynamic ] ) ) {
					$type   = esc_html__( 'post type', 'wpforms-lite' );
					$pt     = get_post_type_object( $field[ 'dynamic_' . $dynamic ] );
					$source = '';
					if ( null !== $pt ) {
						$source = $pt->labels->name;
					}
				} elseif ( 'taxonomy' === $dynamic && ! empty( $field[ 'dynamic_' . $dynamic ] ) ) {
					$type   = esc_html__( 'taxonomy', 'wpforms-lite' );
					$tax    = get_taxonomy( $field[ 'dynamic_' . $dynamic ] );
					$source = '';
					if ( false !== $tax ) {
						$source = $tax->labels->name;
					}
				}

				$note = sprintf(
					'<div class="wpforms-alert-warning wpforms-alert %s">',
					! empty( $dynamic ) && ! empty( $field[ 'dynamic_' . $dynamic ] ) ? '' : 'wpforms-hidden'
				);

				$note .= '<h4>' . esc_html__( 'Dynamic Choices Active', 'wpforms-lite' ) . '</h4>';

				$note .= sprintf(
					/* translators: %1$s - source name; %2$s - type name. */
					'<p>' . esc_html__( 'Choices are dynamically populated from the %1$s %2$s. Go to the Advanced tab to change this.', 'wpforms-lite' ) . '</p>',
					'<span class="dynamic-name">' . $source . '</span>',
					'<span class="dynamic-type">' . $type . '</span>'
				);
				$note .= '</div>';

				// Final field output.
				$output = $this->field_element(
					'row',
					$field,
					array(
						'slug'    => 'choices',
						'content' => $lbl . $fld . $note,
					),
					false
				);
				break;

			/*
			 * Choices for payments.
			 */
			case 'choices_payments':
				$values     = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;
				$class      = array();
				$input_type = in_array( $field['type'], array( 'payment-multiple', 'payment-select' ), true ) ? 'radio' : 'checkbox';

				if ( ! empty( $field['choices_images'] ) ) {
					$class[] = 'show-images';
				}

				// Field label.
				$lbl = $this->field_element(
					'label',
					$field,
					array(
						'slug'    => 'choices',
						'value'   => esc_html__( 'Items', 'wpforms-lite' ),
						'tooltip' => esc_html__( 'Add choices for the form field.', 'wpforms-lite' ),
					),
					false
				);

				// Field contents.
				$fld = sprintf(
					'<ul data-next-id="%s" class="choices-list %s" data-field-id="%d" data-field-type="%s">',
					max( array_keys( $values ) ) + 1,
					wpforms_sanitize_classes( $class, true ),
					$field['id'],
					$this->type
				);
				foreach ( $values as $key => $value ) {
					$default   = ! empty( $value['default'] ) ? $value['default'] : '';
					$base      = sprintf( 'fields[%s][choices][%s]', $field['id'], $key );
					$image     = ! empty( $value['image'] ) ? $value['image'] : '';
					$image_btn = '';

					$fld .= '<li data-key="' . absint( $key ) . '">';
					$fld .= sprintf(
						'<input type="%s" name="%s[default]" class="default" value="1" %s>',
						$input_type,
						$base,
						checked( '1', $default, false )
					);
					$fld .= '<span class="move"><i class="fa fa-bars"></i></span>';
					$fld .= sprintf(
						'<input type="text" name="%s[label]" value="%s" class="label">',
						$base,
						esc_attr( $value['label'] )
					);
					$fld .= sprintf(
						'<input type="text" name="%s[value]" value="%s" class="value wpforms-money-input" placeholder="%s">',
						$base,
						esc_attr( $value['value'] ),
						wpforms_format_amount( 0 )
					);
					$fld .= '<a class="add" href="#"><i class="fa fa-plus-circle"></i></a><a class="remove" href="#"><i class="fa fa-minus-circle"></i></a>';
					$fld .= '<div class="wpforms-image-upload">';
					$fld .= '<div class="preview">';

					if ( ! empty( $image ) ) {
						$fld .= sprintf(
							'<img src="%s"><a href="#" title="%s" class="wpforms-image-upload-remove"><i class="fa fa-trash-o"></i></a>',
							esc_url_raw( $image ),
							esc_attr__( 'Remove Image', 'wpforms-lite' )
						);

						$image_btn = ' style="display:none;"';
					}
					$fld .= '</div>';
					$fld .= sprintf(
						'<button class="wpforms-btn wpforms-btn-sm wpforms-btn-blue wpforms-btn-block wpforms-image-upload-add" data-after-upload="hide"%s>%s</button>',
						$image_btn,
						esc_html__( 'Upload Image', 'wpforms-lite' )
					);
					$fld .= sprintf(
						'<input type="hidden" name="%s[image]" value="%s" class="source">',
						$base,
						esc_url_raw( $image )
					);
					$fld .= '</div>';
					$fld .= '</li>';
				}
				$fld .= '</ul>';

				// Final field output.
				$output = $this->field_element(
					'row',
					$field,
					[
						'slug'    => 'choices',
						'content' => $lbl . $fld,
					],
					false
				);
				break;

			/*
			 * Choices Images.
			 */
			case 'choices_images':
				// Field note: Image tips.
				$note  = sprintf(
					'<div class="wpforms-alert-warning wpforms-alert %s">',
					! empty( $field['choices_images'] ) ? '' : 'wpforms-hidden'
				);
				$note .= wp_kses( /* translators: %s - URL to the ActiveCampaign Getting started page. */
					__( '<h4>Images are not cropped or resized.</h4><p>For best results, they should be the same size and 250x250 pixels or smaller.</p>', 'wpforms-lite' ),
					[
						'h4' => [],
						'p'  => [],
					]
				);
				$note .= '</div>';

				// Field contents.
				$fld = $this->field_element(
					'toggle',
					$field,
					[
						'slug'    => 'choices_images',
						'value'   => isset( $field['choices_images'] ) ? '1' : '0',
						'desc'    => esc_html__( 'Use image choices', 'wpforms-lite' ),
						'tooltip' => esc_html__( 'Check this option to enable using images with the choices.', 'wpforms-lite' ),
					],
					false
				);

				// Final field output.
				$output = $this->field_element(
					'row',
					$field,
					[
						'slug'    => 'choices_images',
						'class'   => ! empty( $field['dynamic_choices'] ) ? 'wpforms-hidden' : '',
						'content' => $note . $fld,
					],
					false
				);
				break;

			/*
			 * Choices Images Style.
			 */
			case 'choices_images_style':
				// Field label.
				$lbl = $this->field_element(
					'label',
					$field,
					array(
						'slug'    => 'choices_images_style',
						'value'   => esc_html__( 'Image Choice Style', 'wpforms-lite' ),
						'tooltip' => esc_html__( 'Select the style for the image choices.', 'wpforms-lite' ),
					),
					false
				);

				// Field contents.
				$fld = $this->field_element(
					'select',
					$field,
					array(
						'slug'    => 'choices_images_style',
						'value'   => ! empty( $field['choices_images_style'] ) ? esc_attr( $field['choices_images_style'] ) : 'modern',
						'options' => array(
							'modern'  => esc_html__( 'Modern', 'wpforms-lite' ),
							'classic' => esc_html__( 'Classic', 'wpforms-lite' ),
							'none'    => esc_html__( 'None', 'wpforms-lite' ),
						),
					),
					false
				);

				// Final field output.
				$output = $this->field_element(
					'row',
					$field,
					array(
						'slug'    => 'choices_images_style',
						'content' => $lbl . $fld,
						'class'   => ! empty( $field['choices_images'] ) ? '' : 'wpforms-hidden',
					),
					false
				);
				break;

			/**
			 * Advanced Fields.
			 */

			/*
			 * Default value.
			 */
			case 'default_value':
				$value   = ! empty( $field['default_value'] ) || ( isset( $field['default_value'] ) && '0' === (string) $field['default_value'] ) ? esc_attr( $field['default_value'] ) : '';
				$tooltip = esc_html__( 'Enter text for the default form field value.', 'wpforms-lite' );
				$toggle  = '<a href="#" class="toggle-smart-tag-display toggle-unfoldable-cont" data-type="other"><i class="fa fa-tags"></i><span>' . esc_html__( 'Show Smart Tags', 'wpforms-lite' ) . '</span></a>';
				$output  = $this->field_element( 'label', $field, array( 'slug' => 'default_value', 'value' => esc_html__( 'Default Value', 'wpforms-lite' ), 'tooltip' => $tooltip, 'after_tooltip' => $toggle ), false );
				$output .= $this->field_element( 'text',  $field, array( 'slug' => 'default_value', 'value' => $value ), false );
				$output  = $this->field_element( 'row',   $field, array( 'slug' => 'default_value', 'content' => $output ), false );
				break;

			/*
			 * Size.
			 */
			case 'size':
				$value   = ! empty( $field['size'] ) ? esc_attr( $field['size'] ) : 'medium';
				$class   = ! empty( $args['class'] ) ? esc_html( $args['class'] ) : '';
				$tooltip = esc_html__( 'Select the default form field size.', 'wpforms-lite' );
				$options = array(
					'small'  => esc_html__( 'Small', 'wpforms-lite' ),
					'medium' => esc_html__( 'Medium', 'wpforms-lite' ),
					'large'  => esc_html__( 'Large', 'wpforms-lite' ),
				);
				$output  = $this->field_element( 'label',  $field, array( 'slug' => 'size', 'value' => esc_html__( 'Field Size', 'wpforms-lite' ), 'tooltip' => $tooltip ), false );
				$output .= $this->field_element( 'select', $field, array( 'slug' => 'size', 'value' => $value, 'options' => $options ), false );
				$output  = $this->field_element( 'row',    $field, array( 'slug' => 'size', 'content' => $output, 'class' => $class ), false );
				break;

			/*
			 * Advanced Options markup.
			 */
			case 'advanced-options':
				$markup = ! empty( $args['markup'] ) ? $args['markup'] : 'open';

				if ( $markup === 'open' ) {
					$override = apply_filters( 'wpforms_advanced_options_override', false );
					$override = ! empty( $override ) ? 'style="display:' . $override . ';"' : '';
					$output   = sprintf( '<div class="wpforms-field-option-group wpforms-field-option-group-advanced" id="wpforms-field-option-advanced-%d" %s>', $field['id'], $override );
					$output  .= sprintf( '<a href="#" class="wpforms-field-option-group-toggle">%s</a>', esc_html__( 'Advanced', 'wpforms-lite' ) );
					$output  .= '<div class="wpforms-field-option-group-inner">';

				} else {
					$output = '</div></div>';
				}
				break;

			/*
			 * Placeholder.
			 */
			case 'placeholder':
				$value   = ! empty( $field['placeholder'] ) ? esc_attr( $field['placeholder'] ) : '';
				$tooltip = esc_html__( 'Enter text for the form field placeholder.', 'wpforms-lite' );
				$output  = $this->field_element( 'label', $field, array( 'slug' => 'placeholder', 'value' => esc_html__( 'Placeholder Text', 'wpforms-lite' ), 'tooltip' => $tooltip ), false );
				$output .= $this->field_element( 'text',  $field, array( 'slug' => 'placeholder', 'value' => $value ), false );
				$output  = $this->field_element( 'row',   $field, array( 'slug' => 'placeholder', 'content' => $output ), false );
				break;

			/*
			 * CSS classes.
			 */
			case 'css':
				$toggle  = '';
				$value   = ! empty( $field['css'] ) ? esc_attr( $field['css'] ) : '';
				$tooltip = esc_html__( 'Enter CSS class names for the form field container. Class names should be separated with spaces.', 'wpforms-lite' );
				if ( 'pagebreak' !== $field['type'] ) {
					$toggle = '<a href="#" class="toggle-layout-selector-display toggle-unfoldable-cont"><i class="fa fa-th-large"></i><span>' . esc_html__( 'Show Layouts', 'wpforms-lite' ) . '</span></a>';
				}
				// Build output.
				$output  = $this->field_element( 'label', $field, array( 'slug' => 'css', 'value' => esc_html__( 'CSS Classes', 'wpforms-lite' ), 'tooltip' => $tooltip, 'after_tooltip' => $toggle ), false );
				$output .= $this->field_element( 'text', $field, array( 'slug' => 'css', 'value' => $value ), false );
				$output  = $this->field_element( 'row', $field, array( 'slug' => 'css', 'content' => $output ), false );
				break;

			/*
			 * Hide Label.
			 */
			case 'label_hide':
				$value   = isset( $field['label_hide'] ) ? $field['label_hide'] : '0';
				$tooltip = esc_html__( 'Check this option to hide the form field label.', 'wpforms-lite' );

				// Build output.
				$output = $this->field_element(
					'toggle',
					$field,
					[
						'slug'    => 'label_hide',
						'value'   => $value,
						'desc'    => esc_html__( 'Hide Label', 'wpforms-lite' ),
						'tooltip' => $tooltip,
					],
					false
				);
				$output = $this->field_element(
					'row',
					$field,
					[
						'slug'    => 'label_hide',
						'content' => $output,
					],
					false
				);

				break;

			/*
			 * Hide sublabels.
			 */
			case 'sublabel_hide':
				$value   = isset( $field['sublabel_hide'] ) ? $field['sublabel_hide'] : '0';
				$tooltip = esc_html__( 'Check this option to hide the form field sublabel.', 'wpforms-lite' );

				// Build output.
				$output = $this->field_element(
					'toggle',
					$field,
					[
						'slug'    => 'sublabel_hide',
						'value'   => $value,
						'desc'    => esc_html__( 'Hide Sublabels', 'wpforms-lite' ),
						'tooltip' => $tooltip,
					],
					false
				);
				$output = $this->field_element(
					'row',
					$field,
					[
						'slug'    => 'sublabel_hide',
						'content' => $output,
					],
					false
				);

				break;

			/*
			 * Input Columns.
			 */
			case 'input_columns':
				$value   = ! empty( $field['input_columns'] ) ? esc_attr( $field['input_columns'] ) : '';
				$tooltip = esc_html__( 'Select the layout for displaying field choices.', 'wpforms-lite' );
				$options = array(
					''       => esc_html__( 'One Column', 'wpforms-lite' ),
					'2'      => esc_html__( 'Two Columns', 'wpforms-lite' ),
					'3'      => esc_html__( 'Three Columns', 'wpforms-lite' ),
					'inline' => esc_html__( 'Inline', 'wpforms-lite' ),
				);
				$output  = $this->field_element( 'label', $field, array( 'slug' => 'input_columns', 'value' => esc_html__( 'Choice Layout', 'wpforms-lite' ), 'tooltip' => $tooltip ), false );
				$output .= $this->field_element( 'select', $field, array( 'slug' => 'input_columns', 'value' => $value, 'options' => $options ), false );
				$output  = $this->field_element( 'row', $field, array( 'slug' => 'input_columns', 'content' => $output ), false );
				break;

			/*
			 * Dynamic Choices.
			 */
			case 'dynamic_choices':
				$value   = ! empty( $field['dynamic_choices'] ) ? esc_attr( $field['dynamic_choices'] ) : '';
				$tooltip = esc_html__( 'Select auto-populate method to use.', 'wpforms-lite' );
				$options = array(
					''          => esc_html__( 'Off', 'wpforms-lite' ),
					'post_type' => esc_html__( 'Post Type', 'wpforms-lite' ),
					'taxonomy'  => esc_html__( 'Taxonomy', 'wpforms-lite' ),
				);
				$output  = $this->field_element( 'label', $field, array( 'slug' => 'dynamic_choices', 'value' => esc_html__( 'Dynamic Choices', 'wpforms-lite' ), 'tooltip' => $tooltip ), false );
				$output .= $this->field_element( 'select', $field, array( 'slug' => 'dynamic_choices', 'value' => $value, 'options' => $options ), false );
				$output  = $this->field_element( 'row', $field, array( 'slug' => 'dynamic_choices', 'content' => $output ), false );
				break;

			/*
			 * Dynamic Choices Source.
			 */
			case 'dynamic_choices_source':
				$output = '';
				$type   = ! empty( $field['dynamic_choices'] ) ? esc_attr( $field['dynamic_choices'] ) : '';

				if ( ! empty( $type ) ) {

					$type_name = '';
					$items     = array();

					if ( 'post_type' === $type ) {

						$type_name = esc_html__( 'Post Type', 'wpforms-lite' );
						$items     = get_post_types(
							array(
								'public' => true,
							),
							'objects'
						);
						unset( $items['attachment'] );

					} elseif ( 'taxonomy' === $type ) {

						$type_name = esc_html__( 'Taxonomy', 'wpforms-lite' );
						$items     = get_taxonomies(
							array(
								'public' => true,
							),
							'objects'
						);
						unset( $items['post_format'] );
					}

					/* translators: %s - dynamic source type name. */
					$tooltip = sprintf( esc_html__( 'Select %s to use for auto-populating field choices.', 'wpforms-lite' ), $type_name );
					/* translators: %s - dynamic source type name. */
					$label   = sprintf( esc_html__( 'Dynamic %s Source', 'wpforms-lite' ), $type_name );
					$options = array();
					$source  = ! empty( $field[ 'dynamic_' . $type ] ) ? esc_attr( $field[ 'dynamic_' . $type ] ) : '';

					foreach ( $items as $key => $item ) {
						$options[ $key ] = $item->labels->name;
					}

					// Field option label.
					$option_label = $this->field_element(
						'label',
						$field,
						array(
							'slug'    => 'dynamic_' . $type,
							'value'   => $label,
							'tooltip' => $tooltip,
						),
						false
					);

					// Field option select input.
					$option_input = $this->field_element(
						'select',
						$field,
						array(
							'slug'    => 'dynamic_' . $type,
							'options' => $options,
							'value'   => $source,
						),
						false
					);

					// Field option row (markup) including label and input.
					$output = $this->field_element(
						'row',
						$field,
						array(
							'slug'    => 'dynamic_' . $type,
							'content' => $option_label . $option_input,
						),
						false
					);
				} // End if().
				break;
		}

		if ( ! $echo ) {
			return $output;
		}

		if ( in_array( $option, array( 'basic-options', 'advanced-options' ), true ) ) {

			if ( 'open' === $markup ) {
				do_action( "wpforms_field_options_before_{$option}", $field, $this );
			}

			if ( 'close' === $markup ) {
				do_action( "wpforms_field_options_bottom_{$option}", $field, $this );
			}

			echo $output; // WPCS: XSS ok.

			if ( 'open' === $markup ) {
				do_action( "wpforms_field_options_top_{$option}", $field, $this );
			}

			if ( 'close' === $markup ) {
				do_action( "wpforms_field_options_after_{$option}", $field, $this );
			}
		} else {
			echo $output; // WPCS: XSS ok.
		}
	}

	/**
	 * Helper function to create common field options that are used frequently
	 * in the field preview.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Added support for <select> HTML tag for choices.
	 * @since 1.6.1 Added multiple select support.
	 *
	 * @param string $option Field option to render.
	 * @param array  $field  Field data and settings.
	 * @param array  $args   Field preview arguments.
	 * @param bool   $echo   Print or return the value. Print by default.
	 *
	 * @return mixed Print or return a string.
	 */
	public function field_preview_option( $option, $field, $args = array(), $echo = true ) {

		$output       = '';
		$class        = ! empty( $args['class'] ) ? wpforms_sanitize_classes( $args['class'] ) : '';
		$allowed_tags = wpforms_builder_preview_get_allowed_tags();

		switch ( $option ) {

			case 'label':
				$label  = isset( $field['label'] ) && ! empty( $field['label'] ) ? esc_html( $field['label'] ) : '';
				$output = sprintf( '<label class="label-title %s"><span class="text">%s</span><span class="required">*</span></label>', $class, $label );

				break;

			case 'description':
				$description = isset( $field['description'] ) && ! empty( $field['description'] ) ? wp_kses( $field['description'], $allowed_tags ) : '';
				$description = strpos( $class, 'nl2br' ) !== false ? nl2br( $description ) : $description;
				$output      = sprintf( '<div class="description %s">%s</div>', $class, $description );

				break;

			case 'choices':
				$fields_w_choices = [ 'checkbox', 'gdpr-checkbox', 'select', 'payment-select', 'radio', 'payment-multiple', 'payment-checkbox' ];

				$values  = ! empty( $field['choices'] ) ? $field['choices'] : $this->defaults;
				$dynamic = ! empty( $field['dynamic_choices'] ) ? $field['dynamic_choices'] : false;
				$total   = count( $values );
				$values  = array_slice( $values, 0, 20 );

				/*
				 * Check to see if this field is configured for Dynamic Choices,
				 * either auto populating from a post type or a taxonomy.
				 */
				if ( ! empty( $field['dynamic_post_type'] ) || ! empty( $field['dynamic_taxonomy'] ) ) {
					switch ( $dynamic ) {
						case 'post_type':
							// Post type dynamic populating.
							$total_obj = wp_count_posts( $field['dynamic_post_type'] );
							$total     = isset( $total_obj->publish ) ? (int) $total_obj->publish : 0;
							$values    = [];
							$posts     = wpforms_get_hierarchical_object(
								apply_filters(
									'wpforms_dynamic_choice_post_type_args',
									[
										'post_type'      => $field['dynamic_post_type'],
										'posts_per_page' => 20,
										'orderby'        => 'title',
										'order'          => 'ASC',
									],
									$field,
									$this->form_id
								),
								true
							);

							foreach ( $posts as $post ) {
								$values[] = [
									'label' => $post->post_title,
								];
							}
							break;

						case 'taxonomy':
							// Taxonomy dynamic populating.
							$total  = (int) wp_count_terms( $field['dynamic_taxonomy'] );
							$values = [];
							$terms  = wpforms_get_hierarchical_object(
								apply_filters(
									'wpforms_dynamic_choice_taxonomy_args',
									[
										'taxonomy'   => $field['dynamic_taxonomy'],
										'hide_empty' => false,
										'number'     => 20,
									],
									$field,
									$this->form_id
								),
								true
							);

							foreach ( $terms as $term ) {
								$values[] = [
									'label' => $term->name,
								];
							}
							break;
					}
				}

				// Notify if dynamic choices source is currently empty.
				if ( empty( $values ) ) {
					$values = [
						[
							'label' => esc_html__( '(empty)', 'wpforms-lite' ),
						],
					];
				}

				// Build output.
				if ( ! in_array( $field['type'], $fields_w_choices, true ) ) {
					break;
				}

				switch ( $field['type'] ) {
					case 'checkbox':
					case 'gdpr-checkbox':
					case 'payment-checkbox':
						$type = 'checkbox';

						break;

					case 'select':
					case 'payment-select':
						$type = 'select';

						break;

					default:
						$type = 'radio';

						break;
				}

				$list_class  = [ 'primary-input' ];
				$with_images = empty( $field['dynamic_choices'] ) && ! empty( $field['choices_images'] );

				if ( $with_images ) {
					$list_class[] = 'wpforms-image-choices';
					$list_class[] = 'wpforms-image-choices-' . sanitize_html_class( $field['choices_images_style'] );
				}

				if ( ! empty( $class ) ) {
					$list_class[] = $class;
				}

				// Special rules for <select>-based fields.
				if ( $type === 'select' ) {
					$multiple    = ! empty( $field['multiple'] ) ? ' multiple' : '';
					$placeholder = ! empty( $field['placeholder'] ) ? $field['placeholder'] : '';

					$output = sprintf(
						'<select class="%s"%s readonly>',
						wpforms_sanitize_classes( $list_class, true ),
						$multiple
					);

					// Optional placeholder.
					if ( ! empty( $placeholder ) ) {
						$output .= sprintf(
							'<option value="" class="placeholder">%s</option>',
							esc_html( $placeholder )
						);
					}

					// Build the select options.
					foreach ( $values as $key => $value ) {

						$default  = isset( $value['default'] ) ? (bool) $value['default'] : false;
						$selected = ! empty( $placeholder ) && empty( $multiple ) ? '' : selected( true, $default, false );

						$label = isset( $value['label'] ) ? trim( $value['label'] ) : '';

						/* translators: %d - Choice item number. */
						$label  = $label !== '' ? $label : sprintf( esc_html__( 'Choice %d', 'wpforms-lite' ), (int) $key );
						$label .= ! empty( $field['show_price_after_labels'] ) && isset( $value['value'] ) ? ' - ' . wpforms_format_amount( wpforms_sanitize_amount( $value['value'] ), true ) : '';

						$output .= sprintf(
							'<option value="%2$s" %1$s>%2$s</option>',
							$selected,
							esc_html( $label )
						);
					}

					$output .= '</select>';
				} else {
					// Normal checkbox/radio-based fields.
					$output = sprintf(
						'<ul class="%s">',
						wpforms_sanitize_classes( $list_class, true )
					);

					foreach ( $values as $key => $value ) {

						$default     = isset( $value['default'] ) ? $value['default'] : '';
						$selected    = checked( '1', $default, false );
						$input_class = [];
						$item_class  = [];

						if ( ! empty( $value['default'] ) ) {
							$item_class[] = 'wpforms-selected';
						}

						if ( $with_images ) {
							$item_class[] = 'wpforms-image-choices-item';
						}

						$output .= sprintf(
							'<li class="%s">',
							wpforms_sanitize_classes( $item_class, true )
						);

						$label = isset( $value['label'] ) ? trim( $value['label'] ) : '';

						/* translators: %d - Choice item number. */
						$label  = $label !== '' ? $label : sprintf( esc_html__( 'Choice %d', 'wpforms-lite' ), (int) $key );
						$label .= ! empty( $field['show_price_after_labels'] ) && isset( $value['value'] ) ? ' - ' . wpforms_format_amount( wpforms_sanitize_amount( $value['value'] ), true ) : '';

						if ( $with_images ) {

							if ( in_array( $field['choices_images_style'], [ 'modern', 'classic' ], true ) ) {
								$input_class[] = 'wpforms-screen-reader-element';
							}

							$output .= '<label>';

							$output .= sprintf(
								'<span class="wpforms-image-choices-image"><img src="%s" alt="%s"%s></span>',
								! empty( $value['image'] ) ? esc_url( $value['image'] ) : WPFORMS_PLUGIN_URL . 'assets/images/builder/placeholder-200x125.svg',
								esc_attr( $value['label'] ),
								! empty( $value['label'] ) ? ' title="' . esc_attr( $value['label'] ) . '"' : ''
							);

							if ( $field['choices_images_style'] === 'none' ) {
								$output .= '<br>';
							}

							$output .= sprintf(
								'<input type="%s" class="%s" %s readonly>',
								$type,
								wpforms_sanitize_classes( $input_class, true ),
								$selected
							);

							$output .= '<span class="wpforms-image-choices-label">' . wp_kses( $label, $allowed_tags ) . '</span>';

							$output .= '</label>';

						} else {
							$output .= sprintf(
								'<input type="%s" %s readonly> %s',
								$type,
								$selected,
								wp_kses( $label, $allowed_tags )
							);
						}

						$output .= '</li>';
					}

					$output .= '</ul>';

					/*
					 * Contains more than 20 items, include a note about a limited subset of results displayed.
					*/
					if ( $total > 20 ) {
						$output .= '<div class="wpforms-alert-dynamic wpforms-alert wpforms-alert-warning">';
						$output .= sprintf(
							wp_kses( /* translators: %s - total amount of choices. */
								__( 'Showing the first 20 choices.<br> All %s choices will be displayed when viewing the form.', 'wpforms-lite' ),
								[
									'br' => [],
								]
							),
							$total
						);
						$output .= '</div>';
					}
				}

				break;
		}

		if ( ! $echo ) {
			return $output;
		}

		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Create a new field in the admin AJAX editor.
	 *
	 * @since 1.0.0
	 */
	public function field_new() {

		// Run a security check.
		check_ajax_referer( 'wpforms-builder', 'nonce' );

		// Check for permissions.
		if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
			die( esc_html__( 'You do not have permission.', 'wpforms-lite' ) );
		}

		// Check for form ID.
		if ( ! isset( $_POST['id'] ) || empty( $_POST['id'] ) ) {
			die( esc_html__( 'No form ID found', 'wpforms-lite' ) );
		}

		// Check for field type to add.
		if ( ! isset( $_POST['type'] ) || empty( $_POST['type'] ) ) {
			die( esc_html__( 'No field type found', 'wpforms-lite' ) );
		}

		// Grab field data.
		$field_args     = ! empty( $_POST['defaults'] ) ? (array) $_POST['defaults'] : array();
		$field_type     = esc_attr( $_POST['type'] );
		$field_id       = wpforms()->form->next_field_id( $_POST['id'] );
		$field          = array(
			'id'          => $field_id,
			'type'        => $field_type,
			'label'       => $this->name,
			'description' => '',
		);
		$field          = wp_parse_args( $field_args, $field );
		$field          = apply_filters( 'wpforms_field_new_default', $field );
		$field_required = apply_filters( 'wpforms_field_new_required', '', $field );
		$field_class    = apply_filters( 'wpforms_field_new_class', '', $field );

		// Field types that default to required.
		if ( ! empty( $field_required ) ) {
			$field_required    = 'required';
			$field['required'] = '1';
		}

		// Build Preview.
		ob_start();
		$this->field_preview( $field );
		$prev    = ob_get_clean();
		$preview = sprintf(
			'<div class="wpforms-field wpforms-field-%1$s %2$s %3$s" id="wpforms-field-%4$d" data-field-id="%4$d" data-field-type="%5$s">',
			esc_attr( $field_type ),
			esc_attr( $field_required ),
			esc_attr( $field_class ),
			(int) $field['id'],
			esc_attr( $field_type )
		);

		if ( apply_filters( 'wpforms_field_new_display_duplicate_button', true, $field ) ) {
			$preview .= sprintf( '<a href="#" class="wpforms-field-duplicate" title="%s"><i class="fa fa-files-o" aria-hidden="true"></i></a>', esc_attr__( 'Duplicate Field', 'wpforms-lite' ) );
		}

		$preview .= sprintf( '<a href="#" class="wpforms-field-delete" title="%s"><i class="fa fa-trash-o"></i></a>', esc_attr__( 'Delete Field', 'wpforms-lite' ) );
		$preview .= sprintf(
			// language=HTML PhpStorm.
			'<div class="wpforms-field-helper">
				<span class="wpforms-field-helper-edit">%s</span>
				<span class="wpforms-field-helper-drag">%s</span>
			</div>',
			esc_html__( 'Click to edit.', 'wpforms-lite' ),
			esc_html__( 'Drag to reorder.', 'wpforms-lite' )
		);
		$preview .= $prev;
		$preview .= '</div>';

		// Build Options.
		$class    = apply_filters( 'wpforms_builder_field_option_class', '', $field );
		$options  = sprintf( '<div class="wpforms-field-option wpforms-field-option-%s %s" id="wpforms-field-option-%d" data-field-id="%d">', sanitize_html_class( $field['type'] ), wpforms_sanitize_classes( $class ), (int) $field['id'], (int) $field['id'] );
		$options .= sprintf( '<input type="hidden" name="fields[%d][id]" value="%d" class="wpforms-field-option-hidden-id">', $field['id'], $field['id'] );
		$options .= sprintf( '<input type="hidden" name="fields[%d][type]" value="%s" class="wpforms-field-option-hidden-type">', $field['id'], esc_attr( $field['type'] ) );
		ob_start();
		$this->field_options( $field );
		$options .= ob_get_clean();
		$options .= '</div>';

		// Prepare to return compiled results.
		wp_send_json_success(
			array(
				'form_id' => (int) $_POST['id'],
				'field'   => $field,
				'preview' => $preview,
				'options' => $options,
			)
		);
	}

	/**
	 * Display the field input elements on the frontend.
	 *
	 * @since 1.0.0
	 * @since 1.5.0 Converted to abstract method, as it's required for all fields.
	 *
	 * @param array $field      Field data and settings.
	 * @param array $field_atts Field attributes.
	 * @param array $form_data  Form data and settings.
	 */
	abstract public function field_display( $field, $field_atts, $form_data );

	/**
	 * Display field input errors if present.
	 *
	 * @since 1.3.7
	 *
	 * @param string $key   Input key.
	 * @param array  $field Field data and settings.
	 */
	public function field_display_error( $key, $field ) {

		// Need an error.
		if ( empty( $field['properties']['error']['value'][ $key ] ) ) {
			return;
		}

		printf(
			'<label class="wpforms-error" for="%s">%s</label>',
			esc_attr( $field['properties']['inputs'][ $key ]['id'] ),
			esc_html( $field['properties']['error']['value'][ $key ] )
		);
	}

	/**
	 * Display field input sublabel if present.
	 *
	 * @since 1.3.7
	 *
	 * @param string $key      Input key.
	 * @param string $position Sublabel position.
	 * @param array  $field    Field data and settings.
	 */
	public function field_display_sublabel( $key, $position, $field ) {

		// Need a sublabel value.
		if ( empty( $field['properties']['inputs'][ $key ]['sublabel']['value'] ) ) {
			return;
		}

		$pos    = ! empty( $field['properties']['inputs'][ $key ]['sublabel']['position'] ) ? $field['properties']['inputs'][ $key ]['sublabel']['position'] : 'after';
		$hidden = ! empty( $field['properties']['inputs'][ $key ]['sublabel']['hidden'] ) ? 'wpforms-sublabel-hide' : '';

		if ( $pos !== $position ) {
			return;
		}

		printf(
			'<label for="%s" class="wpforms-field-sublabel %s %s">%s</label>',
			esc_attr( $field['properties']['inputs'][ $key ]['id'] ),
			sanitize_html_class( $pos ),
			$hidden,
			$field['properties']['inputs'][ $key ]['sublabel']['value']
		);
	}

	/**
	 * Validate field on form submit.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param mixed $field_submit Field value that was submitted.
	 * @param array $form_data    Form data and settings.
	 */
	public function validate( $field_id, $field_submit, $form_data ) {

		// Basic required check - If field is marked as required, check for entry data.
		if ( ! empty( $form_data['fields'][ $field_id ]['required'] ) && empty( $field_submit ) && '0' !== (string) $field_submit ) {
			wpforms()->process->errors[ $form_data['id'] ][ $field_id ] = wpforms_get_required_label();
		}
	}

	/**
	 * Format and sanitize field.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $field_id     Field ID.
	 * @param mixed $field_submit Field value that was submitted.
	 * @param array $form_data    Form data and settings.
	 */
	public function format( $field_id, $field_submit, $form_data ) {

		if ( is_array( $field_submit ) ) {
			$field_submit = array_filter( $field_submit );
			$field_submit = implode( "\r\n", $field_submit );
		}

		$name = ! empty( $form_data['fields'][ $field_id ]['label'] ) ? sanitize_text_field( $form_data['fields'][ $field_id ]['label'] ) : '';

		// Sanitize but keep line breaks.
		$value = wpforms_sanitize_textarea_field( $field_submit );

		wpforms()->process->fields[ $field_id ] = array(
			'name'  => $name,
			'value' => $value,
			'id'    => absint( $field_id ),
			'type'  => $this->type,
		);
	}

	/**
	 * Get field name for ajax error message.
	 *
	 * @since 1.6.3
	 *
	 * @param string $name  Field name for error triggered.
	 * @param array  $field Field settings.
	 * @param array  $props List of properties.
	 * @param string $error Error message.
	 *
	 * @return string
	 */
	public function ajax_error_field_name( $name, $field, $props, $error ) {

		if ( $name ) {
			return $name;
		}
		$input = isset( $props['inputs']['primary'] ) ? $props['inputs']['primary'] : end( $props['inputs'] );

		return (string) isset( $input['attr']['name'] ) ? $input['attr']['name'] : '';
	}

	/**
	 * Enqueue Choicesjs script and config.
	 *
	 * @param array $forms Forms on the current page.
	 *
	 * @since 1.6.3
	 */
	protected function enqueue_choicesjs_once( $forms ) {

		if ( wpforms()->frontend->is_choicesjs_enqueued ) {
			return;
		}

		wp_enqueue_script(
			'wpforms-choicesjs',
			WPFORMS_PLUGIN_URL . 'assets/js/choices.min.js',
			array(),
			'9.0.1',
			true
		);

		$config = [
			'removeItemButton'  => true,
			'shouldSort'        => false,
			'loadingText'       => esc_html__( 'Loading...', 'wpforms-lite' ),
			'noResultsText'     => esc_html__( 'No results found.', 'wpforms-lite' ),
			'noChoicesText'     => esc_html__( 'No choices to choose from.', 'wpforms-lite' ),
			'itemSelectText'    => esc_attr__( 'Press to select.', 'wpforms-lite' ),
			'uniqueItemText'    => esc_html__( 'Only unique values can be added.', 'wpforms-lite' ),
			'customAddItemText' => esc_html__( 'Only values matching specific conditions can be added.', 'wpforms-lite' ),
		];

		// Allow theme/plugin developers to modify the provided or add own Choices.js settings.
		$config = apply_filters( 'wpforms_field_select_choicesjs_config', $config, $forms, $this );

		wp_localize_script(
			'wpforms-choicesjs',
			'wpforms_choicesjs_config',
			$config
		);

		wpforms()->frontend->is_choicesjs_enqueued = true;
	}

	/**
	 * Whether a Choicesjs search area should be shown.
	 *
	 * @since 1.6.4
	 *
	 * @param int $choices_count Choices amount.
	 *
	 * @return bool
	 */
	protected function is_choicesjs_search_enabled( $choices_count ) {

		// We should auto hide/remove search, if less than 8 choices.
		return $choices_count >= (int) apply_filters( 'wpforms_field_choicesjs_search_enabled_items_min', 8 );
	}
}
