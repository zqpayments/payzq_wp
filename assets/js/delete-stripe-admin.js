jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle Stripe admin functions.
	 */
	var wc_stripe_admin = {
		isTestMode: function() {
			return $( '#woocommerce_payzq_testmode' ).is( ':checked' );
		},

		getSecretKey: function() {
			if ( wc_stripe_admin.isTestMode() ) {
				return $( '#woocommerce_payzq_test_secret_key' ).val();
			} else {
				return $( '#woocommerce_payzq_secret_key' ).val();
			}
		},

		init: function() {
			$( document.body ).on( 'change', '#woocommerce_payzq_testmode', function() {
				var test_secret_key = $( '#woocommerce_payzq_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					merchant_key = $( '#woocommerce_payzq_merchant_key' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_payzq_secret_key' ).parents( 'tr' ).eq( 0 ),

				if ( $( this ).is( ':checked' ) ) {
					test_secret_key.show();
					merchant_key.show();
					live_secret_key.hide();
				} else {
					test_secret_key.hide();
					live_secret_key.show();
					merchant_key.show();
				}
			} );

			$( '#woocommerce_payzq_testmode' ).change();

		}
	};

	wc_stripe_admin.init();
});
