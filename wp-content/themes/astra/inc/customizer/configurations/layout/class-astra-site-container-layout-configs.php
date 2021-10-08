<?php
/**
 * General Options for Astra Theme.
 *
 * @package     Astra
 * @author      Astra
 * @copyright   Copyright (c) 2020, Astra
 * @link        https://wpastra.com/
 * @since       Astra 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Astra_Site_Container_Layout_Configs' ) ) {

	/**
	 * Register Astra Site Container Layout Customizer Configurations.
	 */
	class Astra_Site_Container_Layout_Configs extends Astra_Customizer_Config_Base {

		/**
		 * Register Astra Site Container Layout Customizer Configurations.
		 *
		 * @param Array                $configurations Astra Customizer Configurations.
		 * @param WP_Customize_Manager $wp_customize instance of WP_Customize_Manager.
		 * @since 1.4.3
		 * @return Array Astra Customizer Configurations with updated configurations.
		 */
		public function register_configuration( $configurations, $wp_customize ) {

			$_section = 'section-colors-background';

			if ( class_exists( 'Astra_Ext_Extension' ) && Astra_Ext_Extension::is_active( 'colors-and-background' ) && ! astra_has_gcp_typo_preset_compatibility() ) {
				$_section = 'section-colors-body';
			}

			$_configs = array(

				/**
				 * Option: Single Page Content Layout
				 */
				array(
					'name'     => ASTRA_THEME_SETTINGS . '[site-content-layout]',
					'type'     => 'control',
					'default'  => astra_get_option( 'site-content-layout' ),
					'control'  => 'ast-select',
					'section'  => 'section-container-layout',
					'priority' => 50,
					'title'    => __( 'Layout', 'astra' ),
					'choices'  => array(
						'boxed-container'         => __( 'Boxed', 'astra' ),
						'content-boxed-container' => __( 'Content Boxed', 'astra' ),
						'plain-container'         => __( 'Full Width / Contained', 'astra' ),
						'page-builder'            => __( 'Full Width / Stretched', 'astra' ),
					),
					'divider'  => array( 'ast_class' => 'ast-bottom-divider' ),
				),

				/**
				 * Option: Single Page Content Layout
				 */
				array(
					'name'     => ASTRA_THEME_SETTINGS . '[single-page-content-layout]',
					'type'     => 'control',
					'control'  => 'ast-select',
					'default'  => astra_get_option( 'single-page-content-layout' ),
					'section'  => 'section-container-layout',
					'title'    => __( 'Page Layout', 'astra' ),
					'priority' => 55,
					'choices'  => array(
						'default'                 => __( 'Default', 'astra' ),
						'boxed-container'         => __( 'Boxed', 'astra' ),
						'content-boxed-container' => __( 'Content Boxed', 'astra' ),
						'plain-container'         => __( 'Full Width / Contained', 'astra' ),
						'page-builder'            => __( 'Full Width / Stretched', 'astra' ),
					),
					'divider'  => array( 'ast_class' => 'ast-bottom-divider' ),
				),

				array(
					'name'     => ASTRA_THEME_SETTINGS . '[single-post-content-layout]',
					'type'     => 'control',
					'control'  => 'ast-select',
					'default'  => astra_get_option( 'single-post-content-layout' ),
					'section'  => 'section-container-layout',
					'priority' => 60,
					'title'    => __( 'Blog Post Layout', 'astra' ),
					'choices'  => array(
						'default'                 => __( 'Default', 'astra' ),
						'boxed-container'         => __( 'Boxed', 'astra' ),
						'content-boxed-container' => __( 'Content Boxed', 'astra' ),
						'plain-container'         => __( 'Full Width / Contained', 'astra' ),
						'page-builder'            => __( 'Full Width / Stretched', 'astra' ),
					),
					'divider'  => array( 'ast_class' => 'ast-bottom-divider' ),
				),

				/**
				 * Option: Archive Post Content Layout
				 */
				array(
					'name'     => ASTRA_THEME_SETTINGS . '[archive-post-content-layout]',
					'type'     => 'control',
					'control'  => 'ast-select',
					'default'  => astra_get_option( 'archive-post-content-layout' ),
					'section'  => 'section-container-layout',
					'priority' => 65,
					'title'    => __( 'Archives Layout', 'astra' ),
					'choices'  => array(
						'default'                 => __( 'Default', 'astra' ),
						'boxed-container'         => __( 'Boxed', 'astra' ),
						'content-boxed-container' => __( 'Content Boxed', 'astra' ),
						'plain-container'         => __( 'Full Width / Contained', 'astra' ),
						'page-builder'            => __( 'Full Width / Stretched', 'astra' ),
					),
				),

				/**
				 * Option: Body Background
				 */
				array(
					'name'      => ASTRA_THEME_SETTINGS . '[site-layout-outside-bg-obj-responsive]',
					'type'      => 'control',
					'control'   => 'ast-responsive-background',
					'default'   => astra_get_option( 'site-layout-outside-bg-obj-responsive' ),
					'section'   => $_section,
					'transport' => 'postMessage',
					'priority'  => 25,
					'title'     => __( 'Site Background', 'astra' ),
				),
			);

			$section_content_bg_obj = ( class_exists( 'Astra_Ext_Extension' ) && Astra_Ext_Extension::is_active( 'colors-and-background' ) ) ? 'section-colors-body' : 'section-colors-background';

			if ( astra_has_gcp_typo_preset_compatibility() ) {

				$_configs[] = array(
					'name'      => ASTRA_THEME_SETTINGS . '[content-bg-obj-responsive]',
					'default'   => astra_get_option( 'content-bg-obj-responsive' ),
					'type'      => 'control',
					'control'   => 'ast-responsive-background',
					'section'   => $_section,
					'title'     => __( 'Content Background', 'astra' ),
					'transport' => 'postMessage',
					'priority'  => 25,
					'divider'   => defined( 'ASTRA_EXT_VER' ) && Astra_Ext_Extension::is_active( 'colors-and-background' ) ? array( 'ast_class' => 'ast-bottom-divider' ) : array(),
				);
			}

			$configurations = array_merge( $configurations, $_configs );

			// Learn More link if Astra Pro is not activated.
			if ( ! defined( 'ASTRA_EXT_VER' ) ) {

				$config = array(

					array(
						'name'     => ASTRA_THEME_SETTINGS . '[ast-container-more-feature-description]',
						'type'     => 'control',
						'control'  => 'ast-description',
						'section'  => 'section-container-layout',
						'priority' => 999,
						'title'    => '',
						'help'     => '<p>' . __( 'More Options Available in Astra Pro!', 'astra' ) . '</p><a href="' . astra_get_pro_url( 'https://wpastra.com/pro/', 'customizer', 'learn-more', 'upgrade-to-pro' ) . '" class="button button-secondary"  target="_blank" rel="noopener">' . __( 'Learn More', 'astra' ) . '</a>',
						'settings' => array(),
						'divider'  => array( 'ast_class' => 'ast-bottom-divider' ),
					),
				);

				$configurations = array_merge( $configurations, $config );
			}

			return $configurations;
		}
	}
}

new Astra_Site_Container_Layout_Configs();
