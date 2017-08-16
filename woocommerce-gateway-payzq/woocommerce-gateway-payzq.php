<?php
/*
 * Plugin Name: WooCommerce PayZQ Gateway
 * Plugin URI: https://wordpress.org/plugins/woocommerce-gateway-payzq/
 * Description: PayZQ Take credit card payments on your store.
 * Author: PayZQ
 * Author URI: https://payzq.net/
 * Version: 1.0.0
 * Requires at least: 4.4
 * Tested up to: 4.8
 * Text Domain: woocommerce-gateway-payzq
 * Domain Path: /languages
 *
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_PAYZQ_VERSION', '1.0.0' );
define( 'WC_PAYZQ_MIN_PHP_VER', '5.6.0' );
define( 'WC_PAYZQ_MIN_WC_VER', '2.5.0' );
define( 'WC_PAYZQ_MAIN_FILE', __FILE__ );
define( 'WC_PAYZQ_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_PAYZQ_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'WC_PayZQ' ) ) :

	class WC_PayZQ {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * @var Reference to logging class.
		 */
		private static $log;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {}

		/**
		 * Flag to indicate whether or not we need to load code for / support subscriptions.
		 *
		 * @var bool
		 */
		private $subscription_support_enabled = false;

		/**
		 * Flag to indicate whether or not we need to load support for pre-orders.
		 *
		 * @since 3.0.3
		 *
		 * @var bool
		 */
		private $pre_order_enabled = false;

		/**
		 * Notices (array)
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}

			include_once( dirname( __FILE__ ) . '/includes/class-wc-payzq-api.php' );
			include_once( dirname( __FILE__ ) . '/includes/class-wc-payzq-jwt.php' );
			include_once( dirname( __FILE__ ) . '/includes/class-wc-payzq-curl.php' );

			// Init the gateway itself
			$this->init_gateways();

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			// add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
			// add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
			// add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
			// add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
			// add_filter( 'woocommerce_get_customer_payment_tokens', array( $this, 'woocommerce_get_customer_payment_tokens' ), 10, 3 );
			// add_action( 'woocommerce_payment_token_deleted', array( $this, 'woocommerce_payment_token_deleted' ), 10, 2 );
			// add_action( 'woocommerce_payment_token_set_default', array( $this, 'woocommerce_payment_token_set_default' ) );
			add_action( 'wp_ajax_payzq_dismiss_request_api_notice', array( $this, 'dismiss_request_api_notice' ) );

			// include_once( dirname( __FILE__ ) . '/includes/class-wc-payzq-payment-request.php' );
		}

		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * The backup sanity check, in case the plugin is activated in a weird way,
		 * or the environment changes after activation. Also handles upgrade routines.
		 */
		public function check_environment() {
			if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_PAYZQ_VERSION !== get_option( 'wc_payzq_version' ) ) ) {
				$this->install();

				do_action( 'woocommerce_payzq_updated' );
			}

			$environment_warning = self::get_environment_warning();

			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}

			// Check if secret key present. Otherwise prompt, via notice, to go to
			// setting.
			if ( ! class_exists( 'WC_PayZQ_API' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-payzq-api.php' );
			}

			$secret = WC_PayZQ_API::get_secret_key();

			if ( empty( $secret ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'payzq' === $_GET['section'] ) ) {
				$setting_link = $this->get_setting_link();
				$this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'PayZQ is almost ready. To get started, <a href="%s">set your PayZQ account keys</a>.', 'woocommerce-gateway-payzq' ), $setting_link ) );
			}
		}

		/**
		 * Updates the plugin version in db
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 * @return bool
		 */
		private static function _update_plugin_version() {
			delete_option( 'wc_payzq_version' );
			update_option( 'wc_payzq_version', WC_PAYZQ_VERSION );

			return true;
		}

		/**
		 * Dismiss the Google Payment Request API Feature notice.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function dismiss_request_api_notice() {
			update_option( 'wc_payzq_show_request_api_notice', 'no' );
		}

		/**
		 * Handles upgrade routines.
		 *
		 * @since 3.1.0
		 * @version 3.1.0
		 */
		public function install() {
			if ( ! defined( 'WC_PAYZQ_INSTALLING' ) ) {
				define( 'WC_PAYZQ_INSTALLING', true );
			}

			$this->_update_plugin_version();
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		static function get_environment_warning() {
			if ( version_compare( phpversion(), WC_PAYZQ_MIN_PHP_VER, '<' ) ) {
				$message = __( 'WooCommerce PayZQ - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-payzq' );

				return sprintf( $message, WC_PAYZQ_MIN_PHP_VER, phpversion() );
			}

			if ( ! defined( 'WC_VERSION' ) ) {
				return __( 'WooCommerce PayZQ requires WooCommerce to be activated to work.', 'woocommerce-gateway-payzq' );
			}

			if ( version_compare( WC_VERSION, WC_PAYZQ_MIN_WC_VER, '<' ) ) {
				$message = __( 'WooCommerce PayZQ - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-payzq' );

				return sprintf( $message, WC_PAYZQ_MIN_WC_VER, WC_VERSION );
			}

			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'WooCommerce PayZQ - cURL is not installed.', 'woocommerce-gateway-payzq' );
			}

			return false;
		}

		/**
		 * Adds plugin action links
		 *
		 * @since 1.0.0
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-gateway-payzq' ) . '</a>',
				'<a href="https://docs.woocommerce.com/document/payzq/">' . __( 'Docs', 'woocommerce-gateway-payzq' ) . '</a>',
				'<a href="http://payzq.net/">' . __( 'Support', 'woocommerce-gateway-payzq' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;

			$section_slug = $use_id_as_section ? 'payzq' : strtolower( 'WC_Gateway_PayZQ' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.0
		 */
		public function init_gateways() {
			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$this->subscription_support_enabled = true;
			}

			if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
				$this->pre_order_enabled = true;
			}

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			include_once( dirname( __FILE__ ) . '/includes/legacy/class-wc-gateway-payzq.php' );

			load_plugin_textdomain( 'woocommerce-gateway-payzq', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @since 1.0.0
		 */
		public function add_gateways( $methods ) {
				$methods[] = 'WC_Gateway_PayZQ';
			return $methods;
		}

		/**
		 * @param object $balance_transaction
		 * @param string $type Type of number to format
		 */
		public static function format_number( $balance_transaction, $type = 'fee' ) {
			if ( ! is_object( $balance_transaction ) ) {
				return;
			}

			if ( 'fee' === $type ) {
				return number_format( $balance_transaction->fee / 100, 2, '.', '' );
			}

			return number_format( $balance_transaction->net / 100, 2, '.', '' );
		}


		/**
		 * What rolls down stairs
		 * alone or in pairs,
		 * and over your neighbor's dog?
		 * What's great for a snack,
		 * And fits on your back?
		 * It's log, log, log
		 */
		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			self::$log->add( 'woocommerce-gateway-payzq', $message );
		}
	}

	$GLOBALS['wc_payzq'] = WC_PayZQ::get_instance();

endif;
