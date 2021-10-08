<?php
/**
 * Loader Functions
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

/**
 * Theme Enqueue Scripts
 */
if ( ! class_exists( 'Astra_Enqueue_Scripts' ) ) {

	/**
	 * Theme Enqueue Scripts
	 */
	class Astra_Enqueue_Scripts {

		/**
		 * Class styles.
		 *
		 * @access public
		 * @var $styles Enqueued styles.
		 */
		public static $styles;

		/**
		 * Class scripts.
		 *
		 * @access public
		 * @var $scripts Enqueued scripts.
		 */
		public static $scripts;

		/**
		 * Constructor
		 */
		public function __construct() {

			add_action( 'astra_get_fonts', array( $this, 'add_fonts' ), 1 );
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ), 1 );
			add_action( 'enqueue_block_editor_assets', array( $this, 'gutenberg_assets' ) );
			add_filter( 'admin_body_class', array( $this, 'admin_body_class' ) );
			add_action( 'wp_print_footer_scripts', array( $this, 'astra_skip_link_focus_fix' ) );
			add_filter( 'gallery_style', array( $this, 'enqueue_galleries_style' ) );
		}

		/**
		 * Fix skip link focus in IE11.
		 *
		 * This does not enqueue the script because it is tiny and because it is only for IE11,
		 * thus it does not warrant having an entire dedicated blocking script being loaded.
		 *
		 * @link https://git.io/vWdr2
		 * @link https://github.com/WordPress/twentynineteen/pull/47/files
		 * @link https://github.com/ampproject/amphtml/issues/18671
		 */
		public function astra_skip_link_focus_fix() {
			// Skip printing script on AMP content, since accessibility fix is covered by AMP framework.
			if ( astra_is_amp_endpoint() ) {
				return;
			}

			// The following is minified via `terser --compress --mangle -- js/skip-link-focus-fix.js`.
			?>
			<script>
			/(trident|msie)/i.test(navigator.userAgent)&&document.getElementById&&window.addEventListener&&window.addEventListener("hashchange",function(){var t,e=location.hash.substring(1);/^[A-z0-9_-]+$/.test(e)&&(t=document.getElementById(e))&&(/^(?:a|select|input|button|textarea)$/i.test(t.tagName)||(t.tabIndex=-1),t.focus())},!1);
			</script>
			<?php
		}

		/**
		 * Admin body classes.
		 *
		 * Body classes to be added to <body> tag in admin page
		 *
		 * @param String $classes body classes returned from the filter.
		 * @return String body classes to be added to <body> tag in admin page
		 */
		public function admin_body_class( $classes ) {

			global $pagenow;
			$screen = get_current_screen();

			if ( ( ( 'post-new.php' == $pagenow || 'post.php' == $pagenow ) && ( defined( 'ASTRA_ADVANCED_HOOKS_POST_TYPE' ) && ASTRA_ADVANCED_HOOKS_POST_TYPE == $screen->post_type ) ) || 'widgets.php' == $pagenow ) {
				return;
			}

			$post_id = get_the_ID();

			if ( $post_id ) {
				$meta_content_layout = get_post_meta( $post_id, 'site-content-layout', true );
			}

			if ( ( isset( $meta_content_layout ) && ! empty( $meta_content_layout ) ) && 'default' !== $meta_content_layout ) {
				$content_layout = $meta_content_layout;
			} else {
				$content_layout = astra_get_option( 'site-content-layout' );
			}

			if ( 'content-boxed-container' == $content_layout ) {
				$classes .= ' ast-separate-container';
			} elseif ( 'boxed-container' == $content_layout ) {
				$classes .= ' ast-separate-container ast-two-container';
			} elseif ( 'page-builder' == $content_layout ) {
				$classes .= ' ast-page-builder-template';
			} elseif ( 'plain-container' == $content_layout ) {
				$classes .= ' ast-plain-container';
			}

			$site_layout = astra_get_option( 'site-layout' );
			if ( 'ast-box-layout' === $site_layout ) {
				$classes .= ' ast-max-width-layout';
			}

			$classes .= ' ast-' . astra_page_layout();

			return $classes;
		}

		/**
		 * List of all assets.
		 *
		 * @return array assets array.
		 */
		public static function theme_assets() {

			$default_assets = array(
				// handle => location ( in /assets/js/ ) ( without .js ext).
				'js'  => array(
					'astra-theme-js' => 'style',
				),
				// handle => location ( in /assets/css/ ) ( without .css ext).
				'css' => array(
					'astra-theme-css' => Astra_Builder_Helper::apply_flex_based_css() ? 'style-flex' : 'style',
				),
			);

			if ( true === Astra_Builder_Helper::$is_header_footer_builder_active ) {

				$default_assets = array(
					// handle => location ( in /assets/js/ ) ( without .js ext).
					'js'  => array(
						'astra-theme-js' => 'frontend',
					),
					// handle => location ( in /assets/css/ ) ( without .css ext).
					'css' => array(
						'astra-theme-css' => Astra_Builder_Helper::apply_flex_based_css() ? 'main' : 'frontend',
					),
				);

				if ( defined( 'ASTRA_EXT_VER' ) && version_compare( ASTRA_EXT_VER, '3.5.9', '<' ) ) {
					$default_assets['js']['astra-theme-js-pro'] = 'frontend-pro';
				}

				if ( Astra_Builder_Helper::is_component_loaded( 'edd-cart', 'header' ) ||
					Astra_Builder_Helper::is_component_loaded( 'woo-cart', 'header' ) ) {
					$default_assets['js']['astra-mobile-cart'] = 'mobile-cart';
				}
			}
			return apply_filters( 'astra_theme_assets', $default_assets );
		}

		/**
		 * Add Fonts
		 */
		public function add_fonts() {

			$font_family  = astra_get_option( 'body-font-family' );
			$font_weight  = astra_get_option( 'body-font-weight' );
			$font_variant = astra_get_option( 'body-font-variant' );

			Astra_Fonts::add_font( $font_family, $font_weight );
			Astra_Fonts::add_font( $font_family, $font_variant );

			// Render headings font.
			$heading_font_family  = astra_get_option( 'headings-font-family' );
			$heading_font_weight  = astra_get_option( 'headings-font-weight' );
			$heading_font_variant = astra_get_option( 'headings-font-variant' );

			Astra_Fonts::add_font( $heading_font_family, $heading_font_weight );
			Astra_Fonts::add_font( $heading_font_family, $heading_font_variant );
		}

		/**
		 * Enqueue Scripts
		 */
		public function enqueue_scripts() {

			if ( false === self::enqueue_theme_assets() ) {
				return;
			}

			/* Directory and Extension */
			$file_prefix = ( SCRIPT_DEBUG ) ? '' : '.min';
			$dir_name    = ( SCRIPT_DEBUG ) ? 'unminified' : 'minified';

			$js_uri  = ASTRA_THEME_URI . 'assets/js/' . $dir_name . '/';
			$css_uri = ASTRA_THEME_URI . 'assets/css/' . $dir_name . '/';

			/**
			 * IE Only Js and CSS Files.
			 */
			// Flexibility.js for flexbox IE10 support.
			wp_enqueue_script( 'astra-flexibility', $js_uri . 'flexibility' . $file_prefix . '.js', array(), ASTRA_THEME_VERSION, false );
			wp_add_inline_script( 'astra-flexibility', 'flexibility(document.documentElement);' );
			wp_script_add_data( 'astra-flexibility', 'conditional', 'IE' );

			// Polyfill for CustomEvent for IE.
			wp_register_script( 'astra-customevent', $js_uri . 'custom-events-polyfill' . $file_prefix . '.js', array(), ASTRA_THEME_VERSION, false );
			wp_register_style( 'astra-galleries-css', $css_uri . 'galleries' . $file_prefix . '.css', array(), ASTRA_THEME_VERSION, 'all' );
			// All assets.
			$all_assets = self::theme_assets();
			$styles     = $all_assets['css'];
			$scripts    = $all_assets['js'];

			if ( is_array( $styles ) && ! empty( $styles ) ) {
				// Register & Enqueue Styles.
				foreach ( $styles as $key => $style ) {

					$dependency = array();

					// Add dynamic CSS dependency for all styles except for theme's style.css.
					if ( 'astra-theme-css' !== $key && class_exists( 'Astra_Cache_Base' ) ) {
						if ( ! Astra_Cache_Base::inline_assets() ) {
							$dependency[] = 'astra-theme-dynamic';
						}
					}

					// Generate CSS URL.
					$css_file = $css_uri . $style . $file_prefix . '.css';

					// Register.
					wp_register_style( $key, $css_file, $dependency, ASTRA_THEME_VERSION, 'all' );

					// Enqueue.
					wp_enqueue_style( $key );

					// RTL support.
					wp_style_add_data( $key, 'rtl', 'replace' );
				}
			}

			// Fonts - Render Fonts.
			Astra_Fonts::render_fonts();

			/**
			 * Inline styles
			 */

			add_filter( 'astra_dynamic_theme_css', array( 'Astra_Dynamic_CSS', 'return_output' ) );
			add_filter( 'astra_dynamic_theme_css', array( 'Astra_Dynamic_CSS', 'return_meta_output' ) );

			$menu_animation = astra_get_option( 'header-main-submenu-container-animation' );

			// Submenu Container Animation for header builder.
			if ( true === Astra_Builder_Helper::$is_header_footer_builder_active ) {

				for ( $index = 1; $index <= Astra_Builder_Helper::$component_limit; $index++ ) {

					$menu_animation_enable = astra_get_option( 'header-menu' . $index . '-submenu-container-animation' );

					if ( Astra_Builder_Helper::is_component_loaded( 'menu-' . $index, 'header' ) && ! empty( $menu_animation_enable ) ) {
						$menu_animation = 'is_animated';
						break;
					}
				}
			}

			$rtl = ( is_rtl() ) ? '-rtl' : '';

			if ( ! empty( $menu_animation ) || is_customize_preview() ) {
				if ( class_exists( 'Astra_Cache' ) ) {
					Astra_Cache::add_css_file( ASTRA_THEME_DIR . 'assets/css/' . $dir_name . '/menu-animation' . $rtl . $file_prefix . '.css' );
				} else {
					wp_register_style( 'astra-menu-animation', $css_uri . 'menu-animation' . $file_prefix . '.css', null, ASTRA_THEME_VERSION, 'all' );
					wp_enqueue_style( 'astra-menu-animation' );
				}
			}

			if ( ! class_exists( 'Astra_Cache' ) ) {
				$theme_css_data = apply_filters( 'astra_dynamic_theme_css', '' );
				wp_add_inline_style( 'astra-theme-css', $theme_css_data );
			}

			if ( astra_is_amp_endpoint() ) {
				return;
			}

			// Comment assets.
			if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
				wp_enqueue_script( 'comment-reply' );
			}

			if ( is_array( $scripts ) && ! empty( $scripts ) ) {

				// Register & Enqueue Scripts.
				foreach ( $scripts as $key => $script ) {

					// Register.
					wp_register_script( $key, $js_uri . $script . $file_prefix . '.js', array(), ASTRA_THEME_VERSION, true );

					// Enqueue.
					wp_enqueue_script( $key );
				}
			}

			$astra_localize = array(
				'break_point' => astra_header_break_point(),    // Header Break Point.
				'isRtl'       => is_rtl(),
			);

			wp_localize_script( 'astra-theme-js', 'astra', apply_filters( 'astra_theme_js_localize', $astra_localize ) );
		}

		/**
		 * Trim CSS
		 *
		 * @since 1.0.0
		 * @param string $css CSS content to trim.
		 * @return string
		 */
		public static function trim_css( $css = '' ) {

			// Trim white space for faster page loading.
			if ( ! empty( $css ) ) {
				$css = preg_replace( '!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css );
				$css = str_replace( array( "\r\n", "\r", "\n", "\t", '  ', '    ', '    ' ), '', $css );
				$css = str_replace( ', ', ',', $css );
			}

			return $css;
		}

		/**
		 * Enqueue Gutenberg assets.
		 *
		 * @since 1.5.2
		 *
		 * @return void
		 */
		public function gutenberg_assets() {
			/* Directory and Extension */
			$rtl = '';
			if ( is_rtl() ) {
				$rtl = '-rtl';
			}

			$css_uri = ASTRA_THEME_URI . 'inc/assets/css/block-editor-styles' . $rtl . '.css';
			$js_uri  = ASTRA_THEME_URI . 'inc/assets/js/block-editor-script.js';
			/** @psalm-suppress InvalidArgument */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			wp_enqueue_style( 'astra-block-editor-styles', $css_uri, false, ASTRA_THEME_VERSION, 'all' );
			/** @psalm-suppress InvalidArgument */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
			wp_enqueue_script( 'astra-block-editor-script', $js_uri, false, ASTRA_THEME_VERSION, 'all' );

			// Render fonts in Gutenberg layout.
			Astra_Fonts::render_fonts();

			wp_add_inline_style( 'astra-block-editor-styles', apply_filters( 'astra_block_editor_dynamic_css', Gutenberg_Editor_CSS::get_css() ) );
		}

		/**
		 * Function to check if enqueuing of Astra assets are disabled.
		 *
		 * @since 2.1.0
		 * @return boolean
		 */
		public static function enqueue_theme_assets() {
			return apply_filters( 'astra_enqueue_theme_assets', true );
		}

		/**
		 * Enqueue galleries relates CSS on gallery_style filter.
		 *
		 * @param string $gallery_style gallery style and div.
		 * @since 3.5.0
		 * @return string
		 */
		public function enqueue_galleries_style( $gallery_style ) {
			wp_enqueue_style( 'astra-galleries-css' );
			return $gallery_style;
		}

	}

	new Astra_Enqueue_Scripts();
}
