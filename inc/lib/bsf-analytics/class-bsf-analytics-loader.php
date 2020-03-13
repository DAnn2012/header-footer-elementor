<?php

if ( ! class_exists( 'BSF_Analytics' ) ) {

	class BSF_Analytics {

		private static $products = array();

		private static $_instance = null;

		public static function instance() {
			if ( ! isset( self::$_instance ) ) {
				self::$_instance = new self();
			}

			return self::$_instance;
		}

		public function __construct() {
			add_action( 'admin_init', array( $this, 'handle_optin_optout' ) );
			add_action( 'cron_schedules', array( $this, 'every_two_days_schedule' ) );
			add_action( 'admin_notices', array( $this, 'option_notice' ) );

			if ( ! has_action( 'bsf_analytics_send', array( $this, 'send' ) ) ) {
				add_action( 'bsf_analytics_send', array( $this, 'send' ) );
			}

			$this->includes();
			$this->schedule_event();
		}

		public static function register_product( $slug, $name, $type ) {
			self::$products[] = array(
				'slug' => $slug,
				'name' => $name,
				'type' => $type,
			);
		}

		private function get_api_url() {
			return defined( 'BSF_API_URL' ) ? BSF_API_URL : 'https://support.brainstormforce.com/';
		}

		public function send() {
			wp_remote_post(
				$this->get_api_url() . 'wp-json/bsf-core/v1/analytics/',
				array(
					'body'     => BSF_Analytics_Stats::instance()->get_stats(),
					'timeout'  => 5,
					'blocking' => false,
				)
			);
		}

		public function is_tracking_enabled() {
			return ( get_option( 'bsf_analytics_optin' ) === 'yes' ) ? true : false; 
		}

		public function option_notice() {

			// Don't display the notice if the user has taken action on the notice.
			if ( get_option( 'bsf_analytics_optin' ) ) {
				return;
			}

			Astra_Notices::add_notice(
				array(
					'id'                         => 'bsf-optin-notice',
					'type'                       => '',
					'message'                    => sprintf(
						'<div class="notice-content">
								<div class="notice-heading">
									%1$s
								</div>
								<div class="astra-notices-container">
									<a href="%3$s" class="astra-notices button-primary">
									%4$s
									</a> &nbsp; 
									<a href="%5$s" data-repeat-notice-after="%6$s" class="astra-notices button-secondary">
									%7$s
									</a>
								</div>
							</div>',
						sprintf( __( 'Want to help make <b>%1$s</b> even more awesome? Allow us to collect non-sensitive diagnostic data and usage information', 'header-footer-elementor' ), '' ),
						__( 'Could you please do us a BIG favor and give it a 5-star rating on WordPress? This would boost our motivation and help other users make a comfortable decision while choosing the Elementor - Header, Footer & Blocks.', 'header-footer-elementor' ),
						add_query_arg(
							array(
								'bsf_analytics_optin' => 'yes',
								'bsf_analytics_nonce' => wp_create_nonce( 'bsf_analytics_optin' ),
							)
						),
						__( 'Allow', 'header-footer-elementor' ),
						add_query_arg(
							array(
								'bsf_analytics_optin' => 'no',
								'bsf_analytics_nonce' => wp_create_nonce( 'bsf_analytics_optin' ),
							)
						),
						MONTH_IN_SECONDS,
						__( 'No Thanks', 'header-footer-elementor' )
					),
					'show_if'                    => true,
					'repeat-notice-after'        => false,
					// 'display-notice-after'       => 1296000, // Display notice after 15 days.
					'priority'                   => 18,
					'display-with-other-notices' => true,
				)
			);
		}

		public function handle_optin_optout() {
			if ( ! isset( $_GET['bsf_analytics_nonce'] ) ) {
				return;
			}

			if ( ! wp_verify_nonce( sanitize_text_field( $_GET['bsf_analytics_nonce'] ), 'bsf_analytics_optin' ) ) {
				return;
			}

			$optin_status = sanitize_text_field( $_GET['bsf_analytics_optin'] );

			if ( 'yes' == $optin_status ) {
				$this->optin();
			} elseif ( 'no' == $optin_status ) {
				$this->optout();
			}

			wp_redirect(
				remove_query_arg(
					array(
						'bsf_analytics_optin',
						'bsf_analytics_product',
						'bsf_analytics_nonce',
					)
				)
			);
		}

		private function optin() {
			$this->unschedule_event();
			$this->schedule_event();
			update_option( 'bsf_analytics_optin', 'yes' );
		}

		private function optout() {
			$this->unschedule_event();
			update_option( 'bsf_analytics_optin', 'no' );
		}

		public function every_two_days_schedule( $schedules ) {
			$schedules['every_two_days'] = array(
				'interval' => 2 * DAY_IN_SECONDS,
				'display'  => __( 'Every two days', 'textdomain' ),
			);

			return $schedules;
		}

		private function schedule_event() {
			if ( ! wp_next_scheduled( 'bsf_analytics_send' ) && $this->is_tracking_enabled() ) {
				wp_schedule_event( time(), 'every_two_days', 'bsf_analytics_send' );
			}
		}

		private function unschedule_event() {
			wp_clear_scheduled_hook( 'bsf_analytics_send' );
		}

		private function includes() {
			require_once __DIR__ . '/class-bsf-analytics-stats.php';
		}

	}

}

BSF_Analytics::instance();
