<?php

namespace WPForms\Admin\Builder;

/**
 * Form templates cache handler.
 *
 * @since 1.6.8
 */
class TemplatesCache extends \WPForms\Helpers\CacheBase {

	/**
	 * Determine if the class is allowed to load.
	 *
	 * @since 1.6.8
	 *
	 * @return bool
	 */
	protected function allow_load() {

		// Load only in the Form Builder.
		return wp_doing_ajax() || wpforms_is_admin_page( 'builder' );
	}

	/**
	 * Provide settings.
	 *
	 * @since 1.6.8
	 *
	 * @return array Settings array.
	 */
	protected function setup() {

		return [

			// Remote source URL.
			'remote_source' => 'https://wpforms.com/templates/api/get/',

			// Cache file.
			'cache_file'    => 'templates.json',

			/**
			 * Time-to-live of the templates cache files in seconds.
			 *
			 * This applies to `uploads/wpforms/cache/templates.json`
			 * and all *.json files in `uploads/wpforms/cache/templates/` directory.
			 *
			 * @since 1.6.8
			 *
			 * @param integer $cache_ttl Cache time-to-live, in seconds.
			 *                           Default value: WEEK_IN_SECONDS.
			 */
			'cache_ttl'     => (int) apply_filters( 'wpforms_admin_builder_templates_cache_ttl', WEEK_IN_SECONDS ),

			// Scheduled update action.
			'update_action' => 'wpforms_admin_builder_templates_cache_update',
		];
	}

	/**
	 * Prepare data to store in a local cache.
	 *
	 * @since 1.6.8
	 *
	 * @param array $data Raw data received by the remote request.
	 *
	 * @return array Prepared data for caching.
	 */
	protected function prepare_cache_data( $data ) {

		if (
			empty( $data ) ||
			! is_array( $data ) ||
			empty( $data['status'] ) ||
			$data['status'] !== 'success' ||
			empty( $data['data'] )
		) {
			return [];
		}

		$cache_data = $data['data'];

		// Strip the word "Template" from the end of each template name.
		foreach ( $cache_data['templates'] as $slug => $template ) {
			$cache_data['templates'][ $slug ]['name'] = preg_replace( '/\sTemplate$/', '', $template['name'] );
		}

		return $cache_data;
	}
}
