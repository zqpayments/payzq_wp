<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_payzq_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-payzq' ),
			'label'       => __( 'Enable PayZQ', 'woocommerce-gateway-payzq' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-gateway-payzq' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-payzq' ),
			'default'     => __( 'Card (PayZQ)', 'woocommerce-gateway-payzq' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-gateway-payzq' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-payzq' ),
			'default'     => __( 'Pay with your card via PayZQ.', 'woocommerce-gateway-payzq' ),
			'desc_tip'    => true,
		),
		'testmode' => array(
			'title'       => __( 'Test mode', 'woocommerce-gateway-payzq' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-payzq' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-payzq' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'test_secret_key' => array(
			'title'       => __( 'PayZQ Test Secret Key', 'woocommerce-gateway-payzq' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your PayZQ account.', 'woocommerce-gateway-payzq' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'secret_key' => array(
			'title'       => __( 'PayZQ Secret Key', 'woocommerce-gateway-payzq' ),
			'type'        => 'text',
			'description' => __( 'Get your API key from your PayZQ account.', 'woocommerce-gateway-payzq' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'merchant_key' => array(
			'title'       => __( 'Merchant Key', 'woocommerce-gateway-payzq' ),
			'type'        => 'text',
			'description' => __( 'Get your Merchant key from your PayZQ account.', 'woocommerce-gateway-payzq' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'statement_descriptor' => array(
			'title'       => __( 'Statement Descriptor', 'woocommerce-gateway-payzq' ),
			'type'        => 'text',
			'description' => __( 'Extra information about a charge. This will appear on your customer’s credit card statement.', 'woocommerce-gateway-payzq' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'logging' => array(
			'title'       => __( 'Logging', 'woocommerce-gateway-payzq' ),
			'label'       => __( 'Log debug messages', 'woocommerce-gateway-payzq' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-payzq' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);
