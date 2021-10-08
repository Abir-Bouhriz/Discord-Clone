<?php
/**
 * Ajax actions used in by admin.
 *
 * @since 1.0.0
 */

/**
 * Save a form.
 *
 * @since 1.0.0
 */
function wpforms_save_form() {

	// Run a security check.
	if ( ! check_ajax_referer( 'wpforms-builder', 'nonce', false ) ) {
		wp_send_json_error( esc_html__( 'Your session expired. Please reload the builder.', 'wpforms-lite' ) );
	}

	// Check for permissions.
	if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
		wp_send_json_error( esc_html__( 'You are not allowed to perform this action.', 'wpforms-lite' ) );
	}

	// Check for form data.
	if ( empty( $_POST['data'] ) ) {
		wp_send_json_error( esc_html__( 'Something went wrong while performing this action.', 'wpforms-lite' ) );
	}

	$form_post = json_decode( stripslashes( $_POST['data'] ) ); // phpcs:ignore
	$data      = [
		'fields' => [],
	];

	if ( ! is_null( $form_post ) && $form_post ) {
		foreach ( $form_post as $post_input_data ) {
			// For input names that are arrays (e.g. `menu-item-db-id[3][4][5]`),
			// derive the array path keys via regex and set the value in $_POST.
			preg_match( '#([^\[]*)(\[(.+)\])?#', $post_input_data->name, $matches );

			$array_bits = array( $matches[1] );

			if ( isset( $matches[3] ) ) {
				$array_bits = array_merge( $array_bits, explode( '][', $matches[3] ) );
			}

			$new_post_data = [];

			// Build the new array value from leaf to trunk.
			for ( $i = count( $array_bits ) - 1; $i >= 0; $i -- ) {
				if ( $i === count( $array_bits ) - 1 ) {
					$new_post_data[ $array_bits[ $i ] ] = wp_slash( $post_input_data->value );
				} else {
					$new_post_data = array(
						$array_bits[ $i ] => $new_post_data,
					);
				}
			}

			$data = array_replace_recursive( $data, $new_post_data );
		}
	}

	$form_id = wpforms()->form->update( $data['id'], $data );

	do_action( 'wpforms_builder_save_form', $form_id, $data );

	if ( ! $form_id ) {
		wp_send_json_error( esc_html__( 'Something went wrong while saving the form.', 'wpforms-lite' ) );
	}

	wp_send_json_success(
		apply_filters(
			'wpforms_builder_save_form_response_data',
			array(
				'form_name' => esc_html( $data['settings']['form_title'] ),
				'form_desc' => $data['settings']['form_desc'],
				'redirect'  => admin_url( 'admin.php?page=wpforms-overview' ),
			),
			$form_id,
			$data
		)
	);
}

add_action( 'wp_ajax_wpforms_save_form', 'wpforms_save_form' );

/**
 * Create a new form.
 *
 * @since 1.0.0
 */
function wpforms_new_form() {

	// Run a security check.
	check_ajax_referer( 'wpforms-builder', 'nonce' );

	// Check for form name.
	if ( empty( $_POST['title'] ) ) {
		die( esc_html__( 'No form name provided.', 'wpforms-lite' ) );
	}

	if ( ! isset( $_POST['template'] ) ) {
		$_POST['template'] = '';
	}

	// Create form.
	$form_title    = sanitize_text_field( wp_unslash( $_POST['title'] ) );
	$form_template = empty( $_POST['template'] ) ? 'blank' : sanitize_text_field( wp_unslash( $_POST['template'] ) );
	$title_exists  = get_page_by_title( $form_title, 'OBJECT', 'wpforms' );
	$form_id       = wpforms()->form->add(
		$form_title,
		[],
		[
			'template' => $form_template,
		]
	);

	if ( $title_exists !== null ) {
		wp_update_post(
			[
				'ID'         => $form_id,
				'post_title' => $form_title . ' (ID #' . $form_id . ')',
			]
		);
	}

	if ( ! $form_id ) {
		die( esc_html__( 'Error creating form.', 'wpforms-lite' ) );
	}

	if ( wpforms_current_user_can( 'edit_form_single', $form_id ) ) {
		wp_send_json_success(
			[
				'id'       => $form_id,
				'redirect' => add_query_arg(
					[
						'view'    => 'fields',
						'form_id' => $form_id,
						'newform' => '1',
					],
					admin_url( 'admin.php?page=wpforms-builder' )
				),
			]
		);
	}

	if ( wpforms_current_user_can( 'view_forms' ) ) {
		wp_send_json_success( [ 'redirect' => admin_url( 'admin.php?page=wpforms-overview' ) ] );
	}

	wp_send_json_success( [ 'redirect' => admin_url() ] );
}

add_action( 'wp_ajax_wpforms_new_form', 'wpforms_new_form' );

/**
 * Update form template.
 *
 * @since 1.0.0
 */
function wpforms_update_form_template() {

	// Run a security check.
	check_ajax_referer( 'wpforms-builder', 'nonce' );

	// Check for form name.
	if ( empty( $_POST['form_id'] ) ) {
		wp_send_json_error( esc_html__( 'No form ID provided.', 'wpforms-lite' ) );
	}

	$form_template = empty( $_POST['template'] ) ? 'blank' : sanitize_text_field( $_POST['template'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash

	$data    = wpforms()->form->get(
		(int) $_POST['form_id'],
		[
			'content_only' => true,
		]
	);
	$form_id = wpforms()->form->update(
		(int) $_POST['form_id'],
		$data,
		[
			'template' => $form_template,
		]
	);

	if ( $form_id ) {
		wp_send_json_success(
			[
				'id'       => $form_id,
				'redirect' => add_query_arg(
					[
						'view'    => 'fields',
						'form_id' => $form_id,
					],
					admin_url( 'admin.php?page=wpforms-builder' )
				),
			]
		);
	} else {
		wp_send_json_error( esc_html__( 'Error updating form template.', 'wpforms-lite' ) );
	}
}

add_action( 'wp_ajax_wpforms_update_form_template', 'wpforms_update_form_template' );

/**
 * Form Builder update next field ID.
 *
 * @since 1.2.9
 */
function wpforms_builder_increase_next_field_id() {

	// Run a security check.
	check_ajax_referer( 'wpforms-builder', 'nonce' );

	// Check for permissions.
	if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
		wp_send_json_error();
	}

	// Check for required items.
	if ( empty( $_POST['form_id'] ) ) {
		wp_send_json_error();
	}

	wpforms()->form->next_field_id( absint( $_POST['form_id'] ) );

	wp_send_json_success();
}

add_action( 'wp_ajax_wpforms_builder_increase_next_field_id', 'wpforms_builder_increase_next_field_id' );

/**
 * Form Builder Dynamic Choices option toggle.
 *
 * This can be triggered with select/radio/checkbox fields.
 *
 * @since 1.2.8
 */
function wpforms_builder_dynamic_choices() {

	// Run a security check.
	check_ajax_referer( 'wpforms-builder', 'nonce' );

	// Check for permissions.
	if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
		wp_send_json_error();
	}

	// Check for valid/required items.
	if ( ! isset( $_POST['field_id'] ) || empty( $_POST['type'] ) || ! in_array( $_POST['type'], array( 'post_type', 'taxonomy' ), true ) ) {
		wp_send_json_error();
	}

	$type = esc_attr( $_POST['type'] );
	$id   = absint( $_POST['field_id'] );

	// Fetch the option row HTML to be returned to the builder.
	$field      = new WPForms_Field_Select( false );
	$field_args = array(
		'id'              => $id,
		'dynamic_choices' => $type,
	);
	$option_row = $field->field_option( 'dynamic_choices_source', $field_args, array(), false );

	wp_send_json_success(
		array(
			'markup' => $option_row,
		)
	);
}

add_action( 'wp_ajax_wpforms_builder_dynamic_choices', 'wpforms_builder_dynamic_choices' );

/**
 * Form Builder Dynamic Choices Source option toggle.
 *
 * This can be triggered with select/radio/checkbox fields.
 *
 * @since 1.2.8
 */
function wpforms_builder_dynamic_source() {

	// Run a security check.
	check_ajax_referer( 'wpforms-builder', 'nonce' );

	// Check for permissions.
	if ( ! wpforms_current_user_can( 'edit_forms' ) ) {
		wp_send_json_error();
	}

	// Check for required items.
	if ( ! isset( $_POST['field_id'] ) || empty( $_POST['form_id'] ) || empty( $_POST['type'] ) || empty( $_POST['source'] ) ) {
		wp_send_json_error();
	}

	$type        = sanitize_key( $_POST['type'] );
	$source      = sanitize_key( $_POST['source'] );
	$id          = absint( $_POST['field_id'] );
	$form_id     = absint( $_POST['form_id'] );
	$items       = [];
	$total       = 0;
	$source_name = '';
	$type_name   = '';

	if ( $type === 'post_type' ) {

		$type_name   = esc_html__( 'post type', 'wpforms-lite' );
		$args        = [
			'post_type'      => $source,
			'posts_per_page' => 20,
			'orderby'        => 'title',
			'order'          => 'ASC',
		];
		$posts       = wpforms_get_hierarchical_object(
			apply_filters(
				'wpforms_dynamic_choice_post_type_args',
				$args,
				[
					'id' => $id,
				],
				$form_id
			),
			true
		);
		$total       = wp_count_posts( $source );
		$total       = $total->publish;
		$pt          = get_post_type_object( $source );
		$source_name = '';

		if ( $pt !== null ) {
			$source_name = $pt->labels->name;
		}

		foreach ( $posts as $post ) {
			$items[] = trim( $post->post_title );
		}
	} elseif ( $type === 'taxonomy' ) {

		$type_name   = esc_html__( 'taxonomy', 'wpforms-lite' );
		$args        = [
			'taxonomy'   => $source,
			'hide_empty' => false,
			'number'     => 20,
		];
		$terms       = wpforms_get_hierarchical_object(
			apply_filters(
				'wpforms_dynamic_choice_taxonomy_args',
				$args,
				[
					'id' => $id,
				],
				$form_id
			),
			true
		);
		$total       = wp_count_terms( $source );
		$tax         = get_taxonomy( $source );
		$source_name = $tax->labels->name;

		foreach ( $terms as $term ) {
			$items[] = trim( $term->name );
		}
	}

	if ( empty( $items ) ) {
		$items = [
			esc_html__( '(empty)', 'wpforms-lite' ),
		];
	}

	wp_send_json_success(
		[
			'items'       => $items,
			'source'      => $source,
			'source_name' => $source_name,
			'total'       => $total,
			'type'        => $type,
			'type_name'   => $type_name,
		]
	);
}

add_action( 'wp_ajax_wpforms_builder_dynamic_source', 'wpforms_builder_dynamic_source' );

/**
 * Perform test connection to verify that the current web host can successfully
 * make outbound SSL connections.
 *
 * @since 1.4.5
 */
function wpforms_verify_ssl() {

	// Run a security check.
	check_ajax_referer( 'wpforms-admin', 'nonce' );

	// Check for permissions.
	if ( ! wpforms_current_user_can() ) {
		wp_send_json_error(
			array(
				'msg' => esc_html__( 'You do not have permission to perform this operation.', 'wpforms-lite' ),
			)
		);
	}

	$response = wp_remote_post( 'https://wpforms.com/connection-test.php' );

	if ( 200 === wp_remote_retrieve_response_code( $response ) ) {
		wp_send_json_success(
			array(
				'msg' => esc_html__( 'Success! Your server can make SSL connections.', 'wpforms-lite' ),
			)
		);
	}

	wp_send_json_error(
		array(
			'msg'   => esc_html__( 'There was an error and the connection failed. Please contact your web host with the technical details below.', 'wpforms-lite' ),
			'debug' => '<pre>' . print_r( map_deep( $response, 'wp_strip_all_tags' ), true ) . '</pre>',
		)
	);
}
add_action( 'wp_ajax_wpforms_verify_ssl', 'wpforms_verify_ssl' );

/**
 * Deactivate addon.
 *
 * @since 1.0.0
 * @since 1.6.2.3 Updated the permissions checking.
 */
function wpforms_deactivate_addon() {

	// Run a security check.
	check_ajax_referer( 'wpforms-admin', 'nonce' );

	// Check for permissions.
	if ( ! current_user_can( 'deactivate_plugins' ) ) {
		wp_send_json_error( esc_html__( 'Plugin deactivation is disabled for you on this site.', 'wpforms-lite' ) );
	}

	$type = 'addon';
	if ( ! empty( $_POST['type'] ) ) {
		$type = sanitize_key( $_POST['type'] );
	}

	if ( isset( $_POST['plugin'] ) ) {
		$plugin = sanitize_text_field( wp_unslash( $_POST['plugin'] ) );

		deactivate_plugins( $plugin );

		do_action( 'wpforms_plugin_deactivated', $plugin );

		if ( 'plugin' === $type ) {
			wp_send_json_success( esc_html__( 'Plugin deactivated.', 'wpforms-lite' ) );
		} else {
			wp_send_json_success( esc_html__( 'Addon deactivated.', 'wpforms-lite' ) );
		}
	}

	wp_send_json_error( esc_html__( 'Could not deactivate the addon. Please deactivate from the Plugins page.', 'wpforms-lite' ) );
}
add_action( 'wp_ajax_wpforms_deactivate_addon', 'wpforms_deactivate_addon' );

/**
 * Activate addon.
 *
 * @since 1.0.0
 * @since 1.6.2.3 Updated the permissions checking.
 */
function wpforms_activate_addon() {

	// Run a security check.
	check_ajax_referer( 'wpforms-admin', 'nonce' );

	// Check for permissions.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		wp_send_json_error( esc_html__( 'Plugin activation is disabled for you on this site.', 'wpforms-lite' ) );
	}

	$type = 'addon';

	if ( isset( $_POST['plugin'] ) ) {

		if ( ! empty( $_POST['type'] ) ) {
			$type = sanitize_key( $_POST['type'] );
		}

		$plugin   = sanitize_text_field( wp_unslash( $_POST['plugin'] ) );
		$activate = activate_plugins( $plugin );

		/**
		 * Fire after plugin activating via the WPForms installer.
		 *
		 * @since 1.6.3.1
		 *
		 * @param string $plugin Path to the plugin file relative to the plugins directory.
		 */
		do_action( 'wpforms_plugin_activated', $plugin );

		if ( ! is_wp_error( $activate ) ) {
			if ( $type === 'plugin' ) {
				wp_send_json_success( esc_html__( 'Plugin activated.', 'wpforms-lite' ) );
			} else {
				wp_send_json_success( esc_html__( 'Addon activated.', 'wpforms-lite' ) );
			}
		}
	}

	if ( $type === 'plugin' ) {
		wp_send_json_error( esc_html__( 'Could not activate the plugin. Please activate it on the Plugins page.', 'wpforms-lite' ) );
	}

	wp_send_json_error( esc_html__( 'Could not activate the addon. Please activate it on the Plugins page.', 'wpforms-lite' ) );
}
add_action( 'wp_ajax_wpforms_activate_addon', 'wpforms_activate_addon' );

/**
 * Install addon.
 *
 * @since 1.0.0
 * @since 1.6.2.3 Updated the permissions checking.
 */
function wpforms_install_addon() {

	// Run a security check.
	check_ajax_referer( 'wpforms-admin', 'nonce' );

	$generic_error = esc_html__( 'There was an error while performing your request.', 'wpforms-lite' );
	$type          = ! empty( $_POST['type'] ) ? sanitize_key( $_POST['type'] ) : 'addon';

	// Check if new installations are allowed.
	if ( ! wpforms_can_install( $type ) ) {
		wp_send_json_error( $generic_error );
	}

	$error = $type === 'plugin'
		? esc_html__( 'Could not install the plugin. Please download and install it manually.', 'wpforms-lite' )
		: esc_html__( 'Could not install the addon. Please download it from wpforms.com and install it manually.', 'wpforms-lite' );

	if ( empty( $_POST['plugin'] ) ) {
		wp_send_json_error( $error );
	}

	// Set the current screen to avoid undefined notices.
	set_current_screen( 'wpforms_page_wpforms-settings' );

	// Prepare variables.
	$url = esc_url_raw(
		add_query_arg(
			[
				'page' => 'wpforms-addons',
			],
			admin_url( 'admin.php' )
		)
	);

	ob_start();
	$creds = request_filesystem_credentials( $url, '', false, false, null );

	// Hide the filesystem credentials form.
	ob_end_clean();

	// Check for file system permissions.
	if ( $creds === false ) {
		wp_send_json_error( $error );
	}

	if ( ! WP_Filesystem( $creds ) ) {
		wp_send_json_error( $error );
	}

	/*
	 * We do not need any extra credentials if we have gotten this far, so let's install the plugin.
	 */

	require_once WPFORMS_PLUGIN_DIR . 'includes/admin/class-install-skin.php';

	// Do not allow WordPress to search/download translations, as this will break JS output.
	remove_action( 'upgrader_process_complete', [ 'Language_Pack_Upgrader', 'async_upgrade' ], 20 );

	// Create the plugin upgrader with our custom skin.
	$installer = new WPForms\Helpers\PluginSilentUpgrader( new WPForms_Install_Skin() );

	// Error check.
	if ( ! method_exists( $installer, 'install' ) || empty( $_POST['plugin'] ) ) {
		wp_send_json_error( $error );
	}

	$installer->install( $_POST['plugin'] ); // phpcs:ignore

	// Flush the cache and return the newly installed plugin basename.
	wp_cache_flush();

	$plugin_basename = $installer->plugin_info();

	if ( empty( $plugin_basename ) ) {
		wp_send_json_error( $error );
	}

	$result = [
		'msg'          => $generic_error,
		'is_activated' => false,
		'basename'     => $plugin_basename,
	];

	// Check for permissions.
	if ( ! current_user_can( 'activate_plugins' ) ) {
		$result['msg'] = $type === 'plugin' ? esc_html__( 'Plugin installed.', 'wpforms-lite' ) : esc_html__( 'Addon installed.', 'wpforms-lite' );

		wp_send_json_success( $result );
	}

	// Activate the plugin silently.
	$activated = activate_plugin( $plugin_basename );

	if ( ! is_wp_error( $activated ) ) {

		/**
		 * Fire after plugin activating via the WPForms installer.
		 *
		 * @since 1.7.0
		 *
		 * @param string $plugin_basename Path to the plugin file relative to the plugins directory.
		 */
		do_action( 'wpforms_plugin_activated', $plugin_basename );

		$result['is_activated'] = true;
		$result['msg']          = $type === 'plugin' ? esc_html__( 'Plugin installed & activated.', 'wpforms-lite' ) : esc_html__( 'Addon installed & activated.', 'wpforms-lite' );

		wp_send_json_success( $result );
	}

	// Fallback error just in case.
	wp_send_json_error( $result );
}
add_action( 'wp_ajax_wpforms_install_addon', 'wpforms_install_addon' );
