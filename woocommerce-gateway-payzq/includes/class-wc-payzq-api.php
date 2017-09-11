<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_PayZQ_API class.
 * Communicates with PayZQ API.
 */
class WC_PayZQ_API {

	private static $api_base_url = 'http://test-zms.zertifica.org:7743/api/v1/transactions/';
  private static $iv = '4242424242424242';

	/**
	 * Format card number.
	 * @param string $number
	 */
	public static function clear_card_number($number) {
		return str_replace(' ', '', $number);
	}

	/**
	 * Format card date.
	 * @param string $date
	 */
	public static function clear_card_date($date) {
		return str_replace(array(' ', '/', '\\'), '', $date);
	}

	/**
	 * Get card type from number.
	 * @param string $card_number
	 */
	public static function get_card_type($card_number) {
		if (preg_match('/^4/', $card_number)) return 'Visa';
		if (preg_match('/^(34|37)/', $card_number)) return 'Amex';
		if (preg_match('/^5[1-5]/', $card_number)) return 'MasterCard';
		if (preg_match('/^6011/', $card_number)) return 'Discover';
		if (preg_match('/^3(?:0[0-5]|[68][0-9])[0-9]{4,}/', $card_number)) return 'Diners';
		if (preg_match('/^(?:2131|1800|35[0-9]{3})[0-9]{3,}/', $card_number)) return 'Jcb';
	}

	/**
	 * Generate the payzq transaction ID.
	 */
	public static function get_payzq_transaction_code() {
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

	/**
	 * Get the IP server
	 */
	public static function get_ip_server() {
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

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $merchant_key = '';

	/**
	 * Set secret Merchant Key.
	 * @param string $merchant_key
	 */
	public static function set_merchant_key( $merchant_key ) {
		self::$merchant_key = $merchant_key;
	}

	/**
	* Get secret merchant key.
	* @return string
	*/
	public static function get_merchant_key() {
		if ( ! self::$merchant_key ) {
			$options = get_option( 'woocommerce_payzq_settings' );

			if ( isset( $options['merchant_key'] ) ) {
				self::set_merchant_key( $options['merchant_key'] );
			}
		}
		return self::$merchant_key;
	}

	/**
	* cypher data.
	* @param string $json_data
	* @return string
	*/
	public static function cypherData($json_data) {
    $merchant_key = self::get_merchant_key();
    $margen = (strlen($json_data) == 16) ? 0 : (intdiv(strlen($json_data), 16) + 1) * 16 - strlen($json_data);
    // AES-128-CFB porque la merchant_key es de 16 bytes
    // the option 3 is not documented in php web page
    $data = openssl_encrypt($json_data.str_repeat('#', $margen), 'aes-128-cbc', $merchant_key, 3, self::$iv);
    $json_compress = gzcompress($data);
    $json_b64 = base64_encode($json_compress);
    $json_utf = utf8_decode($json_b64);
    return $json_utf;
  }

	/**
	* cypher data.
	* @param string $codified_data
	* @return string
	*/
	public static function decodeCypherData($codified_data) {
		$merchant_key = self::get_merchant_key();
	  $compressed_data = base64_decode($codified_data);
	  $descompressed_data = gzuncompress($compressed_data);
	  $decrypted_data = openssl_decrypt($descompressed_data , 'aes-128-cbc' , $merchant_key, OPENSSL_ZERO_PADDING, $this->iv );
	  $clean_text = str_replace('#','', $decrypted_data);
	  $data = json_decode($clean_text, true);
	  return $data;
	}

	/**
	* return header to API request
	* @param string $token
	* @return string
	*/
	public static function get_header($token) {
		return array(
      'Content-Type: application/json',
      'Authorization: JWT '.$token
    );
	}

	private static function handleJsonError($errno)
    {
        $messages = array(
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON'
        );
        throw new DomainException(isset($messages[$errno])
            ? $messages[$errno]
            : 'Unknown JSON error: ' . $errno
        );
    }

    public static function jsonDecode($input)
    {
        $obj = json_decode($input, true);
        if (function_exists('json_last_error') && $errno = json_last_error()) {
            self::handleJsonError($errno);
        }
        else if ($obj === null && $input !== 'null') {
            throw new DomainException('Null result with non-null input');
        }
        return $obj;
    }

    public static function urlsafeB64Decode($input)
    {
        $remainder = strlen($input) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $input .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($input, '-_', '+/'));
    }

    public static function getPayload($jwt) {
      $tks = explode('.', $jwt);
      if (count($tks) != 3) {
        throw new UnexpectedValueException('Wrong number of segments');
      }
      list($headb64, $payloadb64, $cryptob64) = $tks;

      if (null === $payload = self::jsonDecode(self::urlsafeB64Decode($payloadb64))) {
          throw new UnexpectedValueException('Invalid segment encoding');
      }
      return $payload;
    }

	/**
	 * Send the request to PayZQ's API
	 *
	 * @param array $request
	 * @return array|WP_Error
	 */
	public static function request( $request) {
		self::log( "new request: " . print_r( $request, true ) );

		$token = self::get_secret_key();
		$merchant_key = self::get_merchant_key();

		$token_payload = self::getPayload($token);
    $cypher = (in_array('cypher', $token_payload['security'])) ? true : false;

		$json = json_encode($request, JSON_PRESERVE_ZERO_FRACTION);

    if ($cypher) {
			self::log( "the reques must by cypher " );
      $cypher_data = self::cypherData($json);
      $json = json_encode(array('request' => $cypher_data));
    }

		try {
			self::log( "curl request: ".self::$api_base_url );

			$curl = new Curl();
			list($curl_body, $curl_status, $curl_header) = $curl->request('post', self::$api_base_url, self::get_header($token), $json, false);
		} catch (Exception $e) {
			self::log( "Error curl request: ".$e->getMessage() );
			return new WP_Error( 'payzq_error', 'error '.$e->getMessage() );
		}

		if ($curl_status == 200 && $message = json_decode($curl_body, true)) {
	    if ($message['code'] === '00') {
				return $message;
			} else {
				self::log( "Error: Transaction declined " );
				return new WP_Error( 'payzq_error', __( 'Transaction declined', 'woocommerce-gateway-payzq' ) );
			}
		} else {
			self::log( "Error: an error has ocurred calling curl". json_encode($curl_body) );
			return new WP_Error( 'payzq_error', __( 'an error has ocurred calling curl', 'woocommerce-gateway-payzq' ) . json_encode($curl_body));
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
