<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_PayZQ class.
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_PayZQ extends WC_Payment_Gateway {

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'payzq';
		$this->method_title         = __( 'PayZQ', 'woocommerce-gateway-payzq' );
		$this->method_description   = __( 'PayZQ works by adding credit card fields on the checkout and then sending the details for verification.', 'woocommerce-gateway-payzq' );
		$this->has_fields           = true;
		$this->view_transaction_url = 'https://payzq.net';
		$this->supports             = array(
			// 'subscriptions',
			'products',
			'refunds',
			// 'subscription_cancellation',
			// 'subscription_reactivation',
			// 'subscription_suspension',
			// 'subscription_amount_changes',
			// 'subscription_payment_method_change', // Subs 1.n compatibility
			// 'subscription_payment_method_change_customer',
			// 'subscription_payment_method_change_admin',
			// 'subscription_date_changes',
			// 'multiple_subscriptions',
			'pre-orders',
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                  = $this->get_option( 'title' );
		$this->description            = $this->get_option( 'description' );
		$this->enabled                = $this->get_option( 'enabled' );
		$this->testmode               = 'yes' === $this->get_option( 'testmode' );
		$this->capture                = true;
		$this->secret_key             = $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->merchant_key           = $this->get_option( 'merchant_key' );
		$this->logging                = 'yes' === $this->get_option( 'logging' );

		if ( $this->testmode ) {
			$this->description .= ' ' . sprintf( __( 'Test mode activated', 'woocommerce-gateway-payzq' ), '' );
			$this->description  = trim( $this->description );
		}

		WC_PayZQ_API::set_secret_key( $this->secret_key );

		// Hooks
		// add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
	}

	/**
	 * get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$ext   = version_compare( WC()->version, '2.6', '>=' ) ? '.svg' : '.png';
		$style = version_compare( WC()->version, '2.6', '>=' ) ? 'style="margin-left: 0.3em"' : '';

		$icon  = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa' . $ext ) . '" alt="Visa" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard' . $ext ) . '" alt="Mastercard" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex' . $ext ) . '" alt="Amex" width="32" ' . $style . ' />';

		if ( 'USD' === get_woocommerce_currency() ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover' . $ext ) . '" alt="Discover" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb' . $ext ) . '" alt="JCB" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners' . $ext ) . '" alt="Diners" width="32" ' . $style . ' />';
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */
	public function admin_notices() {
		if ( $this->enabled == 'no' ) {
			return;
		}

		$addons = ( class_exists( 'WC_Subscriptions_Order' ) || class_exists( 'WC_Pre_Orders_Order' ) ) ? '_addons' : '';

		// Check required fields
		if ( ! $this->secret_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'PayZQ error: Please enter your secret key <a href="%s">here</a>', 'woocommerce-gateway-payzq' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payzq' . $addons ) ) . '</p></div>';
			return;

		} elseif ( ! $this->merchant_key ) {
			echo '<div class="error"><p>' . sprintf( __( 'PayZQ error: Please enter your publishable key <a href="%s">here</a>', 'woocommerce-gateway-payzq' ), admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wc_gateway_payzq' . $addons ) ) . '</p></div>';
			return;
		}

		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected
		if ( ( function_exists( 'wc_site_is_https' ) && ! wc_site_is_https() ) && ! class_exists( 'WordPressHTTPS' )  ) {
			echo '<div class="error"><p>' . sprintf( __( 'PayZQ is enabled, but the <a href="%1$s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid <a href="%2$s" target="_blank">SSL certificate</a> - PayZQ will only work in test mode.', 'woocommerce-gateway-payzq' ), admin_url( 'admin.php?page=wc-settings&tab=checkout' ), 'https://en.wikipedia.org/wiki/Transport_Layer_Security' ) . '</p></div>';
		}
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->testmode && is_checkout() && ! is_ssl() ) {
				return false;
			}
			if ( ! $this->secret_key || ! $this->merchant_key ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( untrailingslashit( plugin_dir_path( WC_PAYZQ_MAIN_FILE ) ) . '/includes/settings-payzq.php' );

		wc_enqueue_js( "
			jQuery( function( $ ) {
				$( '#woocommerce_payzq_checkout' ).change(function(){
					if ( $( this ).is( ':checked' ) ) {
						$( '#woocommerce_payzq_checkout_locale, #woocommerce_payzq_checkout_image' ).closest( 'tr' ).show();
					} else {
						$( '#woocommerce_payzq_checkout_locale, #woocommerce_payzq_checkout_image' ).closest( 'tr' ).hide();
					}
				}).change();
			});
		" );
	}

	/**
	 * Payment form on checkout page
	 */
	public function payment_fields() {
		?>
		<fieldset class="stripe-legacy-payment-fields">
			<?php
				if ( $this->description ) {
					echo apply_filters( 'wc_stripe_description', wpautop( wp_kses_post( $this->description ) ) );
				}

				$user = wp_get_current_user();

				if ( $user ) {
					$user_email = get_user_meta( $user->ID, 'billing_email', true );
					$user_email = $user_email ? $user_email : $user->user_email;
				} else {
					$user_email = '';
				}

				$display = '';

				echo '<div ' . $display . ' id="stripe-payment-data"
					data-description=""
					data-email="' . esc_attr( $user_email ) . '"
					data-amount="' . esc_attr( WC()->cart->total ) . '"
					data-name="' . esc_attr( get_bloginfo( 'name', 'display' ) ) . '"
					data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '"
					data-locale="en">';

					$this->credit_card_form( array( 'fields_have_names' => false ) );

				echo '</div>';
			?>
		</fieldset>
		<?php
	}

	/**
	 * payment_scripts function.
	 *
	 * @access public
	 */
	public function payment_scripts() {
		WC_PayZQ::log( "Entradbi en payment_scripts " );

		$payzq_params = array(
			'i18n_terms'           => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-payzq' ),
			'i18n_required_fields' => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-payzq' ),
		);

		if (is_checkout_pay_page()) {
			WC_PayZQ::log( "is_checkout_pay_page" );
		}

		// If we're on the pay page we need to pass stripe.js the address of the order.
		if ( is_checkout_pay_page() && isset( $_GET['order'] ) && isset( $_GET['order_id'] ) ) {
			$order_key = urldecode( $_GET['order'] );
			$order_id  = absint( $_GET['order_id'] );
			$order     = wc_get_order( $order_id );

			if ( $order->id === $order_id && $order->order_key === $order_key ) {
				$payzq_params['billing_first_name'] = $order->billing_first_name;
				$payzq_params['billing_last_name']  = $order->billing_last_name;
				$payzq_params['billing_address_1']  = $order->billing_address_1;
				$payzq_params['billing_address_2']  = $order->billing_address_2;
				$payzq_params['billing_state']      = $order->billing_state;
				$payzq_params['billing_city']       = $order->billing_city;
				$payzq_params['billing_postcode']   = $order->billing_postcode;
				$payzq_params['billing_country']    = $order->billing_country;
			}
		}

		wp_localize_script( 'woocommerce_payzq', 'wc_payzq_params', apply_filters( 'wc_payzq_params', $payzq_params ) );
	}

	/**
	 * Generate the request for the payment.
	 * @param  WC_Order $order
	 * @return array()
	 */
	protected function generate_payment_request( $order ) {

		$card_number = WC_PayZQ_API::clear_card_number($_POST['payzq-card-number']);
		$expiry = WC_PayZQ_API::clear_card_date($_POST['payzq-card-expiry']);
		$cardholder_name = $_POST['billing_first_name'].' '.$_POST['billing_first_name'];

		$credit_card = array(
      "cardholder" => $cardholder_name,
      "type" => WC_PayZQ_API::get_card_type($card_number),
      "number" => $card_number,
      "cvv" => $_POST['payzq-card-cvc'],
      "expiry" => $expiry,
    );

    $billing = array(
      "name" => $order->get_billing_first_name(). ' ' .$order->get_billing_last_name(),
      "fiscal_code" => '',
      "address" => $order->get_billing_address_1(). ' '. $order->get_billing_address_2(),
      "country" => $order->get_billing_country(),
      "state_province" => $order->get_billing_state(),
      "postal_code" => $order->get_billing_postcode(),
      "city" => $order->get_billing_city(),
    );

    $shipping = array(
      "name" => $order->get_shipping_first_name().' '.$order->get_shipping_last_name(),
      "fiscal_code" => '',
      "address" => $order->get_shipping_address_1(). ' '. $order->get_shipping_address_2(),
      "country" => $order->get_shipping_country(), //$this->context->country->iso_code,
      "state_province" => $order->get_shipping_state(),
      "postal_code" => $order->get_shipping_postcode(),
      "city" => $order->get_shipping_city(),
    );

    $breakdown = array();

		$items = $order->get_items();

    foreach ($items as $key => $item) {
			$product  = wc_get_product( $item->get_product_id() );

      $breakdown[] = array(
        "description" => $product->get_name(),
        "subtotal" => floatval($item->get_subtotal()),
        "taxes" => floatval($item->get_subtotal_tax()),
        "total" => floatval($item->get_total() + $item->get_total_tax()),
				"quantity" => $item->get_quantity()
      );
    }

    if ($order->get_shipping_total() > 0) {
      $breakdown[] = array(
        "description" => 'Shipping cost',
        "subtotal" => floatval($order->get_shipping_total()),
        "taxes" => floatval($order->get_shipping_tax()),
        "total" => floatval($order->get_shipping_total() + $order->get_shipping_tax()),
				"quantity" => 1
      );
    }

		$nex_code_transaction = WC_PayZQ_API::get_payzq_transaction_code();
		$ip = WC_PayZQ_API::get_ip_server();

		$token = WC_PayZQ_API::get_secret_key();
		$token_payload = WC_PayZQ_API::getPayload($token);
    $send_avs = (in_array('avs', $token_payload['security'])) ? true : false;

    $response = array(
      "type" => "authorize_and_capture",
      "transaction_id" => $nex_code_transaction,
      "target_transaction_id" => '',
      "amount" => floatval(number_format($order->get_total(), 2, '.', '')),
      "currency" => $order->get_currency(),
      "credit_card" => $credit_card,
      "billing" => $billing,
      "shipping" => $shipping,
      "breakdown" => $breakdown,
      "3ds" => false,
      "ip" => $ip,
    );

		if ($send_avs) {
			$avs = array(
	      "address" => $order->get_billing_address_1(). ' ' .$order	->get_billing_address_2(),
	      "country" => $order->get_billing_country(),
	      "state_province" => $order->get_billing_state(),
	      "email" => $order->get_billing_email(),
	      "cardholder_name" => $cardholder_name,
	      "postal_code" => $order->get_billing_postcode(),
	      "phone" => $order->get_billing_phone(),
	      "city" => $order->get_billing_city(),
	    );

			$response['avs'] = $avs;
		}

		return $response;
	}

	protected function generate_refund_request( $order, $amount ) {
		return array(
      "type" => "refund",
      "transaction_id" => WC_PayZQ_API::get_payzq_transaction_code(),
      "target_transaction_id" => $order->get_transaction_id(),
      "amount" => floatval(number_format($amount, 2, '.', '')),
      "currency" => $order->get_currency(),
      "ip" => WC_PayZQ_API::get_ip_server(),
    );
	}

	/**
	 * Process the payment
	 */
	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		try {
			WC_PayZQ::log( "Process payment. Order ID: ".$order_id );

			$order  = wc_get_order( $order_id );
			$p_response = null;

			if ( $order->get_total() > 0 ) {
				// Make the request to API
				$response = WC_PayZQ_API::request( $this->generate_payment_request( $order ) );

				if ( is_wp_error( $response ) ) {
					throw new Exception( $response->get_error_code() . ': ' . $response->get_error_message() );
				}

				// Process valid response
				$p_response = $this->process_response( $response, $order );
			} else {
				$order->payment_complete();
			}

			if (is_null($p_response) || !isset($p_response['code']) || (isset($p_response['code']) && $p_response['code'] != '00')) {
				throw new Exception( __( 'an error has ocurred whit the ', 'woocommerce-gateway-payzq' ) );
			}

			// Remove cart
			WC()->cart->empty_cart();

			// Return thank you page redirect
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			WC()->session->set( 'refresh_totals', true );
			WC_PayZQ::log( sprintf( __( '1Error: %s', 'woocommerce-gateway-payzq' ), $e->getMessage() ) );
			return;
		}
	}

	/**
	 * Store extra meta data for an order from a PayZQ Response.
	 */
	public function process_response( $response, $order ) {
		WC_PayZQ::log( "Processing response: " . print_r( $response, true ) );

		// Store charge data
		update_post_meta( $order->id, '_payzq_transaction_id', $response['transaction_id'] );
		update_post_meta( $order->id, '_payzq_transaction_captured', $response['message'] === 'Accepted' ? 'yes' : 'no' );

		if ( $response['code'] == '00' ) {
			$order->payment_complete( $response['transaction_id'] );
			WC_PayZQ::log( "Successful charge: " . $response['transaction_id'] );
		}

		return $response;
	}

	/**
	 * Refund a charge
	 * @param  int $order_id
	 * @param  float $amount
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		WC_PayZQ::log( "Process refund. Order ID: $order_id for the amount of {$amount} and reason: $reason" );
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() ) {
			return false;
		}

		$response = WC_PayZQ_API::request( $this->generate_refund_request( $order, $amount ) );

		if ( is_wp_error( $response ) ) {
			WC_PayZQ::log( "Error: " . $response->get_error_message() );
			return $response;
		} elseif ( ! empty( $response['transaction_id'] ) ) {
			$refund_message = sprintf( __( 'Refunded %s - Refund ID: %s - Reason: %s', 'woocommerce-gateway-payzq' ), wc_price( $amount ), $response['transaction_id'], $reason );
			$order->add_order_note( $refund_message );
			WC_PayZQ::log( "Success: " . html_entity_decode( strip_tags( $refund_message ) ) );
			return true;
		}
	}
}
