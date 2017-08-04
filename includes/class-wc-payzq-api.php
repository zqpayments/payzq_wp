<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_PayZQ_API class.
 *
 * Communicates with Stripe API.
 */
class WC_PayZQ_API {

	private static $api_base_url = 'http://test-zms.zertifica.org:7743/api/v1/transactions/';
  private static $key_jwt = 'secret';
  private static $iv = '4242424242424242';

	public static function clear_card_number($number)
	{
		self::log( "clear_card_number" );
		return str_replace(' ', '', $number);
	}

	public static function clear_card_date($date)
	{
		self::log( "clear_card_date" );
		return str_replace(array(' ', '/', '\\'), '', $date);
	}

	public static function get_card_type($card_number)
	{
		self::log( "get_card_type" );
		if (preg_match('/^4/', $card_number)) return 'Visa';
		if (preg_match('/^(34|37)/', $card_number)) return 'Amex';
		if (preg_match('/^5[1-5]/', $card_number)) return 'MasterCard';
		if (preg_match('/^6011/', $card_number)) return 'Discover';
		if (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{4,}/', $card_number)) return 'Diners';
		if (preg_match('/^(?:2131|1800|35[0-9]{3})[0-9]{3,}/', $card_number)) return 'Jcb';
	}

	public static function get_payzq_transaction_code	()
    {
			self::log( "get_payzq_transaction_code" );
			global $wpdb;

      $sql = 'SELECT UTC_TIMESTAMP() as time FROM DUAL;';
      $result = $wpdb->get_results($sql);

      $host= gethostname();
      $ip = gethostbyname($host);

      $chars = array(' ', '-', '.', ':');
      $ip = str_replace($chars, '', $ip);
      $time = str_replace($chars, '', $result[0]->time);

      return 'EC_PAY'.$ip.'ZQ'.$time;
    }

		public static function get_ip_server()
    {
			self::log( "get_ip_server" );
      $host = gethostname();
      $ip = gethostbyname($host);
			return $ip;
    }

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $secret_key = '';

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_secret_key( $secret_key ) {
		self::$secret_key = $secret_key;
	}

	/**
	 * Get secret key.
	 * @return string
	 */
	public static function get_secret_key() {
		if ( ! self::$secret_key ) {
			$options = get_option( 'woocommerce_payzq_settings' );

			if ( isset( $options['testmode'], $options['secret_key'], $options['test_secret_key'] ) ) {
				self::set_secret_key( 'yes' === $options['testmode'] ? $options['test_secret_key'] : $options['secret_key'] );
			}
		}
		return self::$secret_key;
	}


	private static $merchant_key = '';

	public static function set_merchant_key( $merchant_key ) {
		self::$merchant_key = $merchant_key;
	}

	public static function get_merchant_key() {
		if ( ! self::$merchant_key ) {
			$options = get_option( 'woocommerce_payzq_settings' );

			if ( isset( $options['merchant_key'] ) ) {
				self::set_merchant_key( $options['merchant_key'] );
			}
		}
		return self::$merchant_key;
	}

		public static function cypherData($json_data)
	  {
			self::log( "A1: "  );
	    $merchant_key = self::get_merchant_key();
			self::log( "A2: "  );
	    $margen = (strlen($json_data) == 16) ? 0 : (intdiv(strlen($json_data), 16) + 1) * 16 - strlen($json_data);
	    // AES-128-CFB porque la merchant_key es de 16 bytes
	    // the option 3 is not documented in php web page
			self::log( "A13: "  );
	    $data = openssl_encrypt($json_data.str_repeat('#', $margen), 'aes-128-cbc', $merchant_key, 3, self::$iv);
			self::log( "A14: "  );
	    $json_compress = gzcompress($data);
			self::log( "A15: "  );
	    $json_b64 = base64_encode($json_compress);
			self::log( "A16: "  );
	    $json_utf = utf8_decode($json_b64);
			self::log( "A17: "  );
	    return $json_utf;
	  }

		public static function decodeCypherData($codified_data)
		{
			$merchant_key = self::get_merchant_key();
		  $compressed_data = base64_decode($codified_data);
		  $descompressed_data = gzuncompress($compressed_data);
		  $decrypted_data = openssl_decrypt($descompressed_data , 'aes-128-cbc' , $merchant_key, OPENSSL_ZERO_PADDING, $this->iv );
		  $clean_text = str_replace('#','', $decrypted_data);
		  $data = json_decode($clean_text, true);
		  return $data;
		}


		public static function get_header($token)
		{
			return array(
        'Content-Type: application/json',
        'Authorization: JWT '.$token
      );
		}
	/**
	 * Send the request to Stripe's API
	 *
	 * @param array $request
	 * @param string $api
	 * @return array|WP_Error
	 */
	public static function request( $request) {
		self::log( "request: " . print_r( $request, true ) );

		$token = self::get_secret_key();
		self::log( " token: " . $token );

		$merchant_key = self::get_merchant_key();
		self::log( " merchant_key: " . $merchant_key );

		$token_payload = JWT::decode($token, self::$key_jwt, false);
    $cypher = (in_array('cypher', $token_payload['security'])) ? true : false;

		$json = json_encode($request, JSON_PRESERVE_ZERO_FRACTION);

    if ($cypher) {
			self::log( " cypher: " );
      $cypher_data = self::cypherData($json);
      $json = json_encode(array('request' => $cypher_data));
    }

		self::log( " aAPI: ".self::$api_base_url );
		self::log( " data: ".json_encode($request) );

		try {
			$curl = new Curl();
			self::log( " yurl: ".self::$api_base_url );
			self::log( " data: ".json_encode(self::get_header($token)) );
			list($curl_body, $curl_status, $curl_header) = $curl->request('post', self::$api_base_url, self::get_header($token), $json, false);
		} catch (Exception $e) {
			self::log( " error: ".$e->getMessage() );
			return new WP_Error( 'payzq_error', 'error '.$e->getMessage() );
		}

		self::log( " curl_body: ".json_encode($curl_body) );
		self::log( " curl_status: ".json_encode($curl_status) );
		self::log( " curl_header: ".json_encode($curl_header) );

		if ($curl_status == 200 && $message = json_decode($curl_body, true)) {
	    if ($message['code'] === '00') {
				return $message;
			} else {
				self::log( "Error: Transaccion rechazada " );
				return new WP_Error( 'payzq_error', 'Transaccion rechazada' );
			}
		} else {
			self::log( "Error: Has ocurred an error calling curl ". $curl_body );
			return new WP_Error( 'payzq_error', 'Ha ocurrido un error, intenelo de nuevo' );
		}

		$parsed_response = json_decode( $response['body'] );
	}

	/**
	 * Logs
	 *
	 * @since 3.1.0
	 * @version 3.1.0
	 *
	 * @param string $message
	 */
	public static function log( $message ) {
		$options = get_option( 'woocommerce_payzq_settings' );

		if ( 'yes' === $options['logging'] ) {
			WC_PayZQ::log( $message );
		}
	}
}
