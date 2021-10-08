<?php

/**
 * Form builder that contains magic.
 *
 * @since 1.0.0
 * @since 1.6.8 Form Builder Refresh.
 *                  - Added `deregister_common_wp_admin_styles()` method.
 *                  - Changed logic of enqueuing styles.
 */
class WPForms_Builder {

	/**
	 * One is the loneliest number that you'll ever do.
	 *
	 * @since 1.4.4.1
	 *
	 * @var object
	 */
	private static $instance;

	/**
	 * Current view (panel).
	 *
	 * @since 1.0.0
	 *
	 * @var string
	 */
	public $view;

	/**
	 * Available panels.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $panels;

	/**
	 * Current form.
	 *
	 * @since 1.0.0
	 *
	 * @var object
	 */
	public $form;

	/**
	 * Form data and settings.
	 *
	 * @since 1.4.4.1
	 *
	 * @var array
	 */
	public $form_data;

	/**
	 * Current template information.
	 *
	 * @since 1.0.0
	 *
	 * @var array
	 */
	public $template;

	/**
	 * Main Instance.
	 *
	 * @since 1.4.4.1
	 *
	 * @return WPForms_Builder
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPForms_Builder ) ) {

			self::$instance = new WPForms_Builder();

			add_action( 'admin_init', [ self::$instance, 'init' ], 10 );
			add_action( 'admin_init', [ self::$instance, 'deregister_common_wp_admin_styles' ], PHP_INT_MAX );
		}

		return self::$instance;
	}

	/**
	 * Determine if the user is viewing the builder, if so, party on.
	 *
	 * @since 1.0.0
	 */
	public function init() {

		// Only load if we are actually on the builder.
		if ( ! wpforms_is_admin_page( 'builder' ) ) {
			return;
		}

		// Load form if found.
		$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : false; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( $form_id ) {
			// Default view for with an existing form is fields panel.
			$this->view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'fields'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		} else {
			// Default view for new form is the setup panel.
			$this->view = isset( $_GET['view'] ) ? sanitize_key( $_GET['view'] ) : 'setup'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		}

		if ( $this->view === 'setup' && ! wpforms_current_user_can( 'create_forms' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to create new forms.', 'wpforms-lite' ), 403 );
		}

		if ( $this->view === 'fields' && ! wpforms_current_user_can( 'edit_form_single', $form_id ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to edit this form.', 'wpforms-lite' ), 403 );
		}

		// Fetch form.
		$this->form      = wpforms()->form->get( $form_id );
		$this->form_data = $this->form ? wpforms_decode( $this->form->post_content ) : false;

		/**
		 * Active form template data filter.
		 *
		 * Allows developers to modify fields structure and form settings in the template of the current form.
		 *
		 * @since 1.6.8
		 *
		 * @param array $template Template data.
		 * @param array $form_id  Form ID.
		 */
		$this->template = apply_filters( 'wpforms_builder_template_active', [], $this->form );

		// Load builder panels.
		$this->load_panels();

		add_action( 'admin_head', [ $this, 'admin_head' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueues' ], PHP_INT_MAX );
		add_action( 'admin_print_footer_scripts', [ $this, 'footer_scripts' ] );
		add_action( 'wpforms_admin_page', [ $this, 'output' ] );

		// Save the timestamp when the Builder has been opened for the first time.
		add_option( 'wpforms_builder_opened_date', time(), '', 'no' );

		/**
		 * Form Builder init action.
		 *
		 * Executes after all the form builder UI output.
		 * Intended to use in addons.
		 *
		 * @since 1.6.8
		 *
		 * @param string $view Current view.
		 */
		do_action( 'wpforms_builder_init', $this->view );

		add_filter( 'teeny_mce_plugins', [ $this, 'tinymce_buttons' ] );
	}

	/**
	 * Clear common wp-admin styles, keep only allowed.
	 *
	 * @since 1.6.8
	 */
	public function deregister_common_wp_admin_styles() {

		if ( ! wpforms_is_admin_page( 'builder' ) ) {
			return;
		}

		/**
		 * Filters the allowed common wp-admin styles.
		 *
		 * @since 1.6.8
		 *
		 * @param array $allowed_styles Styles allowed in the Form Builder.
		 */
		$allowed_styles = (array) apply_filters(
			'wpforms_admin_builder_allowed_common_wp_admin_styles',
			[
				'wp-editor',
				'wp-editor-font',
				'editor-buttons',
				'dashicons',
				'media-views',
				'imgareaselect',
				'wp-mediaelement',
				'mediaelement',
				'media-views',
				'buttons',
				'admin-bar',
			]
		);

		wp_styles()->registered = array_intersect_key( wp_styles()->registered, array_flip( $allowed_styles ) );
	}

	/**
	 * Define TinyMCE buttons to use with our fancy editor instances.
	 *
	 * @since 1.0.3
	 *
	 * @param array $buttons List of default buttons.
	 *
	 * @return array
	 */
	public function tinymce_buttons( $buttons ) {

		return [ 'colorpicker', 'lists', 'wordpress', 'wpeditimage', 'wplink' ];
	}

	/**
	 * Load panels.
	 *
	 * @since 1.0.0
	 */
	public function load_panels() {

		// Base class and functions.
		require_once WPFORMS_PLUGIN_DIR . 'includes/admin/builder/panels/class-base.php';

		/**
		 * Form Builder panels slugs array filter.
		 *
		 * Allows developers to disable loading of some builder panels.
		 *
		 * @since 1.0.0
		 *
		 * @param array $panels Panels slugs array.
		 */
		$this->panels = apply_filters(
			'wpforms_builder_panels',
			[
				'setup',
				'fields',
				'settings',
				'providers',
				'payments',
			]
		);

		foreach ( $this->panels as $panel ) {
			$panel = sanitize_file_name( $panel );

			if ( file_exists( WPFORMS_PLUGIN_DIR . 'includes/admin/builder/panels/class-' . $panel . '.php' ) ) {
				require_once WPFORMS_PLUGIN_DIR . 'includes/admin/builder/panels/class-' . $panel . '.php';
			} elseif ( file_exists( WPFORMS_PLUGIN_DIR . 'pro/includes/admin/builder/panels/class-' . $panel . '.php' ) ) {
				require_once WPFORMS_PLUGIN_DIR . 'pro/includes/admin/builder/panels/class-' . $panel . '.php';
			}
		}
	}

	/**
	 * Admin head area inside the form builder.
	 *
	 * @since 1.4.6
	 */
	public function admin_head() {

		// Force hide admin side menu.
		echo '<style>#adminmenumain { display: none !important }</style>';

		do_action( 'wpforms_builder_admin_head', $this->view );
	}

	/**
	 * Enqueue assets for the builder.
	 *
	 * @since 1.0.0
	 * @since 1.6.8 All the panel's stylesheets restructured and moved here.
	 */
	public function enqueues() {

		// Remove conflicting scripts.
		wp_deregister_script( 'serialize-object' );
		wp_deregister_script( 'wpclef-ajax-settings' );

		do_action( 'wpforms_builder_enqueues_before', $this->view );

		$min = wpforms_get_min_suffix();

		/*
		 * Builder CSS.
		 */
		$builder_styles = [
			'overlay',
			'basic',
			'third-party',
			'alerts',
			'ui-general',
			'panels',
			'subsystems',
			'fields',
			'fields-types',
		];

		foreach ( $builder_styles as $style ) {
			wp_enqueue_style(
				$style === 'basic' ? 'wpforms-builder' : 'wpforms-builder-' . $style,
				WPFORMS_PLUGIN_URL . "assets/css/builder/builder-{$style}{$min}.css",
				[],
				WPFORMS_VERSION
			);
		}

		/*
		 * Third-party CSS.
		 */
		wp_enqueue_style(
			'wpforms-font-awesome',
			WPFORMS_PLUGIN_URL . 'assets/css/font-awesome.min.css',
			null,
			'4.7.0'
		);

		wp_enqueue_style(
			'tooltipster',
			WPFORMS_PLUGIN_URL . 'assets/css/tooltipster.css',
			null,
			'4.2.6'
		);

		wp_enqueue_style(
			'jquery-confirm',
			WPFORMS_PLUGIN_URL . 'assets/css/jquery-confirm.min.css',
			null,
			'3.3.2'
		);

		wp_enqueue_style(
			'minicolors',
			WPFORMS_PLUGIN_URL . 'assets/css/jquery.minicolors.css',
			null,
			'2.2.6'
		);

		// Remove TinyMCE editor styles from third-party themes and plugins.
		remove_editor_styles();

		/*
		 * JavaScript.
		 */
		wp_enqueue_media();
		wp_enqueue_script( 'jquery-ui-sortable' );
		wp_enqueue_script( 'jquery-ui-draggable' );
		wp_enqueue_script( 'wp-util' );

		wp_enqueue_script(
			'tooltipster',
			WPFORMS_PLUGIN_URL . 'assets/js/jquery.tooltipster.min.js',
			[ 'jquery' ],
			'4.2.6'
		);

		wp_enqueue_script(
			'jquery-confirm',
			WPFORMS_PLUGIN_URL . 'assets/js/jquery.jquery-confirm.min.js',
			[ 'jquery' ],
			'3.3.2'
		);

		wp_enqueue_script(
			'matchheight',
			WPFORMS_PLUGIN_URL . 'assets/js/jquery.matchHeight-min.js',
			[ 'jquery' ],
			'0.7.0'
		);

		wp_enqueue_script(
			'insert-at-caret',
			WPFORMS_PLUGIN_URL . 'assets/js/jquery.insert-at-caret.min.js',
			[ 'jquery' ],
			'1.1.4'
		);

		wp_enqueue_script(
			'minicolors',
			WPFORMS_PLUGIN_URL . 'assets/js/jquery.minicolors.min.js',
			[ 'jquery' ],
			'2.2.6'
		);

		wp_enqueue_script(
			'conditionals',
			WPFORMS_PLUGIN_URL . 'assets/js/jquery.conditionals.min.js',
			[ 'jquery' ],
			'1.0.0'
		);

		wp_enqueue_script(
			'choicesjs',
			WPFORMS_PLUGIN_URL . 'assets/js/choices.min.js',
			[],
			'9.0.1'
		);

		wp_enqueue_script(
			'listjs',
			WPFORMS_PLUGIN_URL . 'assets/js/list.min.js',
			[ 'jquery' ],
			'2.3.0'
		);

		wp_enqueue_script(
			'dom-purify',
			WPFORMS_PLUGIN_URL . 'assets/js/purify.min.js',
			[],
			'2.3.0'
		);

		if ( wp_is_mobile() ) {
			wp_enqueue_script( 'jquery-touch-punch' );
		}

		wp_enqueue_script(
			'wpforms-utils',
			WPFORMS_PLUGIN_URL . "assets/js/admin-utils{$min}.js",
			[ 'jquery', 'dom-purify' ],
			WPFORMS_VERSION
		);

		wp_enqueue_script(
			'wpforms-builder',
			WPFORMS_PLUGIN_URL . "assets/js/admin-builder{$min}.js",
			[ 'wpforms-utils', 'wpforms-admin-builder-templates', 'jquery-ui-sortable', 'jquery-ui-draggable', 'tooltipster', 'jquery-confirm' ],
			WPFORMS_VERSION
		);

		wp_enqueue_script(
			'wpforms-admin-builder-templates',
			WPFORMS_PLUGIN_URL . "assets/js/components/admin/builder/templates{$min}.js",
			[ 'wp-util' ],
			WPFORMS_VERSION,
			true
		);

		wp_localize_script(
			'wpforms-builder',
			'wpforms_builder',
			$this->get_localized_strings()
		);

		wp_localize_script(
			'wpforms-builder',
			'wpforms_addons',
			$this->get_localized_addons()
		);

		/**
		 * Form Builder enqueues action.
		 *
		 * Executes after all the form builder assets were enqueued.
		 * Intended to use in addons.
		 *
		 * @since 1.0.0
		 *
		 * @param string $view Current view.
		 */
		do_action( 'wpforms_builder_enqueues', $this->view );
	}

	/**
	 * Get localized strings.
	 *
	 * @since 1.6.8
	 *
	 * @return array
	 */
	private function get_localized_strings() {

		$strings = [
			'and'                            => esc_html__( 'And', 'wpforms-lite' ),
			'ajax_url'                       => admin_url( 'admin-ajax.php' ),
			'bulk_add_button'                => esc_html__( 'Add New Choices', 'wpforms-lite' ),
			'bulk_add_show'                  => esc_html__( 'Bulk Add', 'wpforms-lite' ),
			'are_you_sure_to_close'          => esc_html__( 'Are you sure you want to leave? You have unsaved changes', 'wpforms-lite' ),
			'bulk_add_hide'                  => esc_html__( 'Hide Bulk Add', 'wpforms-lite' ),
			'bulk_add_heading'               => esc_html__( 'Add Choices (one per line)', 'wpforms-lite' ),
			'bulk_add_placeholder'           => esc_html__( "Blue\nRed\nGreen", 'wpforms-lite' ),
			'bulk_add_presets_show'          => esc_html__( 'Show presets', 'wpforms-lite' ),
			'bulk_add_presets_hide'          => esc_html__( 'Hide presets', 'wpforms-lite' ),
			'date_select_day'                => 'DD',
			'date_select_month'              => 'MM',
			'debug'                          => wpforms_debug(),
			'dynamic_choice_limit'           => esc_html__( 'The {source} {type} contains over {limit} items ({total}). This may make the field difficult for your visitors to use and/or cause the form to be slow.', 'wpforms-lite' ),
			'cancel'                         => esc_html__( 'Cancel', 'wpforms-lite' ),
			'ok'                             => esc_html__( 'OK', 'wpforms-lite' ),
			'close'                          => esc_html__( 'Close', 'wpforms-lite' ),
			'conditionals_change'            => esc_html__( 'Due to form changes, conditional logic rules will be removed or updated:', 'wpforms-lite' ),
			'conditionals_disable'           => esc_html__( 'Are you sure you want to disable conditional logic? This will remove the rules for this field or setting.', 'wpforms-lite' ),
			'field'                          => esc_html__( 'Field', 'wpforms-lite' ),
			'field_locked'                   => esc_html__( 'Field Locked', 'wpforms-lite' ),
			'field_locked_msg'               => esc_html__( 'This field cannot be deleted or duplicated.', 'wpforms-lite' ),
			'fields_available'               => esc_html__( 'Available Fields', 'wpforms-lite' ),
			'fields_unavailable'             => esc_html__( 'No fields available', 'wpforms-lite' ),
			'heads_up'                       => esc_html__( 'Heads up!', 'wpforms-lite' ),
			'image_placeholder'              => WPFORMS_PLUGIN_URL . 'assets/images/builder/placeholder-200x125.svg',
			'nonce'                          => wp_create_nonce( 'wpforms-builder' ),
			'admin_nonce'                    => wp_create_nonce( 'wpforms-admin' ),
			'no_email_fields'                => esc_html__( 'No email fields', 'wpforms-lite' ),
			'notification_delete'            => esc_html__( 'Are you sure you want to delete this notification?', 'wpforms-lite' ),
			'notification_prompt'            => esc_html__( 'Enter a notification name', 'wpforms-lite' ),
			'notification_ph'                => esc_html__( 'Eg: User Confirmation', 'wpforms-lite' ),
			'notification_error'             => esc_html__( 'You must provide a notification name', 'wpforms-lite' ),
			'notification_def_name'          => esc_html__( 'Default Notification', 'wpforms-lite' ),
			'confirmation_delete'            => esc_html__( 'Are you sure you want to delete this confirmation?', 'wpforms-lite' ),
			'confirmation_prompt'            => esc_html__( 'Enter a confirmation name', 'wpforms-lite' ),
			'confirmation_ph'                => esc_html__( 'Eg: Alternative Confirmation', 'wpforms-lite' ),
			'confirmation_error'             => esc_html__( 'You must provide a confirmation name', 'wpforms-lite' ),
			'confirmation_def_name'          => esc_html__( 'Default Confirmation', 'wpforms-lite' ),
			'save'                           => esc_html__( 'Save', 'wpforms-lite' ),
			'saving'                         => esc_html__( 'Saving', 'wpforms-lite' ),
			'saved'                          => esc_html__( 'Saved!', 'wpforms-lite' ),
			'save_exit'                      => esc_html__( 'Save and Exit', 'wpforms-lite' ),
			'save_embed'                     => esc_html__( 'Save and Embed', 'wpforms-lite' ),
			'saved_state'                    => '',
			'layout_selector_show'           => esc_html__( 'Show Layouts', 'wpforms-lite' ),
			'layout_selector_hide'           => esc_html__( 'Hide Layouts', 'wpforms-lite' ),
			'layout_selector_layout'         => esc_html__( 'Select your layout', 'wpforms-lite' ),
			'layout_selector_column'         => esc_html__( 'Select your column', 'wpforms-lite' ),
			'loading'                        => esc_html__( 'Loading', 'wpforms-lite' ),
			'template_name'                  => ! empty( $this->template['name'] ) ? $this->template['name'] : '',
			'template_slug'                  => ! empty( $this->template['slug'] ) ? $this->template['slug'] : '',
			'template_modal_title'           => ! empty( $this->template['modal']['title'] ) ? $this->template['modal']['title'] : '',
			'template_modal_msg'             => ! empty( $this->template['modal']['message'] ) ? $this->template['modal']['message'] : '',
			'template_modal_display'         => ! empty( $this->template['modal_display'] ) ? $this->template['modal_display'] : '',
			'template_select'                => esc_html__( 'Use Template', 'wpforms-lite' ),
			'template_confirm'               => esc_html__( 'Changing templates on an existing form will DELETE existing form fields. Are you sure you want apply the new template?', 'wpforms-lite' ),
			'embed'                          => esc_html__( 'Embed', 'wpforms-lite' ),
			'exit'                           => esc_html__( 'Exit', 'wpforms-lite' ),
			'exit_url'                       => wpforms_current_user_can( 'view_forms' ) ? admin_url( 'admin.php?page=wpforms-overview' ) : admin_url(),
			'exit_confirm'                   => esc_html__( 'Your form contains unsaved changes. Would you like to save your changes first.', 'wpforms-lite' ),
			'delete_confirm'                 => esc_html__( 'Are you sure you want to delete this field?', 'wpforms-lite' ),
			'delete_choice_confirm'          => esc_html__( 'Are you sure you want to delete this choice?', 'wpforms-lite' ),
			'duplicate_confirm'              => esc_html__( 'Are you sure you want to duplicate this field?', 'wpforms-lite' ),
			'duplicate_copy'                 => esc_html__( '(copy)', 'wpforms-lite' ),
			'error_title'                    => esc_html__( 'Please enter a form name.', 'wpforms-lite' ),
			'error_choice'                   => esc_html__( 'This item must contain at least one choice.', 'wpforms-lite' ),
			'off'                            => esc_html__( 'Off', 'wpforms-lite' ),
			'on'                             => esc_html__( 'On', 'wpforms-lite' ),
			'or'                             => esc_html__( 'or', 'wpforms-lite' ),
			'other'                          => esc_html__( 'Other', 'wpforms-lite' ),
			'operator_is'                    => esc_html__( 'is', 'wpforms-lite' ),
			'operator_is_not'                => esc_html__( 'is not', 'wpforms-lite' ),
			'operator_empty'                 => esc_html__( 'empty', 'wpforms-lite' ),
			'operator_not_empty'             => esc_html__( 'not empty', 'wpforms-lite' ),
			'operator_contains'              => esc_html__( 'contains', 'wpforms-lite' ),
			'operator_not_contains'          => esc_html__( 'does not contain', 'wpforms-lite' ),
			'operator_starts'                => esc_html__( 'starts with', 'wpforms-lite' ),
			'operator_ends'                  => esc_html__( 'ends with', 'wpforms-lite' ),
			'operator_greater_than'          => esc_html__( 'greater than', 'wpforms-lite' ),
			'operator_less_than'             => esc_html__( 'less than', 'wpforms-lite' ),
			'payments_entries_off'           => esc_html__( 'Entry storage is currently disabled, but is required to accept payments. Please enable in your form settings.', 'wpforms-lite' ),
			'payments_on_entries_off'        => esc_html__( 'This form is currently accepting payments. Entry storage is required to accept payments. To disable entry storage, please first disable payments.', 'wpforms-lite' ),
			'previous'                       => esc_html__( 'Previous', 'wpforms-lite' ),
			'provider_required_flds'         => esc_html__( "In order to complete your form's {provider} integration, please check that the dropdowns for all required (*) List Fields have been filled out.", 'wpforms-lite' ),
			'rule_create'                    => esc_html__( 'Create new rule', 'wpforms-lite' ),
			'rule_create_group'              => esc_html__( 'Add New Group', 'wpforms-lite' ),
			'rule_delete'                    => esc_html__( 'Delete rule', 'wpforms-lite' ),
			'smart_tags'                     => apply_filters( 'wpforms_builder_enqueues_smart_tags', wpforms()->get( 'smart_tags' )->get_smart_tags() ),
			'smart_tags_disabled_for_fields' => [ 'entry_id' ],
			'smart_tags_show'                => esc_html__( 'Show Smart Tags', 'wpforms-lite' ),
			'smart_tags_hide'                => esc_html__( 'Hide Smart Tags', 'wpforms-lite' ),
			'select_field'                   => esc_html__( '--- Select Field ---', 'wpforms-lite' ),
			'select_choice'                  => esc_html__( '--- Select Choice ---', 'wpforms-lite' ),
			'upload_image_title'             => esc_html__( 'Upload or Choose Your Image', 'wpforms-lite' ),
			'upload_image_button'            => esc_html__( 'Use Image', 'wpforms-lite' ),
			'upload_image_remove'            => esc_html__( 'Remove Image', 'wpforms-lite' ),
			'provider_add_new_acc_btn'       => esc_html__( 'Add', 'wpforms-lite' ),
			'pro'                            => wpforms()->pro,
			'is_gutenberg'                   => version_compare( get_bloginfo( 'version' ), '5.0', '>=' ) && ! is_plugin_active( 'classic-editor/classic-editor.php' ),
			'cl_fields_supported'            => wpforms_get_conditional_logic_form_fields_supported(),
			'redirect_url_field_error'       => esc_html__( 'You should enter a valid absolute address to the Confirmation Redirect URL field.', 'wpforms-lite' ),
			'add_custom_value_label'         => esc_html__( 'Add Custom Value', 'wpforms-lite' ),
			'choice_empty_label_tpl'         => esc_html__( 'Choice {number}', 'wpforms-lite' ),
			'error_save_form'                => esc_html__( 'Something went wrong while saving the form. Please reload the page and try again.', 'wpforms-lite' ),
			'error_contact_support'          => esc_html__( 'Please contact the plugin support team if this behavior persists.', 'wpforms-lite' ),
			'ms_win_css_url'                 => WPFORMS_PLUGIN_URL . 'assets/css/builder/builder-ms-win.css',
			'template_addon_prompt'          => esc_html__( 'The %template% template requires the %addons%. Would you like to install and activate it?', 'wpforms-lite' ),
			'template_addons_prompt'         => esc_html__( 'The %template% template requires the %addons%. Would you like to install and activate all the required addons?', 'wpforms-lite' ),
			'template_addons_error'          => esc_html__( 'Could not install OR activate all the required addons. Please download from wpforms.com and install them manually. Would you like to use the template anyway?', 'wpforms-lite' ),
			'use_template'                   => esc_html__( 'Yes, use template', 'wpforms-lite' ),
			'error_select_template'          => esc_html__( 'Something went wrong while applying the template.', 'wpforms-lite' ),
			'blank_form'                     => esc_html__( 'Blank Form', 'wpforms-lite' ),
            'something_went_wrong'           => esc_html__( 'Something went wrong', 'wpforms-lite' ),
		];

		$strings['disable_entries'] = sprintf(
			wp_kses( /* translators: %s - Link to the WPForms.com doc article. */
				__( 'Disabling entry storage for this form will completely prevent any new submissions from getting saved to your site. If you still intend to keep a record of entries through notification emails, then please <a href="%s" target="_blank" rel="noopener noreferrer">test your form</a> to ensure emails send reliably.', 'wpforms-lite' ),
				[
					'a' => [
						'href'   => [],
						'rel'    => [],
						'target' => [],
					],
				]
			),
			'https://wpforms.com/docs/how-to-properly-test-your-wordpress-forms-before-launching-checklist/'
		);

		$strings = apply_filters( 'wpforms_builder_strings', $strings, $this->form );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_GET['form_id'] ) ) {
			$form_id = (int) $_GET['form_id'];

			$strings['preview_url'] = esc_url( add_query_arg( 'new_window', 1, wpforms_get_form_preview_url( $form_id ) ) );
			$strings['entries_url'] = esc_url( admin_url( 'admin.php?page=wpforms-entries&view=list&form_id=' . $form_id ) );
		}
		// phpcs:enable

		return $strings;
	}

	/**
	 * Get localized addons.
	 *
	 * @since 1.6.8
	 *
	 * @return array
	 */
	private function get_localized_addons() {

		return wpforms_chain( wpforms()->get( 'addons' )->get_available() )
			->map(
				function( $addon ) {
					return [
						'title'  => $addon['title'],
						'action' => $addon['action'],
						'url'    => $addon['url'],
					];
				}
			)
			->value();
	}

	/**
	 * Footer JavaScript.
	 *
	 * @since 1.3.7
	 */
	public function footer_scripts() {

		$countries        = wpforms_countries();
		$countries_postal = array_keys( $countries );
		$countries        = array_values( $countries );

		sort( $countries_postal );
		sort( $countries );

		$choices = [
			'countries'        => [
				'name'    => esc_html__( 'Countries', 'wpforms-lite' ),
				'choices' => $countries,
			],
			'countries_postal' => [
				'name'    => esc_html__( 'Countries Postal Code', 'wpforms-lite' ),
				'choices' => $countries_postal,
			],
			'states'           => [
				'name'    => esc_html__( 'States', 'wpforms-lite' ),
				'choices' => array_values( wpforms_us_states() ),
			],
			'states_postal'    => [
				'name'    => esc_html__( 'States Postal Code', 'wpforms-lite' ),
				'choices' => array_keys( wpforms_us_states() ),
			],
			'months'           => [
				'name'    => esc_html__( 'Months', 'wpforms-lite' ),
				'choices' => array_values( wpforms_months() ),
			],
			'days'             => [
				'name'    => esc_html__( 'Days', 'wpforms-lite' ),
				'choices' => array_values( wpforms_days() ),
			],
		];

		/**
		 * Choices presets array filter.
		 *
		 * Allows developers to edit the choices presets used in all choices-based fields.
		 *
		 * @since 1.3.7
		 *
		 * @param array $choices {
		 *    Choices presets is the [ `slug` => `preset`, ... ] array.
		 *
		 *    @param array $preset {
		 *        Each preset data is the array with two elements:
		 *
		 *        @param string $name    Name of the preset
		 *        @param array  $choices Choices array.
		 *    }
		 *    ...
		 * }
		 */
		$choices = apply_filters( 'wpforms_builder_preset_choices', $choices );

		echo '<script type="text/javascript">wpforms_preset_choices=' . wp_json_encode( $choices ) . '</script>';

		do_action( 'wpforms_builder_print_footer_scripts' );
	}

	/**
	 * Load the appropriate files to build the page.
	 *
	 * @since 1.0.0
	 */
	public function output() {

		if ( ! (bool) apply_filters( 'wpforms_builder_output', true ) ) {
			return;
		}

		$form_id      = $this->form ? absint( $this->form->ID ) : '';
		$field_id     = ! empty( $this->form_data['field_id'] ) ? $this->form_data['field_id'] : '';
		$preview_url  = wpforms_get_form_preview_url( $form_id, true );
		$allowed_caps = [ 'edit_posts', 'edit_other_posts', 'edit_private_posts', 'edit_published_posts', 'edit_pages', 'edit_other_pages', 'edit_published_pages', 'edit_private_pages' ];
		?>

		<div id="wpforms-builder" class="wpforms-admin-page">

			<div id="wpforms-builder-ie-notice" class="wpforms-fullscreen-notice">
				<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/builder/ie-logo.svg' ); ?>" alt="Internet Explorer &reg;">
				<h3><?php esc_html_e( 'You are using an outdated browser!', 'wpforms-lite' ); ?></h3>
				<p>
				<?php
				printf(
					wp_kses( /* translators: %1$s - Link to the update Internet Explorer page, %2$s - link to the browse happy page. */
						__( 'The Internet Explorer browser no more supported.<br>Our form builder is optimized for modern browsers.<br>Please <a href="%1$s" target="_blank" rel="nofollow noopener">install Microsoft Edge</a> or learn<br>how to <a href="%2$s" target="_blank" rel="nofollow noopener">browse happy</a>.', 'wpforms-lite' ),
						[
							'a'  => [
								'href'   => [],
								'target' => [],
								'rel'    => [],
							],
							'br' => [],
						]
					),
					'https://www.microsoft.com/en-us/edge',
					'https://browsehappy.com/'
				);
				?>
				</p>
				<button type="button" class="wpforms-fullscreen-notice-go-back"><?php esc_html_e( 'Go back', 'wpforms-lite' ); ?></button>
			</div>

			<div id="wpforms-builder-mobile-notice" class="wpforms-fullscreen-notice">
				<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/sullie-builder-mobile.png' ); ?>" alt="<?php esc_attr_e( 'Sullie the WPForms mascot', 'wpforms-lite' ); ?>">
				<h3><?php esc_html_e( 'Oh, hi there!', 'wpforms-lite' ); ?></h3>
				<p><?php esc_html_e( 'Our form builder is optimized for desktop computers and tablets. Please manage your forms on a different device, or if you\'re on a tablet, try rotating it.', 'wpforms-lite' ); ?></p>
				<button type="button" class="wpforms-fullscreen-notice-go-back"><?php esc_html_e( 'Go back', 'wpforms-lite' ); ?></button>
			</div>

			<div id="wpforms-builder-overlay">
				<div class="wpforms-builder-overlay-content">
					<i class="spinner"></i>
					<i class="avatar"></i>
				</div>
			</div>

			<form name="wpforms-builder" id="wpforms-builder-form" method="post" data-id="<?php echo esc_attr( $form_id ); ?>">

				<input type="hidden" name="id" value="<?php echo esc_attr( $form_id ); ?>">
				<input type="hidden" value="<?php echo absint( $field_id ); ?>" name="field_id" id="wpforms-field-id">

				<!-- Toolbar -->
				<div class="wpforms-toolbar">

					<div class="wpforms-left">

						<img src="<?php echo esc_url( WPFORMS_PLUGIN_URL . 'assets/images/sullie-alt.png' ); ?>" alt="<?php esc_attr_e( 'Sullie the WPForms mascot', 'wpforms-lite' ); ?>">

					</div>

					<div class="wpforms-center">

						<?php if ( $this->form ) : ?>

							<?php esc_html_e( 'Now editing', 'wpforms-lite' ); ?>
							<span class="wpforms-center-form-name wpforms-form-name"><?php echo esc_html( $this->form->post_title ); ?></span>

						<?php endif; ?>

					</div>

					<div class="wpforms-right">

						<button id="wpforms-help"
							class="wpforms-btn wpforms-btn-toolbar wpforms-btn-light-grey"
							title="<?php esc_attr_e( 'Help Ctrl+H', 'wpforms-lite' ); ?>">
								<i class="fa fa-question-circle-o"></i><span><?php esc_html_e( 'Help', 'wpforms-lite' ); ?></span>
						</button>

						<?php if ( $this->form ) : ?>

							<a href="<?php echo esc_url( $preview_url ); ?>"
								id="wpforms-preview-btn"
								class="wpforms-btn wpforms-btn-toolbar wpforms-btn-light-grey"
								title="<?php esc_attr_e( 'Preview Form Ctrl+P', 'wpforms-lite' ); ?>"
								target="_blank"
								rel="noopener noreferrer">
									<i class="fa fa-eye"></i><span class="text"><?php esc_html_e( 'Preview', 'wpforms-lite' ); ?></span>
							</a>

							<?php if ( array_filter( (array) $allowed_caps, 'current_user_can' ) ) : ?>
								<button id="wpforms-embed"
									class="wpforms-btn wpforms-btn-toolbar wpforms-btn-light-grey"
									title="<?php esc_attr_e( 'Embed Form Ctrl+B', 'wpforms-lite' ); ?>">
										<i class="fa fa-code"></i><span class="text"><?php esc_html_e( 'Embed', 'wpforms-lite' ); ?></span>
								</button>
							<?php endif; ?>

							<button id="wpforms-save"
								class="wpforms-btn wpforms-btn-toolbar wpforms-btn-orange"
								title="<?php esc_attr_e( 'Save Form Ctrl+S', 'wpforms-lite' ); ?>">
									<i class="fa fa-check"></i><i class="wpforms-loading-spinner wpforms-loading-white wpforms-loading-inline wpforms-hidden"></i><span class="text"><?php esc_html_e( 'Save', 'wpforms-lite' ); ?></span>
							</button>

						<?php endif; ?>

						<button id="wpforms-exit" title="<?php esc_attr_e( 'Exit Ctrl+Q', 'wpforms-lite' ); ?>">
							<i class="fa fa-times"></i>
						</button>

					</div>

				</div>

				<!-- Panel toggle buttons. -->
				<div class="wpforms-panels-toggle" id="wpforms-panels-toggle">

					<?php do_action( 'wpforms_builder_panel_buttons', $this->form, $this->view ); ?>

				</div>

				<div class="wpforms-panels">

					<?php do_action( 'wpforms_builder_panels', $this->form, $this->view ); ?>

				</div>

			</form>

		</div>

		<?php
	}
}

WPForms_Builder::instance();
