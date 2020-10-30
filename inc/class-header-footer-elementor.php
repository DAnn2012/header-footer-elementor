<?php
/**
 * Entry point for the plugin. Checks if Elementor is installed and activated and loads it's own files and actions.
 *
 * @package header-footer-elementor
 */

use HFE\Lib\Astra_Target_Rules_Fields;

/**
 * Class Header_Footer_Elementor
 */
class Header_Footer_Elementor {

	/**
	 * Current theme template
	 *
	 * @var String
	 */
	public $template;

	/**
	 * Instance of Elemenntor Frontend class.
	 *
	 * @var \Elementor\Frontend()
	 */
	private static $elementor_instance;

	/**
	 * Instance of HFE_Admin
	 *
	 * @var Header_Footer_Elementor
	 */
	private static $_instance = null;

	/**
	 * Instance of Header_Footer_Elementor
	 *
	 * @return Header_Footer_Elementor Instance of Header_Footer_Elementor
	 */
	public static function instance() {
		if ( ! isset( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}
	/**
	 * Constructor
	 */
	function __construct() {
		$this->template = get_template();

		if ( defined( 'ELEMENTOR_VERSION' ) && is_callable( 'Elementor\Plugin::instance' ) ) {
			self::$elementor_instance = Elementor\Plugin::instance();

			$this->includes();
			$this->load_textdomain();

			if ( 'genesis' == $this->template ) {
				require HFE_DIR . 'themes/genesis/class-hfe-genesis-compat.php';
			} elseif ( 'astra' == $this->template ) {
				require HFE_DIR . 'themes/astra/class-hfe-astra-compat.php';

				add_filter(
					'astra_customizer_configurations',
					function( $configurations, $wp_customize ) {

						if ( class_exists( 'Astra_Builder_Helper' ) && Astra_Builder_Helper::$is_header_footer_builder_active ) {

							$hf_builder_notices = [];

							if ( hfe_header_enabled() ) {
								/* translators: %s: Links to to plugin */
								$message = sprintf( __( 'The header on the page you are previewing is built using %3$s plugin. Options given below will not work here.</p><p></br> %1$s Click here %2$s to modify the header on this page.', 'astra' ), '<a href="' . admin_url( 'edit.php?post_type=elementor-hf' ) . '" target="_blank">', '</a>', '<strong>Elementor - Header, Footer & Blocks</strong>' );

								array_push(
									$hf_builder_notices,
									[
										'name'     => ASTRA_THEME_SETTINGS . '[ehf-header-notice]',
										'type'     => 'control',
										'control'  => 'ast-description',
										'section'  => 'section-header-builder-layout',
										'priority' => 1,
										'help'     => '<div class="ast-customizer-notice wp-ui-highlight"><p>' . $message . '<p></div>',
									]
								);

								$configurations = array_merge( $configurations, $hf_builder_notices );
							}

							if ( hfe_footer_enabled() ) {
								/* translators: %s: Links to to plugin */
								$message = sprintf( __( 'The footer on the page you are previewing is built using %3$s plugin. Options given below will not work here.</p><p></br> %1$s Click here %2$s to modify the footer on this page.', 'astra' ), '<a href="' . admin_url( 'edit.php?post_type=elementor-hf' ) . '" target="_blank">', '</a>', '<strong>Elementor - Header, Footer & Blocks</strong>' );

								array_push(
									$hf_builder_notices,
									[
										'name'     => ASTRA_THEME_SETTINGS . '[ehf-footer-notice]',
										'type'     => 'control',
										'control'  => 'ast-description',
										'section'  => 'section-footer-builder-layout',
										'priority' => 1,
										'help'     => '<div class="ast-customizer-notice wp-ui-highlight"><p>' . $message . '<p></div>',
									]
								);

								$configurations = array_merge( $configurations, $hf_builder_notices );
							}
						}

						return $configurations;
					},
					31,
					2
				);
			} elseif ( 'bb-theme' == $this->template || 'beaver-builder-theme' == $this->template ) {
				$this->template = 'beaver-builder-theme';
				require HFE_DIR . 'themes/bb-theme/class-hfe-bb-theme-compat.php';
			} elseif ( 'generatepress' == $this->template ) {
				require HFE_DIR . 'themes/generatepress/class-hfe-generatepress-compat.php';
			} elseif ( 'oceanwp' == $this->template ) {
				require HFE_DIR . 'themes/oceanwp/class-hfe-oceanwp-compat.php';
			} elseif ( 'storefront' == $this->template ) {
				require HFE_DIR . 'themes/storefront/class-hfe-storefront-compat.php';
			} elseif ( 'hello-elementor' == $this->template ) {
				require HFE_DIR . 'themes/hello-elementor/class-hfe-hello-elementor-compat.php';
			} else {
				add_action( 'init', [ $this, 'setup_unsupported_theme' ] );
			}

			// Scripts and styles.
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );

			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_scripts' ] );

			add_filter( 'body_class', [ $this, 'body_class' ] );
			add_action( 'switch_theme', [ $this, 'reset_unsupported_theme_notice' ] );

			add_shortcode( 'hfe_template', [ $this, 'render_template' ] );

			add_action( 'astra_notice_before_markup_header-footer-elementor-rating', [ $this, 'rating_notice_css' ] );
			add_action( 'admin_notices', [ $this, 'register_notices' ] );

			// BSF Analytics Tracker.
			if ( ! class_exists( 'BSF_Analytics_Loader' ) ) {
				require_once HFE_DIR . 'admin/bsf-analytics/class-bsf-analytics-loader.php';
			}

			$bsf_analytics = BSF_Analytics_Loader::get_instance();

			$bsf_analytics->set_entity(
				[
					'bsf' => [
						'product_name'    => 'Elementor - Header, Footer & Blocks',
						'path'            => HFE_DIR . 'admin/bsf-analytics',
						'author'          => 'Brainstorm Force',
						'time_to_display' => '+24 hours',
					],
				]
			);

		} else {
			add_action( 'admin_notices', [ $this, 'elementor_not_available' ] );
			add_action( 'network_admin_notices', [ $this, 'elementor_not_available' ] );
		}
	}

	/**
	 * Reset the Unsupported theme nnotice after a theme is switched.
	 *
	 * @since 1.0.16
	 *
	 * @return void
	 */
	public function reset_unsupported_theme_notice() {
		delete_user_meta( get_current_user_id(), 'unsupported-theme' );
	}

	/**
	 * Register Astra Notices.
	 *
	 * @since 1.2.0
	 *
	 * @return void
	 */
	public function register_notices() {
		$image_path = HFE_URL . 'assets/images/header-footer-elementor-icon.svg';
		Astra_Notices::add_notice(
			[
				'id'                         => 'header-footer-elementor-rating',
				'type'                       => '',
				'message'                    => sprintf(
					'<div class="notice-image">
						<img src="%1$s" class="custom-logo" alt="Sidebar Manager" itemprop="logo"></div> 
						<div class="notice-content">
							<div class="notice-heading">
								%2$s
							</div>
							%3$s<br />
							<div class="astra-review-notice-container">
								<a href="%4$s" class="astra-notice-close astra-review-notice button-primary" target="_blank">
								%5$s
								</a>
							<span class="dashicons dashicons-calendar"></span>
								<a href="#" data-repeat-notice-after="%6$s" class="astra-notice-close astra-review-notice">
								%7$s
								</a>
							<span class="dashicons dashicons-smiley"></span>
								<a href="#" class="astra-notice-close astra-review-notice">
								%8$s
								</a>
							</div>
						</div>',
					$image_path,
					__( 'Hello! Seems like you have used Elementor - Header, Footer & Blocks to build this website — Thanks a ton!', 'header-footer-elementor' ),
					__( 'Could you please do us a BIG favor and give it a 5-star rating on WordPress? This would boost our motivation and help other users make a comfortable decision while choosing the Elementor - Header, Footer & Blocks.', 'header-footer-elementor' ),
					'https://wordpress.org/support/plugin/header-footer-elementor/reviews/?filter=5#new-post',
					__( 'Ok, you deserve it', 'header-footer-elementor' ),
					MONTH_IN_SECONDS,
					__( 'Nope, maybe later', 'header-footer-elementor' ),
					__( 'I already did', 'header-footer-elementor' )
				),
				'show_if'                    => ( hfe_header_enabled() || hfe_footer_enabled() || hfe_is_before_footer_enabled() ) ? true : false,
				'repeat-notice-after'        => MONTH_IN_SECONDS,
				'display-notice-after'       => 1296000, // Display notice after 15 days.
				'priority'                   => 18,
				'display-with-other-notices' => false,
			]
		);
	}

	/**
	 * Enqueue CSS for the Rating Notice.
	 *
	 * @since 1.2.0
	 * @return void
	 */
	public function rating_notice_css() {
		wp_enqueue_style( 'hfe-admin-style', HFE_URL . 'assets/css/admin-header-footer-elementor.css', [], HFE_VER );
	}

	/**
	 * Prints the admin notics when Elementor is not installed or activated.
	 */
	public function elementor_not_available() {

		if ( ! did_action( 'elementor/loaded' ) ) {
			// Check user capability.
			if ( ! ( current_user_can( 'activate_plugins' ) && current_user_can( 'install_plugins' ) ) ) {
				return;
			}

			/* TO DO */
			$class = 'notice notice-error';
			/* translators: %s: html tags */
			$message = sprintf( __( 'The %1$sElementor - Header Footer and Blocks%2$s plugin requires %1$sElementor%2$s plugin installed & activated.', 'header-footer-elementor' ), '<strong>', '</strong>' );

			$plugin = 'elementor/elementor.php';

			if ( file_exists( WP_PLUGIN_DIR . '/elementor/elementor.php' ) ) {

				$action_url   = wp_nonce_url( 'plugins.php?action=activate&amp;plugin=' . $plugin . '&amp;plugin_status=all&amp;paged=1&amp;s', 'activate-plugin_' . $plugin );
				$button_label = __( 'Activate Elementor', 'header-footer-elementor' );

			} else {

				$action_url   = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=elementor' ), 'install-plugin_elementor' );
				$button_label = __( 'Install Elementor', 'header-footer-elementor' );
			}

			$button = '<p><a href="' . $action_url . '" class="button-primary">' . $button_label . '</a></p><p></p>';

			printf( '<div class="%1$s"><p>%2$s</p>%3$s</div>', esc_attr( $class ), wp_kses_post( $message ), wp_kses_post( $button ) );
		}
	}

	/**
	 * Loads the globally required files for the plugin.
	 */
	public function includes() {
		require_once HFE_DIR . 'admin/class-hfe-admin.php';

		require_once HFE_DIR . 'inc/hfe-functions.php';

		// Load Elementor Canvas Compatibility.
		require_once HFE_DIR . 'inc/class-hfe-elementor-canvas-compat.php';

		// Load WPML & Polylang Compatibility if WPML is installed and activated.
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || defined( 'POLYLANG_BASENAME' ) ) {
			require_once HFE_DIR . 'inc/compatibility/class-hfe-wpml-compatibility.php';
		}

		// Load the Admin Notice Class.
		require_once HFE_DIR . 'inc/lib/notices/class-astra-notices.php';

		// Load Target rules.
		require_once HFE_DIR . 'inc/lib/target-rule/class-astra-target-rules-fields.php';
		// Setup upgrade routines.
		require_once HFE_DIR . 'inc/class-hfe-update.php';

		// Load the widgets.
		require HFE_DIR . 'inc/widgets-manager/class-widgets-loader.php';
	}

	/**
	 * Loads textdomain for the plugin.
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'header-footer-elementor' );
	}

	/**
	 * Enqueue styles and scripts.
	 */
	public function enqueue_scripts() {
		wp_enqueue_style( 'hfe-style', HFE_URL . 'assets/css/header-footer-elementor.css', [], HFE_VER );

		if ( class_exists( '\Elementor\Plugin' ) ) {
			$elementor = \Elementor\Plugin::instance();
			$elementor->frontend->enqueue_styles();
		}

		if ( class_exists( '\ElementorPro\Plugin' ) ) {
			$elementor_pro = \ElementorPro\Plugin::instance();
			$elementor_pro->enqueue_styles();
		}

		if ( hfe_header_enabled() ) {
			if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				$css_file = new \Elementor\Core\Files\CSS\Post( get_hfe_header_id() );
			} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
				$css_file = new \Elementor\Post_CSS_File( get_hfe_header_id() );
			}

			$css_file->enqueue();
		}

		if ( hfe_footer_enabled() ) {
			if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				$css_file = new \Elementor\Core\Files\CSS\Post( get_hfe_footer_id() );
			} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
				$css_file = new \Elementor\Post_CSS_File( get_hfe_footer_id() );
			}

			$css_file->enqueue();
		}

		if ( hfe_is_before_footer_enabled() ) {
			if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
				$css_file = new \Elementor\Core\Files\CSS\Post( hfe_get_before_footer_id() );
			} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
				$css_file = new \Elementor\Post_CSS_File( hfe_get_before_footer_id() );
			}
			$css_file->enqueue();
		}
	}

	/**
	 * Load admin styles on header footer elementor edit screen.
	 */
	public function enqueue_admin_scripts() {
		global $pagenow;
		$screen = get_current_screen();

		if ( ( 'elementor-hf' == $screen->id && ( 'post.php' == $pagenow || 'post-new.php' == $pagenow ) ) || ( 'edit.php' == $pagenow && 'edit-elementor-hf' == $screen->id ) ) {
			wp_enqueue_style( 'hfe-admin-style', HFE_URL . 'admin/assets/css/ehf-admin.css', [], HFE_VER );
			wp_enqueue_script( 'hfe-admin-script', HFE_URL . 'admin/assets/js/ehf-admin.js', [], HFE_VER );
		}
	}

	/**
	 * Adds classes to the body tag conditionally.
	 *
	 * @param  Array $classes array with class names for the body tag.
	 *
	 * @return Array          array with class names for the body tag.
	 */
	public function body_class( $classes ) {
		if ( hfe_header_enabled() ) {
			$classes[] = 'ehf-header';
		}

		if ( hfe_footer_enabled() ) {
			$classes[] = 'ehf-footer';
		}

		$classes[] = 'ehf-template-' . $this->template;
		$classes[] = 'ehf-stylesheet-' . get_stylesheet();

		return $classes;
	}

	/**
	 * Display Unsupported theme notice if the current theme does add support for 'header-footer-elementor'
	 *
	 * @since  1.0.3
	 */
	public function setup_unsupported_theme() {
		if ( ! current_theme_supports( 'header-footer-elementor' ) ) {
			require_once HFE_DIR . 'themes/default/class-hfe-fallback-theme-support.php';
		}
	}

	/**
	 * Prints the Header content.
	 */
	public static function get_header_content() {
		echo self::$elementor_instance->frontend->get_builder_content_for_display( get_hfe_header_id() );
	}

	/**
	 * Prints the Footer content.
	 */
	public static function get_footer_content() {
		echo "<div class='footer-width-fixer'>";
		echo self::$elementor_instance->frontend->get_builder_content_for_display( get_hfe_footer_id() );
		echo '</div>';
	}

	/**
	 * Prints the Before Footer content.
	 */
	public static function get_before_footer_content() {
		echo "<div class='footer-width-fixer'>";
		echo self::$elementor_instance->frontend->get_builder_content_for_display( hfe_get_before_footer_id() );
		echo '</div>';
	}

	/**
	 * Get option for the plugin settings
	 *
	 * @param  mixed $setting Option name.
	 * @param  mixed $default Default value to be received if the option value is not stored in the option.
	 *
	 * @return mixed.
	 */
	public static function get_settings( $setting = '', $default = '' ) {
		if ( 'type_header' == $setting || 'type_footer' == $setting || 'type_before_footer' == $setting ) {
			$templates = self::get_template_id( $setting );

			$template = ! is_array( $templates ) ? $templates : $templates[0];

			$template = apply_filters( "hfe_get_settings_{$setting}", $template );

			return $template;
		}
	}

	/**
	 * Get header or footer template id based on the meta query.
	 *
	 * @param  String $type Type of the template header/footer.
	 *
	 * @return Mixed       Returns the header or footer template id if found, else returns string ''.
	 */
	public static function get_template_id( $type ) {
		$option = [
			'location'  => 'ehf_target_include_locations',
			'exclusion' => 'ehf_target_exclude_locations',
			'users'     => 'ehf_target_user_roles',
		];

		$hfe_templates = Astra_Target_Rules_Fields::get_instance()->get_posts_by_conditions( 'elementor-hf', $option );

		foreach ( $hfe_templates as $template ) {
			if ( get_post_meta( absint( $template['id'] ), 'ehf_template_type', true ) === $type ) {
				return $template['id'];
			}
		}

		return '';
	}

	/**
	 * Callback to shortcode.
	 *
	 * @param array $atts attributes for shortcode.
	 */
	public function render_template( $atts ) {
		$atts = shortcode_atts(
			[
				'id' => '',
			],
			$atts,
			'hfe_template'
		);

		$id = ! empty( $atts['id'] ) ? apply_filters( 'hfe_render_template_id', intval( $atts['id'] ) ) : '';

		if ( empty( $id ) ) {
			return '';
		}

		if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
			$css_file = new \Elementor\Core\Files\CSS\Post( $id );
		} elseif ( class_exists( '\Elementor\Post_CSS_File' ) ) {
			// Load elementor styles.
			$css_file = new \Elementor\Post_CSS_File( $id );
		}
			$css_file->enqueue();

		return self::$elementor_instance->frontend->get_builder_content_for_display( $id );
	}

}
