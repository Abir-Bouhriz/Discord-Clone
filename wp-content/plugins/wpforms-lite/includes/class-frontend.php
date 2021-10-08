<?php

/**
 * Form front-end rendering.
 *
 * @since 1.0.0
 */
class WPForms_Frontend {

	/**
	 * Store form data to be referenced later.
	 *
	 * @since 1.0.0
	 * @var array
	 */
	public $forms;

	/**
	 * Store information for multi-page forms.
	 *
	 * Forms that do not contain pages return false, otherwise returns an array
	 * that contains the number of total pages and page counter used when
	 * displaying pagebreak fields.
	 *
	 * @since 1.3.7
	 *
	 * @var array
	 */
	public $pages = false;

	/**
	 * Store a form confirmation message.
	 *
	 * @since 1.4.8
	 * @todo Remove in favor of \WPForms_Process::$confirmation_message().
	 *
	 * @var string
	 */
	public $confirmation_message = '';

	/**
	 * If the active form confirmation should auto scroll.
	 *
	 * @since 1.4.9
	 *
	 * @var bool
	 */
	public $confirmation_message_scroll = false;

	/**
	 * Whether ChoiceJS library has already been enqueued on the front end.
	 * This lib is used in different fields that can enqueue it separately,
	 * and we use this property to avoid config duplication.
	 *
	 * @since 1.6.3
	 *
	 * @var bool
	 */
	public $is_choicesjs_enqueued = false;

	/**
	 * Primary class constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {

		$this->forms = [];

		// Filters.
		add_filter( 'amp_skip_post', [ $this, 'amp_skip_post' ] );

		// Actions.
		add_action( 'wpforms_frontend_output_success', [ $this, 'confirmation' ], 10, 3 );
		add_action( 'wpforms_frontend_output', [ $this, 'head' ], 5, 5 );
		add_action( 'wpforms_frontend_output', [ $this, 'fields' ], 10, 5 );
		add_action( 'wpforms_display_field_before', [ $this, 'field_container_open' ], 5, 2 );
		add_action( 'wpforms_display_field_before', [ $this, 'field_label' ], 15, 2 );
		add_action( 'wpforms_display_field_before', [ $this, 'field_description' ], 20, 2 );
		add_action( 'wpforms_display_field_after', [ $this, 'field_error' ], 3, 2 );
		add_action( 'wpforms_display_field_after', [ $this, 'field_description' ], 5, 2 );
		add_action( 'wpforms_display_field_after', [ $this, 'field_container_close' ], 15, 2 );
		add_action( 'wpforms_frontend_output', [ $this, 'recaptcha' ], 20, 5 );
		add_action( 'wpforms_frontend_output', [ $this, 'foot' ], 25, 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'assets_header' ] );
		add_action( 'wp_enqueue_scripts', [ $this, 'recaptcha_noconflict' ], 9999 );
		add_action( 'wp_footer', [ $this, 'missing_assets_error_js' ] );
		add_action( 'wp_footer', [ $this, 'assets_footer' ], 15 );
		add_action( 'wp_footer', [ $this, 'recaptcha_noconflict' ], 19 );
		add_action( 'wp_footer', [ $this, 'footer_end' ], 99 );

		// Register shortcode.
		add_shortcode( 'wpforms', [ $this, 'shortcode' ] );
	}

	/**
	 * Get the amp-state ID for a given form.
	 *
	 * @param int $form_id Form ID.
	 * @return string State ID.
	 */
	protected function get_form_amp_state_id( $form_id ) {
		return sprintf( 'wpforms_form_state_%d', $form_id );
	}

	/**
	 * Disable AMP if query param is detected.
	 *
	 * This allows the full form to be accessible for Pro users or sites
	 * that do not have SSL.
	 *
	 * @since 1.5.3
	 *
	 * @param bool $skip Skip AMP mode, display full post.
	 *
	 * @return bool
	 */
	public function amp_skip_post( $skip ) {

		return isset( $_GET['nonamp'] ) ? true : $skip;
	}

	/**
	 * Primary function to render a form on the frontend.
	 *
	 * @since 1.0.0
	 *
	 * @param int  $id Form ID.
	 * @param bool $title Whether to display form title.
	 * @param bool $description Whether to display form description.
	 */
	public function output( $id, $title = false, $description = false ) {

		if ( empty( $id ) ) {
			return;
		}

		// Grab the form data, if not found then we bail.
		$form = wpforms()->form->get( (int) $id );

		if ( empty( $form ) ) {
			return;
		}

		// Basic information.
		$form_data   = apply_filters( 'wpforms_frontend_form_data', wpforms_decode( $form->post_content ) );
		$form_id     = absint( $form->ID );
		$settings    = $form_data['settings'];
		$action      = esc_url_raw( remove_query_arg( 'wpforms' ) );
		$classes     = (int) wpforms_setting( 'disable-css', '1' ) === 1 ? [ 'wpforms-container-full' ] : [];
		$errors      = empty( wpforms()->process->errors[ $form_id ] ) ? array() : wpforms()->process->errors[ $form_id ];
		$title       = filter_var( $title, FILTER_VALIDATE_BOOLEAN );
		$description = filter_var( $description, FILTER_VALIDATE_BOOLEAN );

		// If the form does not contain any fields - do not proceed.
		if ( empty( $form_data['fields'] ) ) {
			echo '<!-- WPForms: no fields, form hidden -->';
			return;
		}

		// We need to stop output processing in case we are on AMP page.
		if ( wpforms_is_amp( false ) && ( ! current_theme_supports( 'amp' ) || apply_filters( 'wpforms_amp_pro', wpforms()->pro ) || ! is_ssl() || ! defined( 'AMP__VERSION' ) || version_compare( AMP__VERSION, '1.2', '<' ) ) ) {

			$text = apply_filters(
				'wpforms_frontend_shortcode_amp_text',
				sprintf(
					wp_kses(
						/* translators: %s - URL to a non-amp version of a page with the form. */
						__( '<a href="%s">Go to the full page</a> to view and submit the form.', 'wpforms-lite' ),
						array(
							'a' => array(
								'href' => array(),
							),
						)
					),
					esc_url( home_url( add_query_arg( 'nonamp', '1' ) . '#wpforms-' . absint( $form->ID ) ) )
				)
			);

			echo '<p class="wpforms-shortcode-amp-text">' . $text . '</p>';
			return;
		}

		// Add url query var wpforms_form_id to track post_max_size overflows.
		if ( in_array( 'file-upload', wp_list_pluck( $form_data['fields'], 'type' ), true ) ) {
			$action = add_query_arg( 'wpforms_form_id', $form_id, $action );
		}

		// Before output hook.
		do_action( 'wpforms_frontend_output_before', $form_data, $form );

		// Check for return hash.
		if (
			! empty( $_GET['wpforms_return'] ) &&
			wpforms()->process->valid_hash &&
			absint( wpforms()->process->form_data['id'] ) === $form_id
		) {
			do_action( 'wpforms_frontend_output_success', wpforms()->process->form_data, wpforms()->process->fields, wpforms()->process->entry_id );
			wpforms_debug_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		// Check for error-free completed form.
		if (
			empty( $errors ) &&
			! empty( $form_data ) &&
			! empty( $_POST['wpforms']['id'] ) &&
			absint( $_POST['wpforms']['id'] ) === $form_id
		) {
			do_action( 'wpforms_frontend_output_success', $form_data, false, false );
			wpforms_debug_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}

		// Allow filter to return early if some condition is not met.
		if ( ! apply_filters( 'wpforms_frontend_load', true, $form_data, null ) ) {
			do_action( 'wpforms_frontend_not_loaded', $form_data, $form );
			return;
		}

		// All checks have passed, so calculate multi-page details for the form.
		$pages = wpforms_get_pagebreak_details( $form_data );
		if ( $pages ) {
			$this->pages = $pages;
		} else {
			$this->pages = false;
		}

		// Allow final action to be customized - 3rd param ($form) has been deprecated.
		$action = apply_filters( 'wpforms_frontend_form_action', $action, $form_data, null );

		// Allow form container classes to be filtered and user defined classes.
		$classes = apply_filters( 'wpforms_frontend_container_class', $classes, $form_data );
		if ( ! empty( $settings['form_class'] ) ) {
			$classes = array_merge( $classes, explode( ' ', $settings['form_class'] ) );
		}
		$classes = wpforms_sanitize_classes( $classes, true );

		$form_classes = array( 'wpforms-validate', 'wpforms-form' );

		if ( ! empty( $form_data['settings']['ajax_submit'] ) && ! wpforms_is_amp() ) {
			$form_classes[] = 'wpforms-ajax-form';
		}

		$form_atts = array(
			'id'    => sprintf( 'wpforms-form-%d', absint( $form_id ) ),
			'class' => $form_classes,
			'data'  => array(
				'formid' => absint( $form_id ),
			),
			'atts'  => array(
				'method'  => 'post',
				'enctype' => 'multipart/form-data',
				'action'  => esc_url( $action ),
			),
		);

		if ( wpforms_is_amp() ) {

			// Set submitting state.
			if ( ! isset( $form_atts['atts']['on'] ) ) {
				$form_atts['atts']['on'] = '';
			} else {
				$form_atts['atts']['on'] .= ';';
			}
			$form_atts['atts']['on'] .= sprintf(
				'submit:AMP.setState( %1$s ); submit-success:AMP.setState( %2$s ); submit-error:AMP.setState( %2$s );',
				wp_json_encode(
					array(
						$this->get_form_amp_state_id( $form_id ) => array(
							'submitting' => true,
						),
					)
				),
				wp_json_encode(
					array(
						$this->get_form_amp_state_id( $form_id ) => array(
							'submitting' => false,
						),
					)
				)
			);

			// Upgrade the form to be an amp-form to avoid sanitizer conversion.
			if ( isset( $form_atts['atts']['action'] ) ) {
				$form_atts['atts']['action-xhr'] = $form_atts['atts']['action'];
				unset( $form_atts['atts']['action'] );

				$form_atts['atts']['verify-xhr'] = $form_atts['atts']['action-xhr'];
			}
		}

		$form_atts = apply_filters( 'wpforms_frontend_form_atts', $form_atts, $form_data );

		// Begin to build the output.
		do_action( 'wpforms_frontend_output_container_before', $form_data, $form );

		printf( '<div class="wpforms-container %s" id="wpforms-%d">', esc_attr( $classes ), absint( $form_id ) );

		do_action( 'wpforms_frontend_output_form_before', $form_data, $form );

		echo '<form ' . wpforms_html_attributes( $form_atts['id'], $form_atts['class'], $form_atts['data'], $form_atts['atts'] ) . '>';

		if ( wpforms_is_amp() ) {

			$state = array(
				'submitting' => false,
			);
			printf(
				'<amp-state id="%s"><script type="application/json">%s</script></amp-state>',
				$this->get_form_amp_state_id( $form_id ),
				wp_json_encode( $state )
			);
		}


		do_action( 'wpforms_frontend_output', $form_data, null, $title, $description, $errors );

		echo '</form>';

		do_action( 'wpforms_frontend_output_form_after', $form_data, $form );

		echo '</div>  <!-- .wpforms-container -->';

		do_action( 'wpforms_frontend_output_container_after', $form_data, $form );

		// Add form to class property that tracks all forms in a page.
		$this->forms[ $form_id ] = $form_data;

		// Optional debug information if WPFORMS_DEBUG is defined.
		wpforms_debug_data( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing

		// After output hook.
		do_action( 'wpforms_frontend_output_after', $form_data, $form );
	}

	/**
	 * Display form confirmation message.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form_data Form data and settings.
	 * @param array $fields    Sanitized field data.
	 * @param int   $entry_id  Entry id.
	 */
	public function confirmation( $form_data, $fields = array(), $entry_id = 0 ) {
		$class = intval( wpforms_setting( 'disable-css', '1' ) ) === 1 ? 'wpforms-confirmation-container-full' : 'wpforms-confirmation-container';

		// In AMP, just print template.
		if ( wpforms_is_amp() ) {
			$this->assets_confirmation();
			printf( '<div submit-success><template type="amp-mustache"><div class="%s {{#redirecting}}wpforms-redirection-message{{/redirecting}}">{{{message}}}</div></template></div>', esc_attr( $class ) );
			return;
		}

		if ( empty( $fields ) ) {
			$fields = ! empty( $_POST['wpforms']['complete'] ) ? $_POST['wpforms']['complete'] : array();
		}

		if ( empty( $entry_id ) ) {
			$entry_id = ! empty( $_POST['wpforms']['entry_id'] ) ? $_POST['wpforms']['entry_id'] : 0;
		}

		$confirmation         = wpforms()->get( 'process' )->get_current_confirmation();
		$confirmation_message = wpforms()->get( 'process' )->get_confirmation_message( $form_data, $fields, $entry_id );

		// Only display if a confirmation message has been configured.
		if ( empty( $confirmation ) || empty( $confirmation_message ) ) {
			return;
		}

		// Load confirmation specific assets.
		$this->assets_confirmation();

		/**
		 * Fires once before the confirmation message.
		 *
		 * @since 1.6.9
		 *
		 * @param array $confirmation Current confirmation data.
		 * @param array $form_data    Form data and settings.
		 * @param array $fields       Sanitized field data.
		 * @param int   $entry_id     Entry id.
		 */
		do_action( 'wpforms_frontend_confirmation_message_before', $confirmation, $form_data, $fields, $entry_id );

		$class .= $this->confirmation_message_scroll ? ' wpforms-confirmation-scroll' : '';

		printf(
			'<div class="%s" id="wpforms-confirmation-%d">%s</div>',
			$class,
			absint( $form_data['id'] ),
			$confirmation_message
		);

		/**
		 * Fires once after the confirmation message.
		 *
		 * @since 1.6.9
		 *
		 * @param array $confirmation Current confirmation data.
		 * @param array $form_data    Form data and settings.
		 * @param array $fields       Sanitized field data.
		 * @param int   $entry_id     Entry id.
		 */
		do_action( 'wpforms_frontend_confirmation_message_after', $confirmation, $form_data, $fields, $entry_id );
	}

	/**
	 * Form head area, for displaying form title and description if enabled.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form_data   Form data and settings.
	 * @param null  $deprecated  Deprecated in v1.3.7, previously was $form object.
	 * @param bool  $title       Whether to display form title.
	 * @param bool  $description Whether to display form description.
	 * @param array $errors      List of all errors filled in WPForms_Process::process().
	 */
	public function head( $form_data, $deprecated, $title, $description, $errors ) {

		$settings = $form_data['settings'];

		// Output title and/or description.
		if ( true === $title || true === $description ) {
			echo '<div class="wpforms-head-container">';

				if ( true === $title && ! empty( $settings['form_title'] ) ) {
					echo '<div class="wpforms-title">' . esc_html( $settings['form_title'] ) . '</div>';
				}

				if ( true === $description && ! empty( $settings['form_desc'] ) ) {
					echo '<div class="wpforms-description">';
					// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					echo apply_filters( 'wpforms_process_smart_tags', $settings['form_desc'], $form_data );
					echo '</div>';
				}

			echo '</div>';
		}

		// Output <noscript> error message.
		$noscript_msg = apply_filters( 'wpforms_frontend_noscript_error_message', __( 'Please enable JavaScript in your browser to complete this form.', 'wpforms-lite' ), $form_data );
		if ( ! empty( $noscript_msg ) && ! empty( $form_data['fields'] ) && ! wpforms_is_amp() ) {
			echo '<noscript class="wpforms-error-noscript">' . esc_html( $noscript_msg ) . '</noscript>';
		}

		// Output header errors if they exist.
		if ( ! empty( $errors['header'] ) ) {
			$this->form_error( 'header', $errors['header'] );
		}
	}

	/**
	 * Form field area.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form_data   Form data and settings.
	 * @param null  $deprecated  Deprecated in v1.3.7, previously was $form object.
	 * @param bool  $title       Whether to display form title.
	 * @param bool  $description Whether to display form description.
	 * @param array $errors      List of all errors filled in WPForms_Process::process().
	 */
	public function fields( $form_data, $deprecated, $title, $description, $errors ) {

		// Obviously we need to have form fields to proceed.
		if ( empty( $form_data['fields'] ) ) {
			return;
		}

		// Form fields area.
		echo '<div class="wpforms-field-container">';

			/**
			 * Core actions on this hook:
			 * Priority / Description
			 * 20         Pagebreak markup (open first page)
			 */
			do_action( 'wpforms_display_fields_before', $form_data );

			// Loop through all the fields we have.
			foreach ( $form_data['fields'] as $field ) :
				if ( ! has_action( "wpforms_display_field_{$field['type']}" ) ) {
					continue;
				}

				/**
				 *
				 * Modify Field before render.
				 *
				 * @since 1.4.0
				 *
				 * @param array $field Current field.
				 * @param array $form_data Form data and settings.
				 */
				$field = apply_filters( 'wpforms_field_data', $field, $form_data );

				if ( empty( $field ) ) {
					continue;
				}

				// Get field attributes. Deprecated; Customizations should use
				// field properties instead.
				$attributes = $this->get_field_attributes( $field, $form_data );

				// Add properties to the field so it's available everywhere.
				$field['properties'] = $this->get_field_properties( $field, $form_data, $attributes );

				/**
				 * Core actions on this hook:
				 * Priority / Description
				 * 5          Field opening container markup.
				 * 15         Field label.
				 * 20         Field description (depending on position).
				 */
				do_action( 'wpforms_display_field_before', $field, $form_data );

				/**
				 * Individual field classes use this hook to display the actual
				 * field form elements.
				 * See `field_display` methods in /includes/fields.
				 */
				do_action( "wpforms_display_field_{$field['type']}", $field, $attributes, $form_data );

				/**
				 * Core actions on this hook:
				 * Priority / Description
				 * 3          Field error messages.
				 * 5          Field description (depending on position).
				 * 15         Field closing container markup.
				 * 20         Pagebreak markup (close previous page, open next)
				 */
				do_action( 'wpforms_display_field_after', $field, $form_data );

			endforeach;

			/**
			 * Core actions on this hook:
			 * Priority / Description
			 * 5          Pagebreak markup (close last page)
			 */
			do_action( 'wpforms_display_fields_after', $form_data );

		echo '</div>';
	}

	/**
	 * Return base attributes for a specific field. This is deprecated and
	 * exists for backwards-compatibility purposes. Use field properties instead.
	 *
	 * @since 1.3.7
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 *
	 * @return array
	 */
	public function get_field_attributes( $field, $form_data ) {

		$form_id    = absint( $form_data['id'] );
		$field_id   = absint( $field['id'] );
		$attributes = array(
			'field_class'       => array( 'wpforms-field', 'wpforms-field-' . sanitize_html_class( $field['type'] ) ),
			'field_id'          => array( sprintf( 'wpforms-%d-field_%d-container', $form_id, $field_id ) ),
			'field_style'       => '',
			'label_class'       => array( 'wpforms-field-label' ),
			'label_id'          => '',
			'description_class' => array( 'wpforms-field-description' ),
			'description_id'    => array(),
			'input_id'          => array( sprintf( 'wpforms-%d-field_%d', $form_id, $field_id ) ),
			'input_class'       => array(),
			'input_data'        => array(),
		);

		// Check user field defined classes.
		if ( ! empty( $field['css'] ) ) {
			$attributes['field_class'] = array_merge( $attributes['field_class'], wpforms_sanitize_classes( $field['css'], true ) );
		}
		// Check for input column layouts.
		if ( ! empty( $field['input_columns'] ) ) {
			if ( '2' === $field['input_columns'] ) {
				$attributes['field_class'][] = 'wpforms-list-2-columns';
			} elseif ( '3' === $field['input_columns'] ) {
				$attributes['field_class'][] = 'wpforms-list-3-columns';
			} elseif ( 'inline' === $field['input_columns'] ) {
				$attributes['field_class'][] = 'wpforms-list-inline';
			}
		}
		// Check label visibility.
		if ( ! empty( $field['label_hide'] ) ) {
			$attributes['label_class'][] = 'wpforms-label-hide';
		}
		// Check size.
		if ( ! empty( $field['size'] ) ) {
			$attributes['input_class'][] = 'wpforms-field-' . sanitize_html_class( $field['size'] );
		}
		// Check if required.
		if ( ! empty( $field['required'] ) ) {
			$attributes['input_class'][] = 'wpforms-field-required';
		}

		// Check if there are errors.
		if ( ! empty( wpforms()->process->errors[ $form_id ][ $field_id ] ) ) {
			$attributes['input_class'][] = 'wpforms-error';
		}

		// This filter is deprecated, filter the properties (below) instead.
		$attributes = apply_filters( 'wpforms_field_atts', $attributes, $field, $form_data );

		return $attributes;
	}

	/**
	 * Return base properties for a specific field.
	 *
	 * @since 1.3.7
	 *
	 * @param array $field      Field data and settings.
	 * @param array $form_data  Form data and settings.
	 * @param array $attributes List of field attributes.
	 *
	 * @return array
	 */
	public function get_field_properties( $field, $form_data, $attributes = array() ) {

		if ( empty( $attributes ) ) {
			$attributes = $this->get_field_attributes( $field, $form_data );
		}

		// This filter is for backwards compatibility purposes.
		$types = array( 'text', 'textarea', 'name', 'number', 'email', 'hidden', 'url', 'html', 'divider', 'password', 'phone', 'address', 'select', 'checkbox', 'radio' );
		if ( in_array( $field['type'], $types, true ) ) {
			$field = apply_filters( "wpforms_{$field['type']}_field_display", $field, $attributes, $form_data );
		} elseif ( 'credit-card' === $field['type'] ) {
			$field = apply_filters( 'wpforms_creditcard_field_display', $field, $attributes, $form_data );
		} elseif ( in_array( $field['type'], array( 'payment-multiple', 'payment-single', 'payment-checkbox' ), true ) ) {
			$filter_field_type = str_replace( '-', '_', $field['type'] );
			$field             = apply_filters( 'wpforms_' . $filter_field_type . '_field_display', $field, $attributes, $form_data );
		}

		$form_id  = absint( $form_data['id'] );
		$field_id = absint( $field['id'] );
		$error    = ! empty( wpforms()->process->errors[ $form_id ][ $field_id ] ) ? wpforms()->process->errors[ $form_id ][ $field_id ] : '';

		$properties = array(
			'container'   => array(
				'attr'  => array(
					'style' => $attributes['field_style'],
				),
				'class' => $attributes['field_class'],
				'data'  => array(),
				'id'    => implode( '', array_slice( $attributes['field_id'], 0 ) ),
			),
			'label'       => array(
				'attr'     => array(
					'for' => sprintf( 'wpforms-%d-field_%d', $form_id, $field_id ),
				),
				'class'    => $attributes['label_class'],
				'data'     => array(),
				'disabled' => ! empty( $field['label_disable'] ) ? true : false,
				'hidden'   => ! empty( $field['label_hide'] ) ? true : false,
				'id'       => $attributes['label_id'],
				'required' => ! empty( $field['required'] ) ? true : false,
				'value'    => ! empty( $field['label'] ) ? $field['label'] : '',
			),
			'inputs'      => array(
				'primary' => array(
					'attr'     => array(
						'name'        => "wpforms[fields][{$field_id}]",
						'value'       => isset( $field['default_value'] ) ? apply_filters( 'wpforms_process_smart_tags', $field['default_value'], $form_data ) : '',
						'placeholder' => isset( $field['placeholder'] ) ? $field['placeholder'] : '',
					),
					'class'    => $attributes['input_class'],
					'data'     => $attributes['input_data'],
					'id'       => implode( array_slice( $attributes['input_id'], 0 ) ),
					'required' => ! empty( $field['required'] ) ? 'required' : '',
				),
			),
			'error'       => array(
				'attr'  => array(
					'for' => sprintf( 'wpforms-%d-field_%d', $form_id, $field_id ),
				),
				'class' => array( 'wpforms-error' ),
				'data'  => array(),
				'id'    => '',
				'value' => $error,
			),
			'description' => array(
				'attr'     => array(),
				'class'    => $attributes['description_class'],
				'data'     => array(),
				'id'       => implode( '', array_slice( $attributes['description_id'], 0 ) ),
				'position' => 'after',
				'value'    => ! empty( $field['description'] ) ? apply_filters( 'wpforms_process_smart_tags', $field['description'], $form_data ) : '',
			),
		);

		$properties = apply_filters( "wpforms_field_properties_{$field['type']}", $properties, $field, $form_data );
		$properties = apply_filters( 'wpforms_field_properties', $properties, $field, $form_data );

		return $properties;
	}

	/**
	 * Display the opening container markup for each field.
	 *
	 * @since 1.3.7
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 */
	public function field_container_open( $field, $form_data ) {

		$container                     = $field['properties']['container'];
		$container['data']['field-id'] = absint( $field['id'] );

		printf(
			'<div %s>',
			wpforms_html_attributes( $container['id'], $container['class'], $container['data'], $container['attr'] )
		);
	}

	/**
	 * Display the label for each field.
	 *
	 * @since 1.3.7
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 */
	public function field_label( $field, $form_data ) {

		$label = $field['properties']['label'];

		// If the label is empty or disabled don't proceed.
		if ( empty( $label['value'] ) || $label['disabled'] ) {
			return;
		}

		$required = $label['required'] ? wpforms_get_field_required_label() : '';

		printf( '<label %s>%s%s</label>',
			wpforms_html_attributes( $label['id'], $label['class'], $label['data'], $label['attr'] ),
			esc_html( $label['value'] ),
			$required
		);
	}

	/**
	 * Display any errors for each field.
	 *
	 * @since 1.3.7
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 */
	public function field_error( $field, $form_data ) {

		$error = $field['properties']['error'];

		// If there are no errors don't proceed.
		// Advanced fields with multiple inputs (address, name, etc) errors
		// will be an array and are handled within the respective field class.
		if ( empty( $error['value'] ) || is_array( $error['value'] ) ) {
			return;
		}

		printf( '<label %s>%s</label>',
			wpforms_html_attributes( $error['id'], $error['class'], $error['data'], $error['attr'] ),
			esc_html( $error['value'] )
		);
	}

	/**
	 * Display the description for each field.
	 *
	 * @since 1.3.7
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 */
	public function field_description( $field, $form_data ) {

		$action      = current_action();
		$description = $field['properties']['description'];

		// If the description is empty don't proceed.
		if ( empty( $description['value'] ) ) {
			return;
		}

		// Determine positioning.
		if ( 'wpforms_display_field_before' === $action && 'before' !== $description['position'] ) {
			return;
		}
		if ( 'wpforms_display_field_after' === $action && 'after' !== $description['position'] ) {
			return;
		}

		if ( 'before' === $description['position'] ) {
			$description['class'][] = 'before';
		}

		printf( '<div %s>%s</div>',
			wpforms_html_attributes( $description['id'], $description['class'], $description['data'], $description['attr'] ),
			do_shortcode( $description['value'] )
		);
	}

	/**
	 * Display the closing container markup for each field.
	 *
	 * @since 1.3.7
	 *
	 * @param array $field     Field data and settings.
	 * @param array $form_data Form data and settings.
	 */
	public function field_container_close( $field, $form_data ) {

		echo '</div>';
	}

	/**
	 * Anti-spam honeypot output if configured.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form_data   Form data and settings.
	 * @param null  $deprecated  Deprecated in v1.3.7, previously was $form object.
	 * @param bool  $title       Whether to display form title.
	 * @param bool  $description Whether to display form description.
	 * @param array $errors      List of all errors filled in WPForms_Process::process().
	 */
	public function honeypot( $form_data, $deprecated, $title, $description, $errors ) {

		if (
			empty( $form_data['settings']['honeypot'] ) ||
			'1' !== $form_data['settings']['honeypot']
		) {
			return;
		}

		$names = array( 'Name', 'Phone', 'Comment', 'Message', 'Email', 'Website' );

		echo '<div class="wpforms-field wpforms-field-hp">';

			echo '<label for="wpforms-' . $form_data['id'] . '-field-hp" class="wpforms-field-label">' . $names[ array_rand( $names ) ] . '</label>'; // phpcs:ignore

			echo '<input type="text" name="wpforms[hp]" id="wpforms-' . $form_data['id'] . '-field-hp" class="wpforms-field-medium">';  // phpcs:ignore

		echo '</div>';
	}

	/**
	 * CAPTCHA output if configured.
	 *
	 * @since 1.0.0
	 * @since 1.6.4 Added hCaptcha support.
	 *
	 * @param array $form_data   Form data and settings.
	 * @param null  $deprecated  Deprecated in v1.3.7, previously was $form object.
	 * @param bool  $title       Whether to display form title.
	 * @param bool  $description Whether to display form description.
	 * @param array $errors      List of all errors filled in WPForms_Process::process().
	 */
	public function recaptcha( $form_data, $deprecated, $title, $description, $errors ) {

		// Check that CAPTCHA is configured in the settings.
		$captcha_settings = wpforms_get_captcha_settings();
		if (
			empty( $captcha_settings['provider'] ) ||
			'none' === $captcha_settings['provider'] ||
			empty( $captcha_settings['site_key'] ) ||
			empty( $captcha_settings['secret_key'] )
		) {
			return;
		}

		// Check that the CAPTCHA is configured for the specific form.
		if (
			! isset( $form_data['settings']['recaptcha'] ) ||
			'1' !== $form_data['settings']['recaptcha']
		) {
			return;
		}

		$is_recaptcha_v3 = 'recaptcha' === $captcha_settings['provider'] && 'v3' === $captcha_settings['recaptcha_type'];

		if ( wpforms_is_amp() ) {
			if ( $is_recaptcha_v3 ) {
				printf(
					'<amp-recaptcha-input name="wpforms[recaptcha]" data-sitekey="%s" data-action="%s" layout="nodisplay"></amp-recaptcha-input>',
					esc_attr( $captcha_settings['site_key'] ),
					esc_attr( 'wpforms_' . $form_data['id'] )
				);
			} elseif ( is_super_admin() ) {
				$captcha_provider = 'hcaptcha' === $captcha_settings['provider'] ? esc_html__( 'hCaptcha', 'wpforms-lite' ) : esc_html__( 'Google reCAPTCHA v2', 'wpforms-lite' );
				echo '<div class="wpforms-notice wpforms-warning" style="margin: 20px 0;">';
				printf(
					wp_kses(
						/* translators: %1$s - CAPTCHA provider name; %2$s - URL to reCAPTCHA documentation. */
						__( '%1$s is not supported by AMP and is currently disabled.<br><a href="%2$s" rel="noopener noreferrer" target="_blank">Upgrade to reCAPTCHA v3</a> for full AMP support. <br><em>Please note: this message is only displayed to site administrators.</em>', 'wpforms-lite' ),
						array(
							'a'  => array(
								'href'   => array(),
								'rel'    => array(),
								'target' => array(),
							),
							'br' => array(),
							'em' => array(),
						)
					),
					$captcha_provider, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
					'https://wpforms.com/docs/setup-captcha-wpforms/'
				);
				echo '</div>';
			}
			return; // Only v3 is supported in AMP.
		}

		if ( $is_recaptcha_v3 ) {
			echo '<input type="hidden" name="wpforms[recaptcha]" value="">';
			return;
		}

		$visible = $this->pages ? 'style="display:none;"' : '';
		$data    = array(
			'sitekey' => $captcha_settings['site_key'],
		);
		$data    = apply_filters( 'wpforms_frontend_recaptcha', $data, $form_data );

		if ( $captcha_settings['provider'] === 'recaptcha' && $captcha_settings['recaptcha_type'] === 'invisible' ) {
			$data['size'] = 'invisible';
		}

		echo '<div class="wpforms-recaptcha-container wpforms-is-' . $captcha_settings['provider'] . '" ' . $visible . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

		echo '<div ' . wpforms_html_attributes( '', array( 'g-recaptcha' ), $data ) . '></div>';

		if ( $captcha_settings['provider'] === 'hcaptcha' || $captcha_settings['recaptcha_type'] !== 'invisible' ) {
			echo '<input type="text" name="g-recaptcha-hidden" class="wpforms-recaptcha-hidden" style="position:absolute!important;clip:rect(0,0,0,0)!important;height:1px!important;width:1px!important;border:0!important;overflow:hidden!important;padding:0!important;margin:0!important;" required>';
		}

		if ( ! empty( $errors['recaptcha'] ) ) {
			$this->form_error( 'recaptcha', $errors['recaptcha'] );
		}

		echo '</div>';
	}

	/**
	 * Form footer area.
	 *
	 * @since 1.0.0
	 *
	 * @param array $form_data   Form data and settings.
	 * @param null  $deprecated  Deprecated in v1.3.7, previously was $form object.
	 * @param bool  $title       Whether to display form title.
	 * @param bool  $description Whether to display form description.
	 * @param array $errors      List of all errors filled in WPForms_Process::process().
	 */
	public function foot( $form_data, $deprecated, $title, $description, $errors ) {

		$form_id  = absint( $form_data['id'] );
		$settings = $form_data['settings'];
		$submit   = apply_filters( 'wpforms_field_submit', $settings['submit_text'], $form_data );
		$process  = 'aria-live="assertive" ';
		$classes  = '';
		$visible  = $this->pages ? 'style="display:none;"' : '';

		// Check for submit button alt-text.
		if ( ! empty( $settings['submit_text_processing'] ) ) {
			if ( wpforms_is_amp() ) {
				$bound_text = sprintf(
					'%s.submitting ? %s : %s',
					$this->get_form_amp_state_id( $form_id ),
					wp_json_encode( $settings['submit_text_processing'], JSON_UNESCAPED_UNICODE ),
					wp_json_encode( $submit, JSON_UNESCAPED_UNICODE )
				);
				$process   .= '[text]="' . esc_attr( $bound_text ) . '"';
			} else {
				$process .= 'data-alt-text="' . esc_attr( $settings['submit_text_processing'] ) . '" data-submit-text="' . esc_attr( $submit ) . '"';
			}
		}

		// Check user defined submit button classes.
		if ( ! empty( $settings['submit_class'] ) ) {
			$classes = wpforms_sanitize_classes( $settings['submit_class'] );
		}

		// AMP submit error template.
		if ( wpforms_is_amp() ) {
			echo '<div submit-error><template type="amp-mustache"><div class="wpforms-error-container"><p>{{{message}}}</p></div></template></div>';
		}

		// Output footer errors if they exist.
		if ( ! empty( $errors['footer'] ) ) {
			$this->form_error( 'footer', $errors['footer'] );
		}

		// Submit button area.
		echo '<div class="wpforms-submit-container" ' . $visible . '>';

				echo '<input type="hidden" name="wpforms[id]" value="' . esc_attr( $form_id ) . '">';

				echo '<input type="hidden" name="wpforms[author]" value="' . absint( get_the_author_meta( 'ID' ) ) . '">';

				if ( is_singular() ) {
					echo '<input type="hidden" name="wpforms[post_id]" value="' . esc_attr( get_the_ID() ) . '">';
				}

				do_action( 'wpforms_display_submit_before', $form_data );

				printf(
					'<button type="submit" name="wpforms[submit]" class="wpforms-submit %s" id="wpforms-submit-%d" value="wpforms-submit" %s>%s</button>',
					esc_attr( $classes ),
					esc_attr( $form_id ),
					$process,
					esc_html( $submit )
				);

				if ( ! empty( $settings['ajax_submit'] ) && ! wpforms_is_amp() ) {

					/**
					 * Filter submit spinner image src attribute.
					 *
					 * @since      1.5.4.1
					 * @deprecated 1.6.7.3
					 *
					 * @param string $src       Spinner image source.
					 * @param array  $form_data Form data and settings.
					 */
					$src = apply_filters_deprecated(
						'wpforms_display_sumbit_spinner_src',
						[
							WPFORMS_PLUGIN_URL . 'assets/images/submit-spin.svg',
							$form_data,
						],
						'1.6.7.3',
						'wpforms_display_submit_spinner_src'
					);

					/**
					 * Filter submit spinner image src attribute.
					 *
					 * @since 1.6.7.3
					 *
					 * @param string $src       Spinner image source.
					 * @param array  $form_data Form data and settings.
					 */
					$src = apply_filters(
						'wpforms_display_submit_spinner_src',
						$src,
						$form_data
					);

					printf(
						'<img src="%s" class="wpforms-submit-spinner" style="display: none;" width="26" height="26" alt="">',
						esc_url( $src )
					);
				}

				do_action( 'wpforms_display_submit_after', $form_data );

		echo '</div>';

		// Load the success template in AMP.
		if ( wpforms_is_amp() ) {
			$this->confirmation( $form_data, $form_data['fields'] );
		}
	}

	/**
	 * Display form error.
	 *
	 * @since 1.5.3
	 *
	 * @param string $type  Error type.
	 * @param string $error Error text.
	 */
	public function form_error( $type, $error ) {

		switch ( $type ) {
			case 'header':
			case 'footer':
				echo '<div class="wpforms-error-container">' . wpforms_sanitize_error( $error ) . '</div>';
				break;
			case 'recaptcha':
				echo '<label id="wpforms-field_recaptcha-error" class="wpforms-error">' . wpforms_sanitize_error( $error ) . '</label>';
				break;
		}
	}

	/**
	 * Determine if we should load assets globally.
	 * If false assets will load conditionally (default).
	 *
	 * @since 1.2.4
	 *
	 * @return bool
	 */
	public function assets_global() {

		return apply_filters( 'wpforms_global_assets', wpforms_setting( 'global-assets', false ) );
	}

	/**
	 * Load the necessary CSS for single pages/posts earlier if possible.
	 *
	 * If we are viewing a singular page, then we can check the content early
	 * to see if the shortcode was used. If not we fallback and load the assets
	 * later on during the page (widgets, archives, etc).
	 *
	 * @since 1.0.0
	 */
	public function assets_header() {

		if ( ! is_singular() ) {
			return;
		}

		global $post;

		if (
			has_shortcode( $post->post_content, 'wpforms' ) ||
			( function_exists( 'has_block' ) && has_block( 'wpforms/form-selector' ) )
		) {
			$this->assets_css();
		}
	}

	/**
	 * Load the CSS assets for frontend output.
	 *
	 * @since 1.0.0
	 */
	public function assets_css() {

		do_action( 'wpforms_frontend_css', $this->forms );

		$min = wpforms_get_min_suffix();

		// jQuery date/time library CSS.
		if (
			$this->assets_global() ||
			true === wpforms_has_field_type( 'date-time', $this->forms, true )
		) {
			wp_enqueue_style(
				'wpforms-jquery-timepicker',
				WPFORMS_PLUGIN_URL . 'assets/css/jquery.timepicker.css',
				[],
				'1.11.5'
			);
			wp_enqueue_style(
				'wpforms-flatpickr',
				WPFORMS_PLUGIN_URL . 'assets/css/flatpickr.min.css',
				[],
				'4.6.9'
			);
		}

		// Load CSS per global setting.
		if ( (int) wpforms_setting( 'disable-css', '1' ) === 1 ) {
			wp_enqueue_style(
				'wpforms-full',
				WPFORMS_PLUGIN_URL . "assets/css/wpforms-full{$min}.css",
				[],
				WPFORMS_VERSION
			);
		}
		if ( (int) wpforms_setting( 'disable-css', '1' ) === 2 ) {
			wp_enqueue_style(
				'wpforms-base',
				WPFORMS_PLUGIN_URL . "assets/css/wpforms-base{$min}.css",
				[],
				WPFORMS_VERSION
			);
		}
	}

	/**
	 * Load the JS assets for frontend output.
	 *
	 * @since 1.0.0
	 */
	public function assets_js() {

		if ( wpforms_is_amp() ) {
			return;
		}

		do_action( 'wpforms_frontend_js', $this->forms );

		$min = wpforms_get_min_suffix();

		// Load jQuery validation library - https://jqueryvalidation.org/.
		wp_enqueue_script(
			'wpforms-validation',
			WPFORMS_PLUGIN_URL . 'assets/js/jquery.validate.min.js',
			[ 'jquery' ],
			'1.19.3',
			true
		);

		// Load jQuery date/time libraries.
		// TODO: should be moved out of here.
		if (
			$this->assets_global() ||
			true === wpforms_has_field_type( 'date-time', $this->forms, true )
		) {
			wp_enqueue_script(
				'wpforms-flatpickr',
				WPFORMS_PLUGIN_URL . 'assets/js/flatpickr.min.js',
				[ 'jquery' ],
				'4.6.9',
				true
			);
			wp_enqueue_script(
				'wpforms-jquery-timepicker',
				WPFORMS_PLUGIN_URL . 'assets/js/jquery.timepicker.min.js',
				[ 'jquery' ],
				'1.11.5',
				true
			);
		}

		// Load jQuery input mask library - https://github.com/RobinHerbots/jquery.inputmask.
		if (
			$this->assets_global() ||
			true === wpforms_has_field_type( [ 'phone', 'address' ], $this->forms, true ) ||
			true === wpforms_has_field_setting( 'input_mask', $this->forms, true )
		) {
			wp_enqueue_script(
				'wpforms-maskedinput',
				WPFORMS_PLUGIN_URL . 'assets/js/jquery.inputmask.min.js',
				[ 'jquery' ],
				'5.0.6',
				true
			);
		}

		// Load mailcheck <https://github.com/mailcheck/mailcheck> and punycode libraries.
		if (
			$this->assets_global() ||
			true === wpforms_has_field_type( [ 'email' ], $this->forms, true )
		) {
			wp_enqueue_script(
				'wpforms-mailcheck',
				WPFORMS_PLUGIN_URL . 'assets/js/mailcheck.min.js',
				false,
				'1.1.2',
				true
			);

			wp_enqueue_script(
				'wpforms-punycode',
				WPFORMS_PLUGIN_URL . "assets/js/punycode{$min}.js",
				[],
				'1.0.0',
				true
			);
		}

		// Load CC payment library - https://github.com/stripe/jquery.payment/.
		// TODO: should be moved out of here.
		if (
			$this->assets_global() ||
			true === wpforms_has_field_type( 'credit-card', $this->forms, true )
		) {
			wp_enqueue_script(
				'wpforms-payment',
				WPFORMS_PLUGIN_URL . 'assets/js/jquery.payment.min.js',
				[ 'jquery' ],
				WPFORMS_VERSION,
				true
			);
		}

		// Load base JS.
		wp_enqueue_script(
			'wpforms',
			WPFORMS_PLUGIN_URL . 'assets/js/wpforms.js',
			[ 'jquery' ],
			WPFORMS_VERSION,
			true
		);

		$this->assets_recaptcha();
	}

	/**
	 * Load the assets needed for the CAPTCHA.
	 *
	 * @since 1.6.2
	 * @since 1.6.4 Added hCaptcha support.
	 */
	public function assets_recaptcha() {

		// Kill switch for CAPTCHA.
		if ( (bool) apply_filters( 'wpforms_frontend_recaptcha_disable', false ) ) {
			return;
		}

		// Load CAPTCHA support if form supports it.
		$captcha_settings = wpforms_get_captcha_settings();

		if (
			empty( $captcha_settings['provider'] ) ||
			'none' === $captcha_settings['provider'] ||
			empty( $captcha_settings['site_key'] ) ||
			empty( $captcha_settings['secret_key'] )
		) {
			return;
		}

		// Whether at least 1 form on a page has CAPTCHA enabled.
		$captcha = false;

		foreach ( $this->forms as $form ) {
			if ( ! empty( $form['settings']['recaptcha'] ) ) {
				$captcha = true;

				break;
			}
		}

		// Return early.
		if ( ! $captcha && ! $this->assets_global() ) {
			return;
		}

		$is_recaptcha_v3 = 'recaptcha' === $captcha_settings['provider'] && 'v3' === $captcha_settings['recaptcha_type'];
		$captcha_api     = 'hcaptcha' === $captcha_settings['provider'] ? 'https://hcaptcha.com/1/api.js?onload=wpformsRecaptchaLoad&render=explicit' : apply_filters( 'wpforms_frontend_recaptcha_url', 'https://www.google.com/recaptcha/api.js?onload=wpformsRecaptchaLoad&render=explicit' ); // BC: reCAPTCHA v3 don't filtered.
		$captcha_api     = $is_recaptcha_v3 ? 'https://www.google.com/recaptcha/api.js?render=' . $captcha_settings['site_key'] : $captcha_api;

		/**
		 * Filter the CAPTCHA API URL.
		 *
		 * @since 1.6.4
		 *
		 * @param string $captcha_api The CAPTCHA API URL.
		 */
		$captcha_api = apply_filters( 'wpforms_frontend_captcha_api', $captcha_api );

		wp_enqueue_script(
			'wpforms-recaptcha',
			$captcha_api,
			$is_recaptcha_v3 ? [] : [ 'jquery' ],
			null,
			true
		);

		/**
		 * Filter the string containing the CAPTCHA javascript to be added.
		 *
		 * @since 1.6.4
		 *
		 * @param string $captcha_inline The CAPTCHA javascript.
		 */
		$captcha_inline = apply_filters( 'wpforms_frontend_captcha_inline_script', $this->get_captcha_inline_script( $captcha_settings ) );

		wp_add_inline_script( 'wpforms-recaptcha', $captcha_inline );
	}

	/**
	 * Retrieve the string containing the CAPTCHA inline javascript.
	 *
	 * @since 1.6.4
	 *
	 * @param array $captcha_settings The CAPTCHA settings.
	 *
	 * @return string
	 */
	protected function get_captcha_inline_script( $captcha_settings ) {

		// IE11 polyfills for native `matches()` and `closest()` methods.
		$polyfills = // language=JavaScript PhpStorm.
			'if (!Element.prototype.matches) {
			    Element.prototype.matches = Element.prototype.msMatchesSelector || Element.prototype.webkitMatchesSelector;
			}
			if (!Element.prototype.closest) {
			    Element.prototype.closest = function (s) {
			        var el = this;
			        do {
			            if (Element.prototype.matches.call(el, s)) { return el; }
			            el = el.parentElement || el.parentNode;
			        } while (el !== null && el.nodeType === 1);
			        return null;
			    };
			}
		';

		// Native equivalent for jQuery's `trigger()` method.
		$dispatch = // language=JavaScript PhpStorm.
			'var wpformsDispatchEvent = function (el, ev, custom) {
			    var e = document.createEvent(custom ? "CustomEvent" : "HTMLEvents");
			    custom ? e.initCustomEvent(ev, true, true, false) : e.initEvent(ev, true, true);
			    el.dispatchEvent(e);
			};
		';

		// Captcha callback, used by hCaptcha and checkbox reCaptcha v2.
		$callback = // language=JavaScript PhpStorm.
			'var wpformsRecaptchaCallback = function (el) {
			    var hdn = el.parentNode.querySelector(".wpforms-recaptcha-hidden");
			    var err = el.parentNode.querySelector("#g-recaptcha-hidden-error");
			    hdn.value = "1";
			    wpformsDispatchEvent(hdn, "change", false);
			    hdn.classList.remove("wpforms-error");
			    err && hdn.parentNode.removeChild(err);
			};
		';

		if ( $captcha_settings['provider'] === 'hcaptcha' ) {

			$data  = $dispatch;
			$data .= $callback;

			$data .= // language=JavaScript PhpStorm.
				'var wpformsRecaptchaLoad = function () {
				    Array.prototype.forEach.call(document.querySelectorAll(".g-recaptcha"), function (el) {
				        var captchaID = hcaptcha.render(el, {
				            callback: function () {
				                wpformsRecaptchaCallback(el);
				            }
				        });
				        el.setAttribute("data-recaptcha-id", captchaID);
				    });
				    wpformsDispatchEvent(document, "wpformsRecaptchaLoaded", true);
				};
			';

			return $data;
		}

		if ( $captcha_settings['recaptcha_type'] === 'v3' ) {

			$data = $dispatch;

			$data .= // language=JavaScript PhpStorm.
				'var wpformsRecaptchaLoad = function () {
				    grecaptcha.execute("' . $captcha_settings['site_key'] . '", {action: "wpforms"}).then(function (token) {
				        Array.prototype.forEach.call(document.getElementsByName("wpforms[recaptcha]"), function (el) {
				            el.value = token;
				        });
				    });
				    wpformsDispatchEvent(document, "wpformsRecaptchaLoaded", true);
				};
				grecaptcha.ready(wpformsRecaptchaLoad);
    		';

		} elseif ( $captcha_settings['recaptcha_type'] === 'invisible' ) {

			$data  = $polyfills;
			$data .= $dispatch;

			$data .= // language=JavaScript PhpStorm.
				'var wpformsRecaptchaLoad = function () {
				    Array.prototype.forEach.call(document.querySelectorAll(".g-recaptcha"), function (el) {
				        try {
				            var recaptchaID = grecaptcha.render(el, {
				                callback: function () {
				                    wpformsRecaptchaCallback(el);
				                }
				            }, true);
				            el.closest("form").querySelector("button[type=submit]").recaptchaID = recaptchaID;
				        } catch (error) {}
				    });
				    wpformsDispatchEvent(document, "wpformsRecaptchaLoaded", true);
				};
				var wpformsRecaptchaCallback = function (el) {
				    var $form = el.closest("form");
				    if (typeof wpforms.formSubmit === "function") {
				        wpforms.formSubmit($form);
				    } else {
				        $form.querySelector("button[type=submit]").recaptchaID = false;
				        $form.submit();
				    }
				};
			';

		} else {

			$data  = $dispatch;
			$data .= $callback;

			$data .= // language=JavaScript PhpStorm.
				'var wpformsRecaptchaLoad = function () {
				    Array.prototype.forEach.call(document.querySelectorAll(".g-recaptcha"), function (el) {
				        try {
				            var recaptchaID = grecaptcha.render(el, {
				                callback: function () {
				                    wpformsRecaptchaCallback(el);
				                }
				            });
				            el.setAttribute("data-recaptcha-id", recaptchaID);
				        } catch (error) {}
				    });
				    wpformsDispatchEvent(document, "wpformsRecaptchaLoaded", true);
				};
			';

		}

		return $data;
	}

	/**
	 * Load the necessary assets for the confirmation message.
	 *
	 * @since 1.1.2
	 */
	public function assets_confirmation() {

		$min = wpforms_get_min_suffix();

		// Base CSS only.
		if ( (int) wpforms_setting( 'disable-css', '1' ) === 1 ) {
			wp_enqueue_style(
				'wpforms-full',
				WPFORMS_PLUGIN_URL . "assets/css/wpforms-full{$min}.css",
				[],
				WPFORMS_VERSION
			);
		}

		// Special confirmation JS.
		if ( ! wpforms_is_amp() ) {
			wp_enqueue_script(
				'wpforms-confirmation',
				WPFORMS_PLUGIN_URL . 'assets/js/wpforms-confirmation.js',
				[ 'jquery' ],
				WPFORMS_VERSION,
				true
			);
		}

		do_action( 'wpforms_frontend_confirmation' );
	}

	/**
	 * Load the assets in footer if needed (archives, widgets, etc).
	 *
	 * @since 1.0.0
	 */
	public function assets_footer() {

		if ( empty( $this->forms ) && ! $this->assets_global() ) {
			return;
		}

		$this->assets_css();
		$this->assets_js();

		do_action( 'wpforms_wp_footer', $this->forms );
	}

	/**
	 * Get strings to localize.
	 *
	 * @since 1.6.0
	 *
	 * @return array Array of strings to localize.
	 */
	public function get_strings() {

		// Define base strings.
		$strings = [
			'val_required'               => wpforms_setting( 'validation-required', esc_html__( 'This field is required.', 'wpforms-lite' ) ),
			'val_email'                  => wpforms_setting( 'validation-email', esc_html__( 'Please enter a valid email address.', 'wpforms-lite' ) ),
			'val_email_suggestion'       => wpforms_setting( 'validation-email-suggestion', esc_html__( 'Did you mean {suggestion}?', 'wpforms-lite' ) ),
			'val_email_suggestion_title' => esc_attr__( 'Click to accept this suggestion.', 'wpforms-lite' ),
			'val_email_restricted'       => wpforms_setting( 'validation-email-restricted', esc_html__( 'This email address is not allowed.', 'wpforms-lite' ) ),
			'val_number'                 => wpforms_setting( 'validation-number', esc_html__( 'Please enter a valid number.', 'wpforms-lite' ) ),
			'val_number_positive'        => wpforms_setting( 'validation-number-positive', esc_html__( 'Please enter a valid positive number.', 'wpforms-lite' ) ),
			'val_confirm'                => wpforms_setting( 'validation-confirm', esc_html__( 'Field values do not match.', 'wpforms-lite' ) ),
			'val_checklimit'             => wpforms_setting( 'validation-check-limit', esc_html__( 'You have exceeded the number of allowed selections: {#}.', 'wpforms-lite' ) ),
			'val_limit_characters'       => wpforms_setting( 'validation-character-limit', esc_html__( '{count} of {limit} max characters.', 'wpforms-lite' ) ),
			'val_limit_words'            => wpforms_setting( 'validation-word-limit', esc_html__( '{count} of {limit} max words.', 'wpforms-lite' ) ),
			'val_recaptcha_fail_msg'     => wpforms_setting( 'recaptcha-fail-msg', esc_html__( 'Google reCAPTCHA verification failed, please try again later.', 'wpforms-lite' ) ),
			'val_empty_blanks'           => wpforms_setting( 'validation-input-mask-incomplete', esc_html__( 'Please fill out all blanks.', 'wpforms-lite' ) ),
			'uuid_cookie'                => false,
			'locale'                     => wpforms_get_language_code(),
			'wpforms_plugin_url'         => WPFORMS_PLUGIN_URL,
			'gdpr'                       => wpforms_setting( 'gdpr' ),
			'ajaxurl'                    => admin_url( 'admin-ajax.php' ),
			'mailcheck_enabled'          => (bool) apply_filters( 'wpforms_mailcheck_enabled', true ),
			'mailcheck_domains'          => array_map( 'sanitize_text_field', (array) apply_filters( 'wpforms_mailcheck_domains', array() ) ),
			'mailcheck_toplevel_domains' => array_map( 'sanitize_text_field', (array) apply_filters( 'wpforms_mailcheck_toplevel_domains', array( 'dev' ) ) ),
			'is_ssl'                     => is_ssl(),
		];

		// Include payment related strings if needed.
		if ( function_exists( 'wpforms_get_currencies' ) ) {
			$currency                       = wpforms_get_currency();
			$currencies                     = wpforms_get_currencies();
			$strings['currency_code']       = $currency;
			$strings['currency_thousands']  = isset( $currencies[ $currency ]['thousands_separator'] ) ? $currencies[ $currency ]['thousands_separator'] : ',';
			$strings['currency_decimals']   = wpforms_get_currency_decimals( $currencies[ $currency ] );
			$strings['currency_decimal']    = isset( $currencies[ $currency ]['decimal_separator'] ) ? $currencies[ $currency ]['decimal_separator'] : '.';
			$strings['currency_symbol']     = isset( $currencies[ $currency ]['symbol'] ) ? $currencies[ $currency ]['symbol'] : '$';
			$strings['currency_symbol_pos'] = isset( $currencies[ $currency ]['symbol_pos'] ) ? $currencies[ $currency ]['symbol_pos'] : 'left';
		}

		$strings = apply_filters( 'wpforms_frontend_strings', $strings );

		foreach ( (array) $strings as $key => $value ) {
			if ( ! is_scalar( $value ) ) {
				continue;
			}
			$strings[ $key ] = html_entity_decode( (string) $value, ENT_QUOTES, 'UTF-8' );
		}

		return $strings;
	}

	/**
	 * Hook at fires at a later priority in wp_footer.
	 *
	 * @since 1.0.5
	 * @since 1.7.0 Load wpforms_settings on the confirmation page for a non-ajax form.
	 */
	public function footer_end() {

		if (
			( empty( $this->forms ) && empty( $_POST['wpforms'] ) && ! $this->assets_global() ) || // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wpforms_is_amp()
		) {
			return;
		}

		$strings = $this->get_strings();

		/*
		 * Below we do our own implementation of wp_localize_script in an effort
		 * to be better compatible with caching plugins which were causing
		 * conflicts.
		 */
		echo "<script type='text/javascript'>\n";
		echo "/* <![CDATA[ */\n";
		echo 'var wpforms_settings = ' . wp_json_encode( $strings ) . "\n";
		echo "/* ]]> */\n";
		echo "</script>\n";

		do_action( 'wpforms_wp_footer_end', $this->forms );
	}

	/**
	 * Google reCAPTCHA no-conflict mode.
	 *
	 * When enabled in the WPForms settings, forcefully remove all other
	 * reCAPTCHA enqueues to prevent conflicts. Filter can be used to target
	 * specific pages, etc.
	 *
	 * @since 1.4.5
	 * @since 1.6.4 Added hCaptcha support.
	 */
	public function recaptcha_noconflict() {

		$captcha_settings = wpforms_get_captcha_settings();

		if (
			empty( wpforms_setting( 'recaptcha-noconflict' ) ) ||
			empty( $captcha_settings['provider'] ) ||
			'none' === $captcha_settings['provider'] ||
			! apply_filters( 'wpforms_frontend_recaptcha_noconflict', true )
		) {
			return;
		}

		$scripts = wp_scripts();
		$urls    = [ 'google.com/recaptcha', 'gstatic.com/recaptcha', 'hcaptcha.com/1' ];

		foreach ( $scripts->queue as $handle ) {

			// Skip the WPForms javascript-assets.
			if (
				! isset( $scripts->registered[ $handle ] ) ||
				false !== strpos( $scripts->registered[ $handle ]->handle, 'wpforms' )
			) {
				return;
			}

			foreach ( $urls as $url ) {
				if ( false !== strpos( $scripts->registered[ $handle ]->src, $url ) ) {
					wp_dequeue_script( $handle );
					wp_deregister_script( $handle );
					break;
				}
			}
		}
	}

	/**
	 * Shortcode wrapper for the outputting a form.
	 *
	 * @since 1.0.0
	 *
	 * @param array $atts Shortcode attributes provided by a user.
	 *
	 * @return string
	 */
	public function shortcode( $atts ) {

		$defaults = array(
			'id'          => false,
			'title'       => false,
			'description' => false,
		);

		$atts = shortcode_atts( $defaults, shortcode_atts( $defaults, $atts, 'output' ), 'wpforms' );

		ob_start();

		$this->output( $atts['id'], $atts['title'], $atts['description'] );

		return ob_get_clean();
	}

	/**
	 * Inline a script to check if our main js is loaded and display a warning message otherwise.
	 *
	 * @since 1.6.4.1
	 */
	public function missing_assets_error_js() {

		/**
		 * Disable missing assets error js checking.
		 *
		 * @since 1.6.6
		 *
		 * @param bool False by default, set to True to disable checking.
		 */
		$skip = (bool) apply_filters( 'wpforms_frontend_missing_assets_error_js_disable', false );

		if ( $skip || ! wpforms_current_user_can() ) {
			return;
		}

		if ( empty( $this->forms ) && ! $this->assets_global() ) {
			return;
		}

		if ( wpforms_is_amp() ) {
			return;
		}

		printf( $this->get_missing_assets_error_script(), $this->get_missing_assets_error_message() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Get missing assets error script.
	 *
	 * @since 1.6.4.1
	 *
	 * @return string
	 */
	private function get_missing_assets_error_script() {

		return "<script>
				( function() {
					function wpforms_js_error_loading() {

						if ( typeof window.wpforms !== 'undefined' ) {
							return;
						}

						var forms = document.querySelectorAll( '.wpforms-form' );

						if ( ! forms.length ) {
							return;
						}

						var error = document.createElement( 'div' );

						error.classList.add( 'wpforms-error-container' );
						error.innerHTML = '%s';

						forms.forEach( function( form ) {

							if ( ! form.querySelector( '.wpforms-error-container' ) ) {
								form.insertBefore( error.cloneNode( true ), form.firstChild );
							}
						} );
					};

					if ( document.readyState === 'loading' ) {
						document.addEventListener( 'DOMContentLoaded', wpforms_js_error_loading );
					} else {
						wpforms_js_error_loading();
					}
				}() );
			</script>";
	}

	/**
	 * Get missing assets error message.
	 *
	 * @since 1.6.4.1
	 *
	 * @return string
	 */
	private function get_missing_assets_error_message() {

		$message = wp_kses(
			sprintf( /* translators: %s - URL to the troubleshooting guide. */
				__( 'Heads up! WPForms has detected an issue with JavaScript on this page. JavaScript is required for this form to work properly, so this form may not work as expected. See our <a href="%s" target="_blank" rel="noopener noreferrer">troubleshooting guide</a> to learn more or contact support.', 'wpforms-lite' ),
				'https://wpforms.com/docs/getting-support-wpforms/'
			),
			[
				'a' => [
					'href'   => [],
					'target' => [],
					'rel'    => [],
				],
			]
		);

		$message .= '<p>';
		$message .= esc_html__( 'This message is only displayed to site administrators.', 'wpforms-lite' );
		$message .= '</p>';

		return $message;
	}
}
